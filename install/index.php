<?php

/**
 * Finch PHP - 安装向导入口
 */

declare(strict_types=1);

use Finch\App;
use Finch\Core\Config;
use Finch\Core\Database;
use Finch\Core\Installer;

require dirname(__DIR__) . '/system/bootstrap.php';

$state = fp_install_handle();
fp_install_render($state);

/** @return array<string, mixed> */
function fp_install_handle(): array
{
    fp_install_prepare_runtime_dirs();

    $configState = fp_install_config_state();
    $requirements = fp_install_requirements();
    $values = fp_install_default_values();
    $errors = [];

    if ($configState['installed'] === true) {
        return [
            'view'         => 'installed',
            'requirements' => $requirements,
            'values'       => $values,
            'errors'       => [],
        ];
    }

    if ($configState['locked'] === true) {
        return [
            'view'         => 'locked',
            'requirements' => $requirements,
            'values'       => $values,
            'errors'       => [],
        ];
    }

    if (fp_install_is_post()) {
        $values = fp_install_post_values();
        $errors = array_merge(
            fp_install_requirement_errors($requirements),
            fp_install_validate($values, $configState),
        );

        if ($errors === []) {
            try {
                $applied = fp_install_run($values);

                return [
                    'view'         => 'success',
                    'requirements' => $requirements,
                    'values'       => $values,
                    'errors'       => [],
                    'applied'      => $applied,
                ];
            } catch (Throwable $e) {
                $errors[] = '安装失败：' . $e->getMessage();
            }
        }
    }

    return [
        'view'         => 'form',
        'requirements' => $requirements,
        'values'       => $values,
        'errors'       => $errors,
    ];
}

function fp_install_is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

/** @return array<string, bool> */
function fp_install_config_state(): array
{
    $config = new Config(FP_CONFIG_FILE);
    $config->load();

    return [
        'exists'    => is_file(FP_CONFIG_FILE),
        'loaded'    => $config->isLoaded(),
        'installed' => $config->isInstalled(),
        'locked'    => is_file(fp_install_lock_file()),
    ];
}

function fp_install_prepare_runtime_dirs(): void
{
    foreach ([
        FP_CONTENT_DIR,
        FP_CONTENT_DIR . '/plugins',
        FP_CONTENT_DIR . '/modules',
        FP_CONTENT_DIR . '/themes',
        FP_CONTENT_DIR . '/uploads',
        FP_STORAGE_DIR,
        FP_STORAGE_DIR . '/data',
        FP_STORAGE_DIR . '/cache',
        FP_STORAGE_DIR . '/cache/thumb',
        FP_STORAGE_DIR . '/logs',
    ] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    fp_install_write_runtime_guards();
}

/** @return list<array{label:string,ok:bool,required:bool,help:string}> */
function fp_install_requirements(): array
{
    $configWritable = is_file(FP_CONFIG_FILE)
        ? is_writable(FP_CONFIG_FILE)
        : is_writable(FP_PATH);

    return [
        [
            'label'    => 'PHP 版本 >= 8.0',
            'ok'       => version_compare(PHP_VERSION, '8.0.0', '>='),
            'required' => true,
            'help'     => PHP_VERSION,
        ],
        [
            'label'    => 'PDO 扩展',
            'ok'       => extension_loaded('pdo'),
            'required' => true,
            'help'     => extension_loaded('pdo') ? '已启用' : '未启用',
        ],
        [
            'label'    => '至少一种 PDO 数据库驱动',
            'ok'       => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
            'required' => true,
            'help'     => 'pdo_sqlite / pdo_mysql',
        ],
        [
            'label'    => 'json 扩展',
            'ok'       => extension_loaded('json'),
            'required' => true,
            'help'     => extension_loaded('json') ? '已启用' : '未启用',
        ],
        [
            'label'    => 'mbstring 扩展',
            'ok'       => extension_loaded('mbstring'),
            'required' => true,
            'help'     => extension_loaded('mbstring') ? '已启用' : '未启用',
        ],
        [
            'label'    => 'session 扩展',
            'ok'       => extension_loaded('session'),
            'required' => true,
            'help'     => extension_loaded('session') ? '已启用' : '未启用',
        ],
        [
            'label'    => 'openssl 扩展',
            'ok'       => extension_loaded('openssl'),
            'required' => true,
            'help'     => extension_loaded('openssl') ? '已启用' : '未启用',
        ],
        [
            'label'    => 'fileinfo 扩展',
            'ok'       => extension_loaded('fileinfo'),
            'required' => true,
            'help'     => extension_loaded('fileinfo') ? '已启用' : '未启用',
        ],
        [
            'label'    => 'gd 扩展',
            'ok'       => extension_loaded('gd'),
            'required' => true,
            'help'     => extension_loaded('gd') ? '已启用' : '未启用',
        ],
        [
            'label'    => 'curl 扩展',
            'ok'       => extension_loaded('curl'),
            'required' => true,
            'help'     => extension_loaded('curl') ? '已启用' : '未启用',
        ],
        [
            'label'    => 'pdo_sqlite 扩展',
            'ok'       => extension_loaded('pdo_sqlite'),
            'required' => false,
            'help'     => extension_loaded('pdo_sqlite') ? '可用 SQLite 安装' : '如使用 SQLite 需启用',
        ],
        [
            'label'    => 'pdo_mysql 扩展',
            'ok'       => extension_loaded('pdo_mysql'),
            'required' => false,
            'help'     => extension_loaded('pdo_mysql') ? '可用 MySQL/MariaDB 安装' : '如使用 MySQL 需启用',
        ],
        [
            'label'    => '站点根目录可写 config.php',
            'ok'       => $configWritable,
            'required' => true,
            'help'     => FP_CONFIG_FILE,
        ],
        [
            'label'    => 'storage/data 可写',
            'ok'       => is_dir(FP_STORAGE_DIR . '/data') && is_writable(FP_STORAGE_DIR . '/data'),
            'required' => true,
            'help'     => FP_STORAGE_DIR . '/data',
        ],
        [
            'label'    => 'storage/logs 可写',
            'ok'       => is_dir(FP_STORAGE_DIR . '/logs') && is_writable(FP_STORAGE_DIR . '/logs'),
            'required' => true,
            'help'     => FP_STORAGE_DIR . '/logs',
        ],
        [
            'label'    => 'content/uploads 可写',
            'ok'       => is_dir(FP_CONTENT_DIR . '/uploads') && is_writable(FP_CONTENT_DIR . '/uploads'),
            'required' => true,
            'help'     => FP_CONTENT_DIR . '/uploads',
        ],
    ];
}

/** @param list<array{label:string,ok:bool,required:bool,help:string}> $requirements @return list<string> */
function fp_install_requirement_errors(array $requirements): array
{
    $errors = [];
    foreach ($requirements as $item) {
        if ($item['required'] && !$item['ok']) {
            $errors[] = '环境检查未通过：' . $item['label'];
        }
    }

    return $errors;
}

/** @return array<string, string> */
function fp_install_default_values(): array
{
    return [
        'site_name'              => 'Finch',
        'site_url'               => fp_install_guess_site_url(),
        'timezone'               => 'Asia/Shanghai',
        'driver'                 => extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql',
        'table_prefix'           => 'fp_',
        'sqlite_database'        => 'storage/data/finch.sqlite',
        'mysql_host'             => 'localhost',
        'mysql_port'             => '3306',
        'mysql_database'         => 'finchphp',
        'mysql_username'         => '',
        'mysql_password'         => '',
        'admin_username'         => 'admin',
        'admin_email'            => '',
        'admin_password'         => '',
        'admin_password_confirm' => '',
    ];
}

/** @return array<string, string> */
function fp_install_post_values(): array
{
    $defaults = fp_install_default_values();
    $values = [];

    foreach ($defaults as $key => $default) {
        $raw = $_POST[$key] ?? $default;
        $values[$key] = is_scalar($raw) ? trim((string) $raw) : $default;
    }

    return $values;
}

/** @param array<string, string> $values @param array<string, bool> $configState @return list<string> */
function fp_install_validate(array $values, array $configState): array
{
    $errors = [];

    if ($configState['exists']) {
        $errors[] = 'config.php 已存在。为避免覆盖现有站点，请先确认并手动移除该文件后再安装。';
    }

    if ($configState['locked']) {
        $errors[] = '检测到安装锁。若确需重新安装，请先备份数据并手动删除 storage/data/install.lock。';
    }

    if ($values['site_name'] === '') {
        $errors[] = '站点名称不能为空。';
    }

    if ($values['site_url'] !== '' && filter_var($values['site_url'], FILTER_VALIDATE_URL) === false) {
        $errors[] = '站点地址格式不正确。';
    }

    if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
        $errors[] = '时区不在 PHP 支持列表中。';
    }

    if (!in_array($values['driver'], ['sqlite', 'mysql', 'mariadb'], true)) {
        $errors[] = '数据库驱动只能选择 sqlite 或 mysql。';
    }

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $values['table_prefix'])) {
        $errors[] = '数据表前缀只能包含字母、数字、下划线，并且不能以数字开头。';
    }

    if ($values['driver'] === 'sqlite') {
        if (!extension_loaded('pdo_sqlite')) {
            $errors[] = '当前 PHP 未启用 pdo_sqlite，不能使用 SQLite 安装。';
        }

        $sqlitePath = fp_install_sqlite_path($values['sqlite_database']);
        if ($sqlitePath === '' || $sqlitePath === ':memory:') {
            $errors[] = 'SQLite 数据库路径不能为空，也不能使用 :memory:。';
        } else {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                $errors[] = '无法创建 SQLite 数据库目录：' . $dir;
            } elseif (!is_writable($dir)) {
                $errors[] = 'SQLite 数据库目录不可写：' . $dir;
            } elseif (is_dir($sqlitePath)) {
                $errors[] = 'SQLite 数据库路径不能是目录。';
            }
        }
    } else {
        if (!extension_loaded('pdo_mysql')) {
            $errors[] = '当前 PHP 未启用 pdo_mysql，不能使用 MySQL/MariaDB 安装。';
        }
        if ($values['mysql_host'] === '') {
            $errors[] = 'MySQL 主机不能为空。';
        }
        if ((int) $values['mysql_port'] <= 0) {
            $errors[] = 'MySQL 端口不正确。';
        }
        if ($values['mysql_database'] === '') {
            $errors[] = 'MySQL 数据库名不能为空。';
        }
        if ($values['mysql_username'] === '') {
            $errors[] = 'MySQL 用户名不能为空。';
        }
    }

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $values['admin_username'])) {
        $errors[] = '管理员用户名需为 3-50 位字母、数字或下划线。';
    }

    if (filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = '管理员邮箱格式不正确。';
    }

    if (mb_strlen($values['admin_password']) < 8) {
        $errors[] = '管理员密码至少 8 位。';
    }

    if ($values['admin_password'] !== $values['admin_password_confirm']) {
        $errors[] = '两次输入的管理员密码不一致。';
    }

    return $errors;
}

/** @param array<string, string> $values @return list<string> */
function fp_install_run(array $values): array
{
    $databaseConfig = fp_install_database_config($values);
    $db = new Database($databaseConfig);
    $db->connect();

    App::reset();
    App::getInstance()->useDatabase($db);

    $installer = new Installer($db, FP_SYSTEM_DIR . '/Database/Migrations');
    $applied = $installer->migrate();
    $installer->seedSettings([
        'site_name'     => $values['site_name'],
        'site_url'      => $values['site_url'],
        'site_timezone' => $values['timezone'],
    ]);
    $installer->seedRoles();
    $installer->createAdmin($values['admin_username'], $values['admin_email'], $values['admin_password']);

    fp_install_write_lock($values);

    try {
        fp_install_write_config([
            'app' => [
                'key'       => bin2hex(random_bytes(32)),
                'debug'     => false,
                'installed' => true,
                'timezone'  => $values['timezone'],
            ],
            'database' => $databaseConfig,
            'cookie' => [
                'name'     => 'finch_session',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ],
            'session' => [
                'lifetime' => 0,
            ],
        ]);
    } catch (Throwable $e) {
        @unlink(fp_install_lock_file());
        throw $e;
    }

    return $applied;
}

/** @param array<string, string> $values @return array<string, mixed> */
function fp_install_database_config(array $values): array
{
    $driver = $values['driver'] === 'mariadb' ? 'mysql' : $values['driver'];

    if ($driver === 'sqlite') {
        return [
            'driver'   => 'sqlite',
            'database' => fp_install_sqlite_path($values['sqlite_database']),
            'prefix'   => $values['table_prefix'],
        ];
    }

    return [
        'driver'   => 'mysql',
        'host'     => $values['mysql_host'],
        'port'     => (int) $values['mysql_port'],
        'database' => $values['mysql_database'],
        'username' => $values['mysql_username'],
        'password' => $values['mysql_password'],
        'charset'  => 'utf8mb4',
        'prefix'   => $values['table_prefix'],
    ];
}

function fp_install_sqlite_path(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path === ':memory:') {
        return $path;
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    return FP_PATH . '/' . ltrim($path, '/');
}

function fp_install_lock_file(): string
{
    return FP_STORAGE_DIR . '/data/install.lock';
}

/** @param array<string, string> $values */
function fp_install_write_lock(array $values): void
{
    $content = json_encode([
        'installed_at' => gmdate('Y-m-d H:i:s'),
        'site_name'    => $values['site_name'],
        'site_url'     => $values['site_url'],
        'driver'       => $values['driver'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($content) || file_put_contents(fp_install_lock_file(), $content . "\n", LOCK_EX) === false) {
        throw new RuntimeException('无法写入安装锁，请检查 storage/data 权限。');
    }

    @chmod(fp_install_lock_file(), 0644);
}

function fp_install_write_runtime_guards(): void
{
    foreach ([
        FP_CONTENT_DIR . '/index.html',
        FP_CONTENT_DIR . '/plugins/index.html',
        FP_CONTENT_DIR . '/modules/index.html',
        FP_CONTENT_DIR . '/themes/index.html',
        FP_CONTENT_DIR . '/uploads/index.html',
        FP_STORAGE_DIR . '/cache/index.html',
        FP_STORAGE_DIR . '/cache/thumb/index.html',
        FP_STORAGE_DIR . '/logs/index.html',
        FP_STORAGE_DIR . '/data/index.html',
    ] as $file) {
        if (!is_file($file)) {
            @file_put_contents($file, '');
        }
    }

    $uploadHtaccess = FP_CONTENT_DIR . '/uploads/.htaccess';
    if (!is_file($uploadHtaccess)) {
        @file_put_contents($uploadHtaccess, "Options -ExecCGI -Indexes\nAddHandler default-handler .html .htm\n\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|phar|inc)$\">\n    Require all denied\n</FilesMatch>\n");
    }
}

/** @param array<string, mixed> $config */
function fp_install_write_config(array $config): void
{
    if (is_file(FP_CONFIG_FILE)) {
        throw new RuntimeException('config.php 已存在，已停止写入。');
    }

    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
    $tmpFile = FP_CONFIG_FILE . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
        throw new RuntimeException('无法写入临时配置文件：' . $tmpFile);
    }

    @chmod($tmpFile, 0644);

    if (!@rename($tmpFile, FP_CONFIG_FILE)) {
        @unlink($tmpFile);
        throw new RuntimeException('无法生成 config.php，请检查站点根目录写入权限。');
    }
}

function fp_install_guess_site_url(): string
{
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $secure ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $root = rtrim(dirname(dirname($script)), '/');

    return $scheme . '://' . $host . ($root === '' ? '' : $root);
}

/** @param array<string, mixed> $state */
function fp_install_render(array $state): void
{
    $view = (string) $state['view'];
    $values = $state['values'];
    $errors = $state['errors'];
    $requirements = $state['requirements'];

    ?><!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装 Finch PHP</title>
    <style>
        :root { color-scheme: light; --border: #d8dee4; --muted: #57606a; --bg: #f6f8fa; --ok: #1a7f37; --bad: #cf222e; --brand: #0969da; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #24292f; background: var(--bg); }
        main { width: min(960px, calc(100% - 32px)); margin: 32px auto; }
        .panel { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 24px; margin-bottom: 16px; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        h2 { margin: 0 0 16px; font-size: 18px; }
        p { color: var(--muted); line-height: 1.6; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input, select { width: 100%; min-height: 40px; border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; font: inherit; }
        .full { grid-column: 1 / -1; }
        .hint { display: block; color: var(--muted); font-size: 13px; margin-top: 6px; }
        .checks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; list-style: none; padding: 0; margin: 0; }
        .checks li { display: flex; gap: 8px; align-items: flex-start; color: var(--muted); }
        .checks strong { color: #24292f; }
        .ok { color: var(--ok); font-weight: 700; }
        .bad { color: var(--bad); font-weight: 700; }
        .errors { border-color: #ffebe9; background: #fff1f0; color: #82071e; }
        .errors ul { margin: 8px 0 0; }
        button, .button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; border: 0; border-radius: 6px; padding: 0 16px; background: var(--brand); color: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
        .button.secondary { background: #57606a; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        @media (max-width: 720px) { .grid, .checks { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <section class="panel">
        <h1>安装 Finch PHP</h1>
        <p>安装向导会创建数据表、写入系统默认配置、创建内置角色和首个管理员，并生成站点根目录下的 config.php。</p>
    </section>

    <?php if ($view === 'installed') : ?>
        <section class="panel">
            <h2>系统已安装</h2>
            <p>检测到当前站点已经完成安装。为避免覆盖现有数据，安装向导已停止。</p>
            <div class="actions">
                <a class="button" href="../admin">进入后台</a>
                <a class="button secondary" href="../">访问首页</a>
            </div>
        </section>
    <?php elseif ($view === 'locked') : ?>
        <section class="panel">
            <h2>检测到安装锁</h2>
            <p>当前站点存在 storage/data/install.lock，但没有可用的已安装配置。若这是一次中断安装，请先备份数据，再手动删除安装锁后重新进入向导。</p>
        </section>
    <?php elseif ($view === 'success') : ?>
        <section class="panel">
            <h2>安装完成</h2>
            <p>Finch PHP 已经完成初始化。已执行迁移 <?php echo count((array) ($state['applied'] ?? [])); ?> 个，config.php 已生成。</p>
            <div class="actions">
                <a class="button" href="../admin/login">登录后台</a>
                <a class="button secondary" href="../">访问首页</a>
            </div>
        </section>
    <?php else : ?>
        <?php fp_install_render_requirements($requirements); ?>

        <?php if ($errors !== []) : ?>
            <section class="panel errors">
                <h2>需要处理的问题</h2>
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo fp_install_e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <form method="post" class="panel" autocomplete="off">
            <h2>站点信息</h2>
            <div class="grid">
                <div>
                    <label for="site_name">站点名称</label>
                    <input id="site_name" name="site_name" value="<?php echo fp_install_e($values['site_name']); ?>" required>
                </div>
                <div>
                    <label for="site_url">站点地址</label>
                    <input id="site_url" name="site_url" value="<?php echo fp_install_e($values['site_url']); ?>" placeholder="https://example.com">
                </div>
                <div class="full">
                    <label for="timezone">时区</label>
                    <input id="timezone" name="timezone" value="<?php echo fp_install_e($values['timezone']); ?>" required>
                    <span class="hint">例如 Asia/Shanghai、UTC、America/New_York。</span>
                </div>
            </div>

            <h2 style="margin-top:24px">数据库</h2>
            <div class="grid">
                <div>
                    <label for="driver">数据库驱动</label>
                    <select id="driver" name="driver">
                        <option value="sqlite" <?php echo $values['driver'] === 'sqlite' ? 'selected' : ''; ?>>SQLite</option>
                        <option value="mysql" <?php echo in_array($values['driver'], ['mysql', 'mariadb'], true) ? 'selected' : ''; ?>>MySQL / MariaDB</option>
                    </select>
                </div>
                <div>
                    <label for="table_prefix">数据表前缀</label>
                    <input id="table_prefix" name="table_prefix" value="<?php echo fp_install_e($values['table_prefix']); ?>" required>
                </div>
                <div class="full">
                    <label for="sqlite_database">SQLite 数据库路径</label>
                    <input id="sqlite_database" name="sqlite_database" value="<?php echo fp_install_e($values['sqlite_database']); ?>">
                    <span class="hint">相对路径会基于站点根目录解析，默认写入 storage/data/finch.sqlite。</span>
                </div>
                <div>
                    <label for="mysql_host">MySQL 主机</label>
                    <input id="mysql_host" name="mysql_host" value="<?php echo fp_install_e($values['mysql_host']); ?>">
                </div>
                <div>
                    <label for="mysql_port">MySQL 端口</label>
                    <input id="mysql_port" name="mysql_port" value="<?php echo fp_install_e($values['mysql_port']); ?>" inputmode="numeric">
                </div>
                <div>
                    <label for="mysql_database">MySQL 数据库名</label>
                    <input id="mysql_database" name="mysql_database" value="<?php echo fp_install_e($values['mysql_database']); ?>">
                </div>
                <div>
                    <label for="mysql_username">MySQL 用户名</label>
                    <input id="mysql_username" name="mysql_username" value="<?php echo fp_install_e($values['mysql_username']); ?>">
                </div>
                <div class="full">
                    <label for="mysql_password">MySQL 密码</label>
                    <input id="mysql_password" type="password" name="mysql_password" value="<?php echo fp_install_e($values['mysql_password']); ?>">
                </div>
            </div>

            <h2 style="margin-top:24px">管理员</h2>
            <div class="grid">
                <div>
                    <label for="admin_username">用户名</label>
                    <input id="admin_username" name="admin_username" value="<?php echo fp_install_e($values['admin_username']); ?>" required>
                </div>
                <div>
                    <label for="admin_email">邮箱</label>
                    <input id="admin_email" type="email" name="admin_email" value="<?php echo fp_install_e($values['admin_email']); ?>" required>
                </div>
                <div>
                    <label for="admin_password">密码</label>
                    <input id="admin_password" type="password" name="admin_password" required>
                </div>
                <div>
                    <label for="admin_password_confirm">确认密码</label>
                    <input id="admin_password_confirm" type="password" name="admin_password_confirm" required>
                </div>
            </div>

            <div class="actions">
                <button type="submit">开始安装</button>
            </div>
        </form>
    <?php endif; ?>
</main>
</body>
</html><?php
}

/** @param list<array{label:string,ok:bool,required:bool,help:string}> $requirements */
function fp_install_render_requirements(array $requirements): void
{
    ?>
    <section class="panel">
        <h2>环境检查</h2>
        <ul class="checks">
            <?php foreach ($requirements as $item) : ?>
                <li>
                    <span class="<?php echo $item['ok'] ? 'ok' : 'bad'; ?>"><?php echo $item['ok'] ? '通过' : '未通过'; ?></span>
                    <span><strong><?php echo fp_install_e($item['label']); ?></strong><br><?php echo fp_install_e($item['help']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
}

function fp_install_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
