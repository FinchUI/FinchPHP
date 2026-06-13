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

/** @return array<string, array<string, string>> */
function fp_install_languages(): array
{
    return [
        'zh-cn' => '简体中文',
        'en' => 'English',
    ];
}

/** @return array<string, array<string, string>> */
function fp_install_i18n(): array
{
    return [
        'zh-cn' => [
            'title' => '欢迎安装 Finch PHP',
            'step' => '步骤',
            'step1_title' => '欢迎',
            'step1_desc' => '请选择界面语言，并阅读同意用户协议。',
            'welcome_heading' => '欢迎安装 Finch PHP',
            'welcome_desc' => 'Finch PHP 是一套轻量、现代、纯原生 PHP 8.0+ 开发的通用内容管理系统。安装向导将引导您完成初始化配置。',
            'step2_title' => '环境检查',
            'step2_desc' => '安装向导正在检查服务器环境是否满足运行要求。',
            'step3_title' => '站点信息',
            'step3_desc' => '请填写站点基本信息、数据库配置和管理员账号。',
            'step4_title' => '安装完成',
            'next' => '下一步',
            'prev' => '上一步',
            'submit' => '开始安装',
            'language' => '界面语言',
            'license_agree' => '我已阅读并同意用户协议',
            'license_title' => '用户协议与免责声明',
            'license_content' => '<p>欢迎使用 Finch PHP 内容管理系统。在安装前，请仔细阅读以下条款：</p>
<ol>
<li><strong>软件性质</strong>：Finch PHP 是开源软件，按“原样”提供，不提供任何明示或暗示的保证。</li>
<li><strong>使用责任</strong>：使用者需自行承担使用本软件的风险，作者不对因使用本软件造成的任何直接或间接损失负责。</li>
<li><strong>数据安全</strong>：建议定期备份数据库和文件，作者不对数据丢失负责。</li>
<li><strong>安全更新</strong>：使用者有责任及时应用安全更新和补丁。</li>
<li><strong>合规使用</strong>：使用者应确保其使用方式符合当地法律法规。</li>
<li><strong>开源协议</strong>：本软件遵循 MIT 开源协议，详见项目根目录 LICENSE 文件。</li>
</ol>
<p>如您不同意上述条款，请勿安装或使用本软件。</p>',
            'license_required' => '请阅读并同意用户协议后才能继续安装。',
            'check_pass' => '通过',
            'check_fail' => '未通过',
            'check_continue' => '所有必选检查已通过，可以继续安装。',
            'check_blocked' => '存在必选检查未通过，请先解决上述问题。',
            'site_info' => '站点信息',
            'site_name' => '站点名称',
            'site_url' => '站点地址',
            'timezone' => '时区',
            'timezone_hint' => '例如 Asia/Shanghai、UTC、America/New_York。',
            'database' => '数据库',
            'driver' => '数据库驱动',
            'table_prefix' => '数据表前缀',
            'sqlite_path' => 'SQLite 数据库路径',
            'sqlite_hint' => '相对路径会基于站点根目录解析，默认写入 storage/data/finch.sqlite。',
            'mysql_host' => 'MySQL 主机',
            'mysql_port' => 'MySQL 端口',
            'mysql_database' => 'MySQL 数据库名',
            'mysql_username' => 'MySQL 用户名',
            'mysql_password' => 'MySQL 密码',
            'admin_info' => '管理员',
            'admin_username' => '用户名',
            'admin_email' => '邮箱',
            'admin_password' => '密码',
            'admin_password_confirm' => '确认密码',
            'env_check' => '环境检查',
            'installed_title' => '系统已安装',
            'installed_desc' => '检测到当前站点已经完成安装。为避免覆盖现有数据，安装向导已停止。',
            'enter_admin' => '进入后台',
            'visit_home' => '访问首页',
            'locked_title' => '检测到安装锁',
            'locked_desc' => '当前站点存在 storage/data/install.lock，但没有可用的已安装配置。若这是一次中断安装，请先备份数据，再手动删除安装锁后重新进入向导。',
            'success_title' => '安装完成',
            'success_desc' => 'Finch PHP 已经完成初始化。已执行迁移 %d 个，config.php 已生成。',
            'success_home_url_label' => '首页地址',
            'success_admin_url_label' => '后台地址',
            'login_admin' => '登录后台',
            'errors_title' => '需要处理的问题',
            'error_site_name_empty' => '站点名称不能为空。',
            'error_site_url_invalid' => '站点地址格式不正确。',
            'error_timezone_invalid' => '时区不在 PHP 支持列表中。',
            'error_driver_invalid' => '数据库驱动只能选择 sqlite 或 mysql。',
            'error_prefix_invalid' => '数据表前缀只能包含字母、数字、下划线，并且不能以数字开头。',
            'error_sqlite_no_ext' => '当前 PHP 未启用 pdo_sqlite，不能使用 SQLite 安装。',
            'error_sqlite_path_empty' => 'SQLite 数据库路径不能为空，也不能使用 :memory:。',
            'error_sqlite_dir_create' => '无法创建 SQLite 数据库目录：%s',
            'error_sqlite_dir_write' => 'SQLite 数据库目录不可写：%s',
            'error_sqlite_is_dir' => 'SQLite 数据库路径不能是目录。',
            'error_mysql_no_ext' => '当前 PHP 未启用 pdo_mysql，不能使用 MySQL/MariaDB 安装。',
            'error_mysql_host_empty' => 'MySQL 主机不能为空。',
            'error_mysql_port_invalid' => 'MySQL 端口不正确。',
            'error_mysql_db_empty' => 'MySQL 数据库名不能为空。',
            'error_mysql_user_empty' => 'MySQL 用户名不能为空。',
            'error_admin_username_invalid' => '管理员用户名需为 3-50 位字母、数字或下划线。',
            'error_admin_email_invalid' => '管理员邮箱格式不正确。',
            'error_admin_password_short' => '管理员密码至少 8 位。',
            'error_admin_password_mismatch' => '两次输入的管理员密码不一致。',
            'error_config_exists' => 'config.php 已存在。为避免覆盖现有站点，请先确认并手动移除该文件后再安装。',
            'error_install_locked' => '检测到安装锁。若确需重新安装，请先备份数据并手动删除 storage/data/install.lock。',
            'error_install_failed' => '安装失败：%s',
            'env_php_version' => 'PHP 版本 >= 8.0',
            'env_pdo' => 'PDO 扩展',
            'env_pdo_driver' => '至少一种 PDO 数据库驱动',
            'env_json' => 'json 扩展',
            'env_mbstring' => 'mbstring 扩展',
            'env_session' => 'session 扩展',
            'env_openssl' => 'openssl 扩展',
            'env_fileinfo' => 'fileinfo 扩展',
            'env_gd' => 'gd 扩展',
            'env_curl' => 'curl 扩展',
            'env_pdo_sqlite' => 'pdo_sqlite 扩展',
            'env_pdo_mysql' => 'pdo_mysql 扩展',
            'env_config_writable' => '站点根目录可写 config.php',
            'env_storage_writable' => 'storage/data 可写',
            'env_logs_writable' => 'storage/logs 可写',
            'env_uploads_writable' => 'content/uploads 可写',
        ],
        'en' => [
            'title' => 'Welcome to Finch PHP',
            'step' => 'Step',
            'step1_title' => 'Welcome',
            'step1_desc' => 'Please select your language and agree to the user agreement.',
            'welcome_heading' => 'Welcome to Finch PHP',
            'welcome_desc' => 'Finch PHP is a lightweight, modern CMS built with pure native PHP 8.0+. The installer will guide you through the initial setup.',
            'step2_title' => 'Environment Check',
            'step2_desc' => 'The installer is checking if your server meets the requirements.',
            'step3_title' => 'Site Information',
            'step3_desc' => 'Please fill in your site information, database settings, and admin account.',
            'step4_title' => 'Complete',
            'next' => 'Next',
            'prev' => 'Previous',
            'submit' => 'Install Now',
            'language' => 'Interface Language',
            'license_agree' => 'I have read and agree to the user agreement',
            'license_title' => 'User Agreement & Disclaimer',
            'license_content' => '<p>Welcome to Finch PHP Content Management System. Before installing, please read the following terms carefully:</p>
<ol>
<li><strong>Software Nature</strong>: Finch PHP is open-source software provided "as is" without any express or implied warranty.</li>
<li><strong>Usage Responsibility</strong>: Users assume all risks associated with using this software. The authors are not liable for any direct or indirect damages.</li>
<li><strong>Data Security</strong>: Regular backups of databases and files are recommended. The authors are not responsible for data loss.</li>
<li><strong>Security Updates</strong>: Users are responsible for applying security updates and patches in a timely manner.</li>
<li><strong>Compliance</strong>: Users must ensure their usage complies with local laws and regulations.</li>
<li><strong>Open Source License</strong>: This software is released under the MIT License. See the LICENSE file in the project root.</li>
</ol>
<p>If you do not agree to these terms, please do not install or use this software.</p>',
            'license_required' => 'Please read and agree to the user agreement before continuing.',
            'check_pass' => 'Pass',
            'check_fail' => 'Fail',
            'check_continue' => 'All required checks passed. You can continue with the installation.',
            'check_blocked' => 'Some required checks failed. Please resolve the issues above first.',
            'site_info' => 'Site Information',
            'site_name' => 'Site Name',
            'site_url' => 'Site URL',
            'timezone' => 'Timezone',
            'timezone_hint' => 'e.g. Asia/Shanghai, UTC, America/New_York.',
            'database' => 'Database',
            'driver' => 'Database Driver',
            'table_prefix' => 'Table Prefix',
            'sqlite_path' => 'SQLite Database Path',
            'sqlite_hint' => 'Relative paths are resolved from the site root. Default: storage/data/finch.sqlite.',
            'mysql_host' => 'MySQL Host',
            'mysql_port' => 'MySQL Port',
            'mysql_database' => 'MySQL Database',
            'mysql_username' => 'MySQL Username',
            'mysql_password' => 'MySQL Password',
            'admin_info' => 'Administrator',
            'admin_username' => 'Username',
            'admin_email' => 'Email',
            'admin_password' => 'Password',
            'admin_password_confirm' => 'Confirm Password',
            'env_check' => 'Environment Check',
            'installed_title' => 'Already Installed',
            'installed_desc' => 'This site has already been installed. The installer has stopped to prevent overwriting existing data.',
            'enter_admin' => 'Go to Admin',
            'visit_home' => 'Visit Homepage',
            'locked_title' => 'Installation Lock Detected',
            'locked_desc' => 'The file storage/data/install.lock exists but no valid configuration was found. If this was an interrupted installation, please backup your data and manually remove the lock file before retrying.',
            'success_title' => 'Installation Complete',
            'success_desc' => 'Finch PHP has been initialized successfully. %d migration(s) applied, config.php has been generated.',
            'success_home_url_label' => 'Homepage URL',
            'success_admin_url_label' => 'Admin URL',
            'login_admin' => 'Login to Admin',
            'errors_title' => 'Issues to Resolve',
            'error_site_name_empty' => 'Site name cannot be empty.',
            'error_site_url_invalid' => 'Site URL format is invalid.',
            'error_timezone_invalid' => 'Timezone is not in the PHP supported list.',
            'error_driver_invalid' => 'Database driver must be sqlite or mysql.',
            'error_prefix_invalid' => 'Table prefix can only contain letters, numbers, and underscores, and cannot start with a number.',
            'error_sqlite_no_ext' => 'pdo_sqlite extension is not enabled. Cannot install with SQLite.',
            'error_sqlite_path_empty' => 'SQLite database path cannot be empty or :memory:.',
            'error_sqlite_dir_create' => 'Cannot create SQLite database directory: %s',
            'error_sqlite_dir_write' => 'SQLite database directory is not writable: %s',
            'error_sqlite_is_dir' => 'SQLite database path cannot be a directory.',
            'error_mysql_no_ext' => 'pdo_mysql extension is not enabled. Cannot install with MySQL/MariaDB.',
            'error_mysql_host_empty' => 'MySQL host cannot be empty.',
            'error_mysql_port_invalid' => 'MySQL port is invalid.',
            'error_mysql_db_empty' => 'MySQL database name cannot be empty.',
            'error_mysql_user_empty' => 'MySQL username cannot be empty.',
            'error_admin_username_invalid' => 'Admin username must be 3-50 characters of letters, numbers, or underscores.',
            'error_admin_email_invalid' => 'Admin email format is invalid.',
            'error_admin_password_short' => 'Admin password must be at least 8 characters.',
            'error_admin_password_mismatch' => 'The two passwords do not match.',
            'error_config_exists' => 'config.php already exists. To prevent overwriting, please remove it manually before installing.',
            'error_install_locked' => 'Installation lock detected. If you need to reinstall, please backup your data and remove storage/data/install.lock manually.',
            'error_install_failed' => 'Installation failed: %s',
            'env_php_version' => 'PHP Version >= 8.0',
            'env_pdo' => 'PDO Extension',
            'env_pdo_driver' => 'At least one PDO database driver',
            'env_json' => 'json Extension',
            'env_mbstring' => 'mbstring Extension',
            'env_session' => 'session Extension',
            'env_openssl' => 'openssl Extension',
            'env_fileinfo' => 'fileinfo Extension',
            'env_gd' => 'gd Extension',
            'env_curl' => 'curl Extension',
            'env_pdo_sqlite' => 'pdo_sqlite Extension',
            'env_pdo_mysql' => 'pdo_mysql Extension',
            'env_config_writable' => 'Site root writable for config.php',
            'env_storage_writable' => 'storage/data Writable',
            'env_logs_writable' => 'storage/logs Writable',
            'env_uploads_writable' => 'content/uploads Writable',
        ],
    ];
}

/** @return array<string, string> */
function fp_install_t(): array
{
    $lang = $_GET['lang'] ?? $_POST['lang'] ?? $_COOKIE['finch_install_lang'] ?? 'zh-cn';
    $languages = fp_install_languages();
    if (!isset($languages[$lang])) {
        $lang = 'zh-cn';
    }
    $i18n = fp_install_i18n();
    return $i18n[$lang] ?? $i18n['zh-cn'];
}

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
    $t = fp_install_t();
    $step = (int) ($_GET['step'] ?? $_POST['step'] ?? 1);
    $step = max(1, min(4, $step));

    // Set language cookie if provided
    if (isset($_GET['lang']) || isset($_POST['lang'])) {
        $lang = $_GET['lang'] ?? $_POST['lang'] ?? 'zh-cn';
        $languages = fp_install_languages();
        if (isset($languages[$lang])) {
            setcookie('finch_install_lang', $lang, time() + 86400, '/');
        }
    }

    if ($configState['installed'] === true) {
        return [
            'view'         => 'installed',
            'step'         => $step,
            'requirements' => $requirements,
            'values'       => $values,
            'errors'       => [],
            't'            => $t,
        ];
    }

    if ($configState['locked'] === true) {
        return [
            'view'         => 'locked',
            'step'         => $step,
            'requirements' => $requirements,
            'values'       => $values,
            'errors'       => [],
            't'            => $t,
        ];
    }

    if (fp_install_is_post()) {
        $values = fp_install_post_values();
        $step = (int) ($values['step'] ?? $step);

        // Step 1: Validate language and license agreement
        if ($step === 1) {
            if (empty($values['agree_license'])) {
                $errors[] = $t['license_required'];
            } else {
                // Move to step 2
                header('Location: ?step=2&lang=' . urlencode($values['lang']));
                exit;
            }
        }

        // Step 2: Check requirements, move to step 3 if all pass
        if ($step === 2) {
            $requirementErrors = fp_install_requirement_errors($requirements);
            if ($requirementErrors === []) {
                header('Location: ?step=3&lang=' . urlencode($values['lang']));
                exit;
            }
        }

        // Step 3: Validate and run installation
        if ($step === 3) {
            $errors = array_merge(
                fp_install_requirement_errors($requirements),
                fp_install_validate($values, $configState),
            );

            if ($errors === []) {
                try {
                    $applied = fp_install_run($values);

                    return [
                        'view'         => 'success',
                        'step'         => 4,
                        'requirements' => $requirements,
                        'values'       => $values,
                        'errors'       => [],
                        'applied'      => $applied,
                        't'            => $t,
                    ];
                } catch (Throwable $e) {
                    $errors[] = sprintf($t['error_install_failed'], $e->getMessage());
                }
            }
        }
    }

    return [
        'view'         => 'form',
        'step'         => $step,
        'requirements' => $requirements,
        'values'       => $values,
        'errors'       => $errors,
        't'            => $t,
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
    $t = fp_install_t();
    $configWritable = is_file(FP_CONFIG_FILE)
        ? is_writable(FP_CONFIG_FILE)
        : is_writable(FP_PATH);

    $enabled = extension_loaded('pdo') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用');

    return [
        [
            'label'    => $t['env_php_version'],
            'ok'       => version_compare(PHP_VERSION, '8.0.0', '>='),
            'required' => true,
            'help'     => PHP_VERSION,
        ],
        [
            'label'    => $t['env_pdo'],
            'ok'       => extension_loaded('pdo'),
            'required' => true,
            'help'     => $enabled,
        ],
        [
            'label'    => $t['env_pdo_driver'],
            'ok'       => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
            'required' => true,
            'help'     => 'pdo_sqlite / pdo_mysql',
        ],
        [
            'label'    => $t['env_json'],
            'ok'       => extension_loaded('json'),
            'required' => true,
            'help'     => extension_loaded('json') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用'),
        ],
        [
            'label'    => $t['env_mbstring'],
            'ok'       => extension_loaded('mbstring'),
            'required' => true,
            'help'     => extension_loaded('mbstring') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用'),
        ],
        [
            'label'    => $t['env_session'],
            'ok'       => extension_loaded('session'),
            'required' => true,
            'help'     => extension_loaded('session') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用'),
        ],
        [
            'label'    => $t['env_openssl'],
            'ok'       => extension_loaded('openssl'),
            'required' => true,
            'help'     => extension_loaded('openssl') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用'),
        ],
        [
            'label'    => $t['env_fileinfo'],
            'ok'       => extension_loaded('fileinfo'),
            'required' => true,
            'help'     => extension_loaded('fileinfo') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用'),
        ],
        [
            'label'    => $t['env_gd'],
            'ok'       => extension_loaded('gd'),
            'required' => true,
            'help'     => extension_loaded('gd') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用'),
        ],
        [
            'label'    => $t['env_curl'],
            'ok'       => extension_loaded('curl'),
            'required' => true,
            'help'     => extension_loaded('curl') ? ($t === fp_install_i18n()['en'] ? 'Enabled' : '已启用') : ($t === fp_install_i18n()['en'] ? 'Disabled' : '未启用'),
        ],
        [
            'label'    => $t['env_pdo_sqlite'],
            'ok'       => extension_loaded('pdo_sqlite'),
            'required' => false,
            'help'     => extension_loaded('pdo_sqlite') ? ($t === fp_install_i18n()['en'] ? 'SQLite available' : '可用 SQLite 安装') : ($t === fp_install_i18n()['en'] ? 'Required if using SQLite' : '如使用 SQLite 需启用'),
        ],
        [
            'label'    => $t['env_pdo_mysql'],
            'ok'       => extension_loaded('pdo_mysql'),
            'required' => false,
            'help'     => extension_loaded('pdo_mysql') ? ($t === fp_install_i18n()['en'] ? 'MySQL/MariaDB available' : '可用 MySQL/MariaDB 安装') : ($t === fp_install_i18n()['en'] ? 'Required if using MySQL' : '如使用 MySQL 需启用'),
        ],
        [
            'label'    => $t['env_config_writable'],
            'ok'       => $configWritable,
            'required' => true,
            'help'     => FP_CONFIG_FILE,
        ],
        [
            'label'    => $t['env_storage_writable'],
            'ok'       => is_dir(FP_STORAGE_DIR . '/data') && is_writable(FP_STORAGE_DIR . '/data'),
            'required' => true,
            'help'     => FP_STORAGE_DIR . '/data',
        ],
        [
            'label'    => $t['env_logs_writable'],
            'ok'       => is_dir(FP_STORAGE_DIR . '/logs') && is_writable(FP_STORAGE_DIR . '/logs'),
            'required' => true,
            'help'     => FP_STORAGE_DIR . '/logs',
        ],
        [
            'label'    => $t['env_uploads_writable'],
            'ok'       => is_dir(FP_CONTENT_DIR . '/uploads') && is_writable(FP_CONTENT_DIR . '/uploads'),
            'required' => true,
            'help'     => FP_CONTENT_DIR . '/uploads',
        ],
    ];
}

/** @param list<array{label:string,ok:bool,required:bool,help:string}> $requirements @return list<string> */
function fp_install_requirement_errors(array $requirements): array
{
    $t = fp_install_t();
    $errors = [];
    foreach ($requirements as $item) {
        if ($item['required'] && !$item['ok']) {
            $prefix = $t === fp_install_i18n()['en'] ? 'Environment check failed: ' : '环境检查未通过：';
            $errors[] = $prefix . $item['label'];
        }
    }

    return $errors;
}

/** @return array<string, string> */
function fp_install_default_values(): array
{
    $lang = $_COOKIE['finch_install_lang'] ?? 'zh-cn';
    $languages = fp_install_languages();
    if (!isset($languages[$lang])) {
        $lang = 'zh-cn';
    }

    return [
        'lang'                   => $lang,
        'agree_license'          => '',
        'step'                   => '1',
        'site_name'              => '这是一个新的网站',
        'site_url'               => fp_install_guess_site_url(),
        'timezone'               => fp_install_server_timezone(),
        'driver'                 => 'mysql',
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
    $t = fp_install_t();
    $errors = [];

    if ($configState['exists']) {
        $errors[] = $t['error_config_exists'];
    }

    if ($configState['locked']) {
        $errors[] = $t['error_install_locked'];
    }

    if ($values['site_name'] === '') {
        $errors[] = $t['error_site_name_empty'];
    }

    if ($values['site_url'] !== '' && filter_var($values['site_url'], FILTER_VALIDATE_URL) === false) {
        $errors[] = $t['error_site_url_invalid'];
    }

    if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
        $errors[] = $t['error_timezone_invalid'];
    }

    if (!in_array($values['driver'], ['sqlite', 'mysql', 'mariadb'], true)) {
        $errors[] = $t['error_driver_invalid'];
    }

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $values['table_prefix'])) {
        $errors[] = $t['error_prefix_invalid'];
    }

    if ($values['driver'] === 'sqlite') {
        if (!extension_loaded('pdo_sqlite')) {
            $errors[] = $t['error_sqlite_no_ext'];
        }

        $sqlitePath = fp_install_sqlite_path($values['sqlite_database']);
        if ($sqlitePath === '' || $sqlitePath === ':memory:') {
            $errors[] = $t['error_sqlite_path_empty'];
        } else {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                $errors[] = sprintf($t['error_sqlite_dir_create'], $dir);
            } elseif (!is_writable($dir)) {
                $errors[] = sprintf($t['error_sqlite_dir_write'], $dir);
            } elseif (is_dir($sqlitePath)) {
                $errors[] = $t['error_sqlite_is_dir'];
            }
        }
    } else {
        if (!extension_loaded('pdo_mysql')) {
            $errors[] = $t['error_mysql_no_ext'];
        }
        if ($values['mysql_host'] === '') {
            $errors[] = $t['error_mysql_host_empty'];
        }
        if ((int) $values['mysql_port'] <= 0) {
            $errors[] = $t['error_mysql_port_invalid'];
        }
        if ($values['mysql_database'] === '') {
            $errors[] = $t['error_mysql_db_empty'];
        }
        if ($values['mysql_username'] === '') {
            $errors[] = $t['error_mysql_user_empty'];
        }
    }

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $values['admin_username'])) {
        $errors[] = $t['error_admin_username_invalid'];
    }

    if (filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = $t['error_admin_email_invalid'];
    }

    if (mb_strlen($values['admin_password']) < 8) {
        $errors[] = $t['error_admin_password_short'];
    }

    if ($values['admin_password'] !== $values['admin_password_confirm']) {
        $errors[] = $t['error_admin_password_mismatch'];
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

function fp_install_server_timezone(): string
{
    return date_default_timezone_get() ?: 'UTC';
}

/** @return list<array{value:string,label:string}> */
function fp_install_timezone_options(): array
{
    $current = fp_install_server_timezone();
    $options = [];
    $found = false;

    foreach (timezone_identifiers_list() as $tz) {
        $options[] = [
            'value' => $tz,
            'label' => str_replace('_', ' ', $tz),
        ];
        if ($tz === $current) {
            $found = true;
        }
    }

    // If the server timezone is not in the list (shouldn't happen), add it
    if (! $found && $current !== '') {
        array_unshift($options, [
            'value' => $current,
            'label' => $current . ' (current)',
        ]);
    }

    return $options;
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
    $step = (int) ($state['step'] ?? 1);
    $values = $state['values'];
    $errors = $state['errors'];
    $requirements = $state['requirements'];
    $t = $state['t'];
    $languages = fp_install_languages();
    $currentLang = $values['lang'] ?? 'zh-cn';

    ?><!DOCTYPE html>
<html lang="<?php echo fp_install_e($currentLang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo fp_install_e($t['title']); ?></title>
    <style>
        :root { color-scheme: light; --border: #d8dee4; --muted: #57606a; --bg: #f6f8fa; --ok: #1a7f37; --bad: #cf222e; --brand: #0969da; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #24292f; background: var(--bg); }
        main { width: min(960px, calc(100% - 32px)); margin: 32px auto; }
        .panel { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 24px; margin-bottom: 16px; }
        h1 { margin: 0 0 16px; font-size: 28px; }
        h2 { margin: 0 0 16px; font-size: 18px; }
        p { color: var(--muted); line-height: 1.6; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input, select, textarea { width: 100%; min-height: 40px; border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; font: inherit; }
        textarea { min-height: 200px; resize: vertical; }
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
        .steps { display: flex; gap: 16px; }
        .step { flex: 1; padding: 12px; text-align: center; background: #e7ebf0; border-radius: 6px; font-weight: 600; color: var(--muted); }
        .step.active { background: var(--brand); color: #fff; }
        .step.done { background: var(--ok); color: #fff; }
        .license-box { background: #f6f8fa; border: 1px solid var(--border); border-radius: 6px; padding: 16px; max-height: 300px; overflow-y: auto; margin-bottom: 16px; font-size: 14px; line-height: 1.6; }
        .license-box ol { padding-left: 20px; }
        .license-box li { margin-bottom: 8px; }
        .lang-switch { text-align: right; margin-bottom: 16px; }
        .lang-switch a { margin-left: 12px; padding: 6px 12px; border: 1px solid var(--border); border-radius: 4px; text-decoration: none; color: #24292f; }
        .lang-switch a.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        @media (max-width: 720px) { .grid, .checks { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <div class="lang-switch">
        <?php foreach ($languages as $code => $name) : ?>
            <a href="?step=<?php echo $step; ?>&lang=<?php echo $code; ?>" class="<?php echo $code === $currentLang ? 'active' : ''; ?>"><?php echo fp_install_e($name); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($view !== 'form') : ?>
        <section class="panel">
            <h1><?php echo fp_install_e($t['title']); ?></h1>
        </section>
    <?php else : ?>
        <section class="panel">
            <h1><?php echo fp_install_e($t['title']); ?></h1>
            <div class="steps">
                <div class="step <?php echo $step === 1 ? 'active' : ($step > 1 ? 'done' : ''); ?>"><?php echo fp_install_e($t['step']); ?> 1: <?php echo fp_install_e($t['step1_title']); ?></div>
                <div class="step <?php echo $step === 2 ? 'active' : ($step > 2 ? 'done' : ''); ?>"><?php echo fp_install_e($t['step']); ?> 2: <?php echo fp_install_e($t['step2_title']); ?></div>
                <div class="step <?php echo $step === 3 ? 'active' : ($step > 3 ? 'done' : ''); ?>"><?php echo fp_install_e($t['step']); ?> 3: <?php echo fp_install_e($t['step3_title']); ?></div>
                <div class="step <?php echo $step === 4 ? 'active' : ''; ?>"><?php echo fp_install_e($t['step']); ?> 4: <?php echo fp_install_e($t['step4_title']); ?></div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($view === 'installed') : ?>
        <section class="panel">
            <h2><?php echo fp_install_e($t['installed_title']); ?></h2>
            <p><?php echo fp_install_e($t['installed_desc']); ?></p>
            <div class="actions">
                <a class="button" href="../admin"><?php echo fp_install_e($t['enter_admin']); ?></a>
                <a class="button secondary" href="../"><?php echo fp_install_e($t['visit_home']); ?></a>
            </div>
        </section>
    <?php elseif ($view === 'locked') : ?>
        <section class="panel">
            <h2><?php echo fp_install_e($t['locked_title']); ?></h2>
            <p><?php echo fp_install_e($t['locked_desc']); ?></p>
        </section>
    <?php elseif ($view === 'success') : ?>
        <?php $siteUrl = rtrim((string) ($values['site_url'] ?? ''), '/'); ?>
        <section class="panel">
            <h2><?php echo fp_install_e($t['success_title']); ?></h2>
            <p><?php echo sprintf(fp_install_e($t['success_desc']), count((array) ($state['applied'] ?? []))); ?></p>
            <div style="background: #f6f8fa; border: 1px solid var(--border); border-radius: 6px; padding: 16px; margin: 16px 0;">
                <p style="margin: 0 0 8px;"><strong><?php echo fp_install_e($t['success_home_url_label']); ?>：</strong><br><a href="<?php echo fp_install_e($siteUrl ?: '../'); ?>"><?php echo fp_install_e($siteUrl ?: '../'); ?></a></p>
                <p style="margin: 0;"><strong><?php echo fp_install_e($t['success_admin_url_label']); ?>：</strong><br><a href="<?php echo fp_install_e($siteUrl ? $siteUrl . '/admin/login' : '../admin/login'); ?>"><?php echo fp_install_e($siteUrl ? $siteUrl . '/admin/login' : '../admin/login'); ?></a></p>
            </div>
            <div class="actions">
                <a class="button" href="<?php echo fp_install_e($siteUrl ? $siteUrl . '/admin/login' : '../admin/login'); ?>"><?php echo fp_install_e($t['login_admin']); ?></a>
                <a class="button secondary" href="<?php echo fp_install_e($siteUrl ?: '../'); ?>"><?php echo fp_install_e($t['visit_home']); ?></a>
            </div>
        </section>
    <?php elseif ($step === 1) : ?>
        <?php if ($errors !== []) : ?>
            <section class="panel errors">
                <h2><?php echo fp_install_e($t['errors_title']); ?></h2>
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo fp_install_e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <form method="post" class="panel" autocomplete="off">
            <input type="hidden" name="step" value="1">
            <input type="hidden" name="lang" value="<?php echo fp_install_e($currentLang); ?>">

            <h2><?php echo fp_install_e($t['welcome_heading']); ?></h2>
            <p style="color: #24292f; font-size: 15px;"><?php echo fp_install_e($t['welcome_desc']); ?></p>
            <p><?php echo fp_install_e($t['step1_desc']); ?></p>

            <div class="grid">
                <div class="full">
                    <label for="lang"><?php echo fp_install_e($t['language']); ?></label>
                    <select id="lang" name="lang" onchange="window.location.href='?step=1&lang='+this.value">
                        <?php foreach ($languages as $code => $name) : ?>
                            <option value="<?php echo fp_install_e($code); ?>" <?php echo $code === $currentLang ? 'selected' : ''; ?>><?php echo fp_install_e($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h2 style="margin-top:24px"><?php echo fp_install_e($t['license_title']); ?></h2>
            <div class="license-box">
                <?php echo $t['license_content']; ?>
            </div>

            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="agree_license" value="1" style="width: auto;" <?php echo !empty($values['agree_license']) ? 'checked' : ''; ?>>
                <?php echo fp_install_e($t['license_agree']); ?>
            </label>

            <div class="actions">
                <button type="submit"><?php echo fp_install_e($t['next']); ?></button>
            </div>
        </form>
    <?php elseif ($step === 2) : ?>
        <?php fp_install_render_requirements($requirements, $t); ?>

        <?php $hasErrors = fp_install_requirement_errors($requirements) !== []; ?>

        <form method="post" class="panel" autocomplete="off">
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="lang" value="<?php echo fp_install_e($currentLang); ?>">

            <?php if ($hasErrors) : ?>
                <p style="color: var(--bad); font-weight: 600;"><?php echo fp_install_e($t['check_blocked']); ?></p>
            <?php else : ?>
                <p style="color: var(--ok); font-weight: 600;"><?php echo fp_install_e($t['check_continue']); ?></p>
            <?php endif; ?>

            <div class="actions">
                <a class="button secondary" href="?step=1&lang=<?php echo fp_install_e($currentLang); ?>"><?php echo fp_install_e($t['prev']); ?></a>
                <?php if (!$hasErrors) : ?>
                    <button type="submit"><?php echo fp_install_e($t['next']); ?></button>
                <?php endif; ?>
            </div>
        </form>
    <?php elseif ($step === 3) : ?>
        <?php if ($errors !== []) : ?>
            <section class="panel errors">
                <h2><?php echo fp_install_e($t['errors_title']); ?></h2>
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo fp_install_e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <form method="post" class="panel" autocomplete="off">
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="lang" value="<?php echo fp_install_e($currentLang); ?>">
            <input type="hidden" name="agree_license" value="1">

            <h2><?php echo fp_install_e($t['step3_title']); ?></h2>
            <p><?php echo fp_install_e($t['step3_desc']); ?></p>

            <h2 style="margin-top:24px"><?php echo fp_install_e($t['site_info']); ?></h2>
            <div class="grid">
                <div>
                    <label for="site_name"><?php echo fp_install_e($t['site_name']); ?></label>
                    <input id="site_name" name="site_name" value="<?php echo fp_install_e($values['site_name']); ?>" required>
                </div>
                <div>
                    <label for="site_url"><?php echo fp_install_e($t['site_url']); ?></label>
                    <input id="site_url" name="site_url" value="<?php echo fp_install_e($values['site_url']); ?>" placeholder="https://example.com">
                </div>
                <div class="full">
                    <label for="timezone"><?php echo fp_install_e($t['timezone']); ?></label>
                    <select id="timezone" name="timezone" required>
                        <?php foreach (fp_install_timezone_options() as $tz) : ?>
                            <option value="<?php echo fp_install_e($tz['value']); ?>" <?php echo $tz['value'] === ($values['timezone'] ?? '') ? 'selected' : ''; ?>><?php echo fp_install_e($tz['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h2 style="margin-top:24px"><?php echo fp_install_e($t['database']); ?></h2>
            <div class="grid">
                <div>
                    <label for="driver"><?php echo fp_install_e($t['driver']); ?></label>
                    <select id="driver" name="driver" onchange="fpToggleDbFields()">
                        <option value="sqlite" <?php echo $values['driver'] === 'sqlite' ? 'selected' : ''; ?>>SQLite</option>
                        <option value="mysql" <?php echo in_array($values['driver'], ['mysql', 'mariadb'], true) ? 'selected' : ''; ?>>MySQL / MariaDB</option>
                    </select>
                </div>
                <div>
                    <label for="table_prefix"><?php echo fp_install_e($t['table_prefix']); ?></label>
                    <input id="table_prefix" name="table_prefix" value="<?php echo fp_install_e($values['table_prefix']); ?>" required>
                </div>
            </div>

            <div class="grid db-fields" id="db-sqlite" style="<?php echo $values['driver'] === 'sqlite' ? '' : 'display:none;'; ?>">
                <div class="full">
                    <label for="sqlite_database"><?php echo fp_install_e($t['sqlite_path']); ?></label>
                    <input id="sqlite_database" name="sqlite_database" value="<?php echo fp_install_e($values['sqlite_database']); ?>">
                    <span class="hint"><?php echo fp_install_e($t['sqlite_hint']); ?></span>
                </div>
            </div>

            <div class="grid db-fields" id="db-mysql" style="<?php echo $values['driver'] === 'sqlite' ? 'display:none;' : ''; ?>">
                <div>
                    <label for="mysql_host"><?php echo fp_install_e($t['mysql_host']); ?></label>
                    <input id="mysql_host" name="mysql_host" value="<?php echo fp_install_e($values['mysql_host']); ?>">
                </div>
                <div>
                    <label for="mysql_port"><?php echo fp_install_e($t['mysql_port']); ?></label>
                    <input id="mysql_port" name="mysql_port" value="<?php echo fp_install_e($values['mysql_port']); ?>" inputmode="numeric">
                </div>
                <div>
                    <label for="mysql_database"><?php echo fp_install_e($t['mysql_database']); ?></label>
                    <input id="mysql_database" name="mysql_database" value="<?php echo fp_install_e($values['mysql_database']); ?>">
                </div>
                <div>
                    <label for="mysql_username"><?php echo fp_install_e($t['mysql_username']); ?></label>
                    <input id="mysql_username" name="mysql_username" value="<?php echo fp_install_e($values['mysql_username']); ?>">
                </div>
                <div class="full">
                    <label for="mysql_password"><?php echo fp_install_e($t['mysql_password']); ?></label>
                    <input id="mysql_password" type="password" name="mysql_password" value="<?php echo fp_install_e($values['mysql_password']); ?>">
                </div>
            </div>

            <h2 style="margin-top:24px"><?php echo fp_install_e($t['admin_info']); ?></h2>
            <div class="grid">
                <div>
                    <label for="admin_username"><?php echo fp_install_e($t['admin_username']); ?></label>
                    <input id="admin_username" name="admin_username" value="<?php echo fp_install_e($values['admin_username']); ?>" required>
                </div>
                <div>
                    <label for="admin_email"><?php echo fp_install_e($t['admin_email']); ?></label>
                    <input id="admin_email" type="email" name="admin_email" value="<?php echo fp_install_e($values['admin_email']); ?>" required>
                </div>
                <div>
                    <label for="admin_password"><?php echo fp_install_e($t['admin_password']); ?></label>
                    <input id="admin_password" type="password" name="admin_password" required>
                </div>
                <div>
                    <label for="admin_password_confirm"><?php echo fp_install_e($t['admin_password_confirm']); ?></label>
                    <input id="admin_password_confirm" type="password" name="admin_password_confirm" required>
                </div>
            </div>

            <div class="actions">
                <a class="button secondary" href="?step=2&lang=<?php echo fp_install_e($currentLang); ?>"><?php echo fp_install_e($t['prev']); ?></a>
                <button type="submit"><?php echo fp_install_e($t['submit']); ?></button>
            </div>
        </form>
    <?php endif; ?>
</main>
<script>
function fpToggleDbFields() {
    const driver = document.getElementById('driver').value;
    const sqliteEl = document.getElementById('db-sqlite');
    const mysqlEl = document.getElementById('db-mysql');
    if (driver === 'sqlite') {
        sqliteEl.style.display = '';
        mysqlEl.style.display = 'none';
    } else {
        sqliteEl.style.display = 'none';
        mysqlEl.style.display = '';
    }
}
</script>
</body>
</html><?php
}

/** @param list<array{label:string,ok:bool,required:bool,help:string}> $requirements @param array<string, string> $t */
function fp_install_render_requirements(array $requirements, array $t): void
{
    ?>
    <section class="panel">
        <h2><?php echo fp_install_e($t['step2_title']); ?></h2>
        <p><?php echo fp_install_e($t['step2_desc']); ?></p>
        <ul class="checks">
            <?php foreach ($requirements as $item) : ?>
                <li>
                    <span class="<?php echo $item['ok'] ? 'ok' : 'bad'; ?>"><?php echo $item['ok'] ? fp_install_e($t['check_pass']) : fp_install_e($t['check_fail']); ?></span>
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
