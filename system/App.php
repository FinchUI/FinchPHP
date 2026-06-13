<?php

/**
 * Finch\App - 全局单例（$fp）
 *
 * 持有核心运行状态：db / config / settings / user / router / hooks / request / logger 等
 * （见 PROJECT_PLAN §4.2）。核心属性对外只读：通过 __get 暴露、禁止 __set。
 *
 * 启动流程见 §4.1：
 *   init() → 加载配置、设时区、注册错误处理、（已安装时）连库/加载设置/恢复会话/注册路由
 *   run()  → Router 分发请求并输出响应
 */

declare(strict_types=1);

namespace Finch;

use Finch\Core\Asset;
use Finch\Core\Cache;
use Finch\Core\Config;
use Finch\Core\Database;
use Finch\Core\ErrorHandler;
use Finch\Core\Gate;
use Finch\Core\Hook;
use Finch\Core\Lang;
use Finch\Core\Logger;
use Finch\Core\Middleware;
use Finch\Core\Request;
use Finch\Core\Response;
use Finch\Core\Router;
use Finch\Core\Session;
use Finch\Core\Settings;
use Finch\Core\Template;
use Finch\Core\TemplateContext;
use Finch\Model\ApiToken;
use Finch\Model\User;
use Finch\Service\AuthService;
use Finch\Service\PluginService;
use RuntimeException;

final class App
{
    private static ?App $instance = null;

    /** @var array<string, mixed> 核心服务容器（只读暴露） */
    private array $services = [];

    private bool $initialized = false;

    private bool $installed = false;

    private function __construct()
    {
    }

    /** 获取全局单例 */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 仅供测试：重置单例 */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /** 初始化应用（见 §4.1 启动流程）。 */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $config = new Config(FP_CONFIG_FILE);
        $config->load();
        $this->services['config'] = $config;

        $this->applyTimezone($config->get('app.timezone', 'UTC'));

        $logger = new Logger(FP_STORAGE_DIR . '/logs');
        $this->services['logger'] = $logger;
        $errorHandler = new ErrorHandler($logger, (bool) $config->get('app.debug', false));
        $errorHandler->register();
        $this->services['errorHandler'] = $errorHandler;

        $this->services['hooks'] = new Hook();
        $this->services['request'] = Request::capture();
        $this->services['gate'] = new Gate();
        $this->services['middleware'] = new Middleware();

        $this->installed = $config->isInstalled();
        if ($this->installed) {
            $this->bootInstalled($config, $logger);
        }

        $this->initialized = true;
    }

    /** 已安装时的引导：连库、加载运行期设置、恢复会话、注册路由。 */
    private function bootInstalled(Config $config, Logger $logger): void
    {
        try {
            $db = new Database($config->section('database'));
            $db->connect();
            $this->services['db'] = $db;
        } catch (\Throwable $e) {
            $logger->error('数据库连接失败：' . $e->getMessage());
            $this->installed = false;

            return;
        }

        $settings = new Settings($db);
        $settings->load();
        $this->services['settings'] = $settings;
        $this->services['cache'] = new Cache($db);
        $this->services['lang'] = new Lang($settings);
        $this->services['context'] = new TemplateContext();
        $this->services['asset'] = new Asset($settings);
        $this->services['template'] = new Template($settings, $this->services['context'], $logger);

        $templateTags = FP_SYSTEM_DIR . '/Helper/TemplateTags.php';
        if (is_file($templateTags)) {
            require_once $templateTags;
        }

        $siteTimezone = $settings->get('site_timezone');
        if (is_string($siteTimezone) && $siteTimezone !== '') {
            $this->applyTimezone($siteTimezone);
        }

        // 数据库 debug_mode 设置覆盖配置文件
        $debugMode = $settings->get('debug_mode');
        if ($debugMode !== null) {
            $this->services['errorHandler']->setDebug((bool) $debugMode);
        }

        $request = $this->services['request'];
        $sessionConfig = array_merge($config->section('cookie'), $config->section('session'));
        $session = new Session($sessionConfig, $request);
        $session->start();
        $this->services['session'] = $session;

        $auth = new AuthService(
            $db,
            $session,
            $request,
            (int) $settings->get('remember_token_days', 30),
        );
        $this->services['auth'] = $auth;
        $this->services['user'] = $auth->user();

        $router = new Router();
        $router->setMiddleware($this->services['middleware']);
        $router->registerDefaults();
        $this->services['router'] = $router;

        $pluginService = new PluginService(FP_CONTENT_DIR . '/plugins', $this->services['cache']);
        $this->services['plugins'] = $pluginService;
        $this->services['pluginRuntime'] = $pluginService->loadEnabled($logger);
    }

    /** 路由分发。 */
    public function run(): void
    {
        $response = new Response();

        if (!$this->installed) {
            $response->html($this->minimalInstallPage(), 200);
            $this->applyResponsePolicies($response, $this->request);
            $response->send();

            return;
        }

        $this->hooks->do('fp_init');

        // 维护模式拦截：管理员可正常访问后台，前台显示维护页
        if ($this->installed && (bool) $this->settings->get('maintenance_mode', false)) {
            $path = $this->request->path();
            $fp = (string) $this->request->query('fp', '');
            $isAdmin = str_starts_with($path, '/admin') || str_starts_with($fp, 'admin_');
            $isLogin = str_starts_with($path, '/login') || str_contains($fp, 'login');

            if (!$isAdmin && !$isLogin && !$this->isLoggedIn) {
                $response = new Response();
                $response->html($this->maintenancePage(), 503);
                $this->applyResponsePolicies($response, $this->request);
                $response->send();
                $this->hooks->do('fp_shutdown');
                return;
            }
        }

        $response = $this->router->dispatch($this->request);
        $this->applyResponsePolicies($response, $this->request);
        $response->send();
        $this->hooks->do('fp_shutdown');
    }

    /** 是否已完成安装 */
    public function isInstalled(): bool
    {
        return $this->installed;
    }

    /**
     * 在安装流程中注入数据库连接。
     *
     * 安装向导在 config.php 写入前手动收集凭据并连库，再交由此方法
     * 注册到单例，供 Installer 与模型使用。仅安装阶段调用。
     */
    public function useDatabase(Database $db): void
    {
        $this->services['db'] = $db;
    }

    /** 由核心认证服务同步当前请求用户。 */
    public function setCurrentUser(?User $user): void
    {
        $this->services['user'] = $user;
    }

    /** 由 API 认证中间件同步当前请求 token。 */
    public function setCurrentApiToken(?ApiToken $token): void
    {
        $this->services['apiToken'] = $token;
    }

    /** 注册一个能力标识，供插件/模块声明权限边界。 */
    public function registerCapability(string $capability, string $description = ''): void
    {
        $this->gate->register($capability, $description);
    }

    // ---------------------------------------------------------------------
    // 只读核心属性访问
    // ---------------------------------------------------------------------

    public function __get(string $name): mixed
    {
        return match ($name) {
            'isLoggedIn' => array_key_exists('user', $this->services) && $this->services['user'] !== null,
            'isAdmin' => array_key_exists('user', $this->services) && $this->services['user']?->can('*') === true,
            'isInstalled' => $this->installed,
            default => array_key_exists($name, $this->services)
                ? $this->services[$name]
                : throw new RuntimeException("核心服务未初始化或不存在：{$name}"),
        };
    }

    /** 禁止外部覆盖核心状态 */
    public function __set(string $name, mixed $value): void
    {
        throw new RuntimeException("禁止直接覆盖核心属性：{$name}");
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->services) && $this->services[$name] !== null;
    }

    // ---------------------------------------------------------------------
    // 内部
    // ---------------------------------------------------------------------

    /** 设置 PHP 时区，非法时回退 UTC 并记录警告 */
    private function applyTimezone(mixed $timezone): void
    {
        $tz = is_string($timezone) ? $timezone : 'UTC';

        if (!in_array($tz, timezone_identifiers_list(), true)) {
            if (isset($this->services['logger'])) {
                $this->services['logger']->warning("非法时区 {$tz}，已回退 UTC");
            }
            $tz = 'UTC';
        }

        date_default_timezone_set($tz);
    }

    /** 未安装时的最小提示页 */
    private function minimalInstallPage(): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>安装 Finch PHP</title></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding:4rem">'
            . '<h1>欢迎使用 Finch PHP</h1>'
            . '<p>系统尚未安装，请运行安装向导完成初始化。</p>'
            . '<p><a href="/install/" style="display:inline-block;padding:.75rem 1rem;background:#0969da;color:#fff;border-radius:6px;text-decoration:none">开始安装</a></p>'
            . '</body></html>';
    }

    /** 维护模式提示页 */
    private function maintenancePage(): string
    {
        $siteName = $this->installed ? (string) $this->settings->get('site_name', 'Finch') : 'Finch';

        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>' . htmlspecialchars($siteName, ENT_QUOTES) . ' - 维护中</title>'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f6f8fa;color:#24292f}'
            . '.card{text-align:center;padding:3rem;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);max-width:460px}'
            . 'h1{font-size:1.5rem;margin:0 0 .5rem}p{color:#57606a;margin:0 0 1.5rem}'
            . 'svg{width:48px;height:48px;color:#0969da;margin-bottom:1rem}</style></head>'
            . '<body><div class="card">'
            . '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13zM0 8a8 8 0 1116 0A8 8 0 010 8zm6.5-.25A.75.75 0 017.25 7h1a.75.75 0 01.75.75v2.75h.25a.75.75 0 010 1.5h-2a.75.75 0 010-1.5h.25v-2h-.25a.75.75 0 01-.75-.75zM8 6a1 1 0 100-2 1 1 0 000 2z"/></svg>'
            . '<h1>网站维护中</h1>'
            . '<p>我们正在进行系统维护，请稍后再来访问。</p>'
            . '</div></body></html>';
    }

    private function applyResponsePolicies(Response $response, Request $request): void
    {
        $path = $request->path();
        $method = $request->method();
        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $fp = (string) $request->query('fp', '');

        $isApi = str_starts_with($path, '/api/') || str_starts_with($fp, 'api_v1_');
        $isAdmin = str_starts_with($path, '/admin') || str_starts_with($fp, 'admin_');
        $isAuth = str_starts_with($path, '/login') || str_starts_with($path, '/register') || str_contains($fp, 'login');
        $isWrite = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);

        if ($isApi && $response->header('X-API-Version') === null) {
            $response->setHeader('X-API-Version', 'v1');
        }

        if ($response->header('Cache-Control') !== null) {
            return;
        }

        if ($isApi || $isAdmin || $isAuth || $isWrite) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->setHeader('Pragma', 'no-cache');

            return;
        }

        if (str_contains($contentType, 'text/html')) {
            $response->setHeader('Cache-Control', 'no-cache, private');
        }
    }
}
