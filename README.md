# FinchPHP

> 零依赖、纯原生 PHP 构建的轻量级内容管理系统。

FinchPHP 是一套面向现代 PHP 运行时的 CMS 框架，不依赖 Composer、不依赖 Node.js，开箱即用。它继承了经典 CMS 的成熟设计，同时全面拥抱 PHP 8 新特性，用原生代码实现了一套完整的内容管理、插件扩展、主题定制和 REST API 能力。

---

## 🎯 设计理念

FinchPHP 不照搬某一个系统，而是分别吸收它们的优点，避开旧式 CMS 和传统框架的常见问题。

| 参考对象 | 值得借鉴 | 需要避免 | FinchPHP 取舍 |
|---------|---------|---------|--------------|
| **Z-Blog** | 轻量、部署简单、插件钩子直接、三级路由清晰、侧栏模块实用 | PHP 历史包袱重、全局状态偏多、接口现代化不足、权限模型较粗 | 保留轻量部署、钩子、中断信号、三级路由和模块思想；核心代码改为更清晰的类结构与中间件/权限能力模型 |
| **WordPress** | 原生 PHP 模板生态成熟、函数标签易懂、主题/插件生态清晰、后台操作路径熟悉 | 历史包袱重、全局函数过多、`term`/`meta`/`option` 容易被滥用、分类标签合表会拖累性能 | 借鉴主题/插件/模板标签体验；分类与标签拆表，限制 meta/option 滥用，核心能力用类和服务收束 |
| **ThinkPHP** | 中文开发者熟悉、MVC 思路明确、路由和数据库封装完整、上手快 | 更偏通用框架而非 CMS，目录和依赖对普通虚拟主机不够友好，CMS 的主题/插件/安装升级需要另行设计 | 借鉴路由、请求、验证、数据库封装思路；保持 CMS 根目录部署友好，不要求 Composer 运行时依赖 |
| **Laravel** | 现代工程化清晰，中间件、验证、迁移、API Resource、服务提供者、权限思路成熟 | 依赖 Composer 生态和较高运行环境，不适合轻量虚拟主机；完整框架体量偏大 | 借鉴中间件、验证、迁移、API、模型关联、服务提供者理念；用原生 PHP 8.0+ 做轻量实现 |

---

## ✨ 项目特点

### 零依赖，开箱即用

- **零 Composer 依赖**：整个运行时不依赖任何第三方 Composer 包，部署只需 PHP 环境。
- **零前端构建**：后台样式和脚本均为手写原生 CSS/JS，不需要 Node.js、npm、Webpack 或 Vite。
- **内置安装向导**：浏览器访问即可引导完成数据库配置、管理员创建和初始化。

### 现代 PHP 技术栈

- 基于 **PHP 8.0+** 构建，全面使用构造器提升、联合类型、`match`、箭头函数、`readonly` 属性等现代语法。
- 所有核心文件均声明 `declare(strict_types=1)`，保证类型安全。
- 自定义 PSR-4 自动加载，`Finch\` 命名空间直接映射到 `system/` 目录。

### 三层路由系统

保留经典 CMS 的三级路由模式，灵活适配不同服务器环境：

| 路由类型 | 说明 | 示例 |
|---------|------|------|
| Active | 动态查询参数路由 | `?fp=post&id=1` |
| Rewrite | 伪静态路由 | `/post/hello-world` |
| Default | 兜底路由 | 默认首页 |

### 插件 / 主题 / 模块 扩展体系

```
content/
├── plugins/    # 插件（钩子驱动，运行时热加载）
├── themes/     # 主题（模板 + 静态资源）
└── modules/    # 官方模块（可独立启停）
```

- **插件系统**：基于事件驱动的 Hook 机制，插件通过 `include.php` 注册钩子回调，支持优先级排序和错误隔离。
- **主题系统**：原生 PHP 模板，支持 `header/footer/sidebar/index/single/page` 等模板文件，主题资源通过 Asset 服务统一管理。
- **模块系统**：独立的官方扩展单元，支持运行时启停和配置持久化。

### 内置 Provider 门面

系统通过 Provider 门面统一对接可选服务，内置插件即可承担具体实现：

| 服务 | 门面 | 默认 Provider |
|------|------|--------------|
| 邮件发送 | `MailService` | `notify-email` |
| 短信验证 | `SmsService` | `notify-sms` |
| 图形验证码 | `CaptchaService` | `captcha-gd` |
| 站内搜索 | `SearchService` | `search-like` |
| AI 补全 | `AiService` | `ai-openai` |
| 社交登录 | `SocialLoginService` | `social-github` |
| 后台样式 | — | `tabler` |
| 富文本编辑器 | — | `quill` |

所有 Provider 均支持通过插件注册覆盖，缺失时优雅降级，不会导致系统崩溃。

### REST API 与 Token 认证

- 完整的 REST API v1，覆盖文章、分类、标签、评论、上传、用户、系统信息。
- 基于 Bearer Token 认证，支持能力（abilities）细粒度授权，如 `posts:read`、`uploads:create`。
- API 响应统一封装为 `{code, message, data, meta}` 格式。
- 内置 CORS 中间件，支持预检请求。

### 安全特性

- 默认安全响应头：`X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy`、`Permissions-Policy`、`Content-Security-Policy`。
- HTTPS 环境下自动启用 HSTS。
- 路由级缓存策略：API / 后台 / 认证 / 写入请求自动 `no-store`。
- CSRF 防护覆盖所有后台 POST 操作。
- 文件上传白名单校验：扩展名 + MIME 类型双重验证。
- 上传目录执行阻断（Apache `.htaccess` / Nginx 配置示例）。

### 分组缓存与失效

- 内置数据库缓存服务，支持 `remember` 语义和分组失效。
- 文章、分类、标签、评论、设置、主题、模块等写入操作自动刷新对应缓存分组。
- 搜索查询自带 180 秒缓存。

### 多数据库支持

- **MySQL / MariaDB**：一等公民支持。
- **SQLite**：完整支持，适合轻量部署和开发调试。
- 统一的数据库抽象层（Driver / Query / Schema），业务代码无需感知底层差异。

---

## 🏆 项目优势

1. **部署极简**：只需 PHP 8.0+ 和 PDO 扩展，无需 Composer、无需 Node.js、无需构建步骤。
2. **学习曲线低**：原生 PHP 模板标签，WordPress 风格主题开发，无需学习模板编译语法。
3. **扩展能力强**：插件钩子 + Provider 门面 + 主题模板，三层扩展体系覆盖绝大多数定制需求。
4. **运行时安全**：默认安全策略、分组缓存失效、上传阻断，安全不是可选项而是默认行为。
5. **API 就绪**：开箱即用的 REST API 和 Token 认证，方便对接小程序、移动端和第三方集成。
6. **可观测性**：分类日志（error / security / api / install / ai）、AI 调用日志表、缓存命中统计。

---

## 📁 目录结构

```
finchphp/
├── index.php                  # 应用入口
├── config.php                 # 安装后生成的配置文件（运行时生成）
│
├── system/                    # 系统核心（Finch\ 命名空间）
│   ├── bootstrap.php          # 启动引导：常量定义、自动加载、临时时区
│   ├── App.php                # 全局单例 $fp：服务容器与启动流程
│   │
│   ├── Core/                  # 核心基础设施
│   │   ├── Asset.php          # 静态资源 URL 生成（system / theme / plugin）
│   │   ├── Cache.php          # 数据库缓存服务（remember / 分组失效）
│   │   ├── Config.php         # 安装级配置加载
│   │   ├── Database.php       # 数据库连接管理
│   │   ├── ErrorHandler.php   # 统一错误/异常捕获
│   │   ├── Gate.php           # 能力注册与权限检查
│   │   ├── Hook.php           # 钩子管理器（add / do / filter）
│   │   ├── Installer.php      # 安装逻辑（迁移 / 种子 / 管理员创建）
│   │   ├── Logger.php         # 分类日志（error / security / api / install / ai）
│   │   ├── Middleware.php      # 中间件管道
│   │   ├── Request.php        # 请求封装
│   │   ├── Response.php       # 响应封装（含默认安全头）
│   │   ├── Router.php         # 三级路由器（active / rewrite / default）
│   │   ├── Session.php        # 会话管理
│   │   ├── Settings.php       # 运行期设置（只读代理）
│   │   ├── Template.php       # 模板加载器（主题优先 → 系统兜底）
│   │   ├── TemplateContext.php # 模板上下文栈
│   │   ├── Thumbnail.php      # 图片缩略图生成（GD）
│   │   └── Validation.php     # 验证器入口
│   │
│   ├── Database/              # 数据库层
│   │   ├── Driver.php         # 驱动接口
│   │   ├── Mysql.php          # MySQL PDO 驱动
│   │   ├── Sqlite.php         # SQLite PDO 驱动
│   │   ├── Query.php          # SQL 查询构建器
│   │   ├── Migrator.php       # 数据库迁移执行器
│   │   ├── Migration.php      # 迁移基类
│   │   ├── Schema/            # Schema 构建器（Blueprint / Builder / Column）
│   │   └── Migrations/        # 内置迁移文件
│   │
│   ├── Model/                 # 数据模型
│   │   ├── BaseModel.php      # 模型基类（查询构建 / 软删除 / CRUD）
│   │   ├── Post.php           # 文章（软删除）
│   │   ├── Category.php       # 分类
│   │   ├── Tag.php            # 标签
│   │   ├── Comment.php        # 评论（软删除）
│   │   ├── User.php           # 用户
│   │   ├── Role.php           # 角色
│   │   ├── Upload.php         # 上传记录
│   │   ├── ApiToken.php       # API Token
│   │   ├── SystemSetting.php  # 系统设置
│   │   ├── ThemeSetting.php   # 主题设置
│   │   ├── PluginSetting.php  # 插件设置
│   │   ├── ModuleSetting.php  # 模块设置
│   │   ├── Module.php         # 模块
│   │   ├── Migration.php      # 迁移记录
│   │   ├── AiModel.php        # AI 模型配置
│   │   ├── AiLog.php          # AI 调用日志
│   │   ├── SmsCode.php        # 短信验证码
│   │   └── UserSocialAccount.php # 社交账号绑定
│   │
│   ├── Service/               # 业务服务层
│   │   ├── AuthService.php    # 认证（登录 / 登出 / 会话恢复）
│   │   ├── PostService.php    # 文章查询（前台只读）
│   │   ├── PostManageService.php # 文章管理（后台写入）
│   │   ├── TaxonomyService.php   # 分类/标签查询
│   │   ├── TaxonomyManageService.php # 分类/标签管理
│   │   ├── CommentService.php    # 评论查询与提交
│   │   ├── CommentManageService.php # 评论审核
│   │   ├── SettingService.php    # 系统设置写入
│   │   ├── UploadService.php     # 上传流水线
│   │   ├── ApiTokenService.php   # Token 签发与撤销
│   │   ├── ThemeService.php      # 主题发现与切换
│   │   ├── PluginService.php     # 插件发现、启停与运行时加载
│   │   ├── ModuleService.php     # 模块发现与启停
│   │   ├── SearchService.php     # 搜索门面
│   │   ├── MailService.php       # 邮件门面
│   │   ├── SmsService.php        # 短信门面
│   │   ├── CaptchaService.php    # 验证码门面
│   │   ├── AiService.php         # AI 门面
│   │   ├── SocialLoginService.php # 社交登录门面
│   │   └── ProviderRuntime.php   # Provider 插件运行时桥接
│   │
│   ├── Controller/            # 前台控制器
│   │   ├── BaseController.php # 控制器基类
│   │   ├── HomeController.php # 首页
│   │   ├── PostController.php # 文章详情
│   │   ├── PageController.php # 页面详情
│   │   ├── CategoryController.php # 分类列表
│   │   ├── TagController.php  # 标签列表
│   │   └── SearchController.php # 搜索结果
│   │
│   ├── Admin/                 # 后台管理控制器
│   │   ├── AdminLayout.php    # 后台布局 Trait
│   │   ├── AuthAdmin.php      # 登录/登出
│   │   ├── DashboardAdmin.php # 仪表盘
│   │   ├── PostAdmin.php      # 文章管理
│   │   ├── PageAdmin.php      # 页面管理
│   │   ├── CategoryAdmin.php  # 分类管理
│   │   ├── TagAdmin.php       # 标签管理
│   │   ├── CommentAdmin.php   # 评论管理
│   │   ├── UploadAdmin.php    # 媒体管理
│   │   ├── UserAdmin.php      # 用户管理
│   │   ├── TokenAdmin.php     # API Token 管理
│   │   ├── SettingAdmin.php   # 系统设置
│   │   └── ExtensionAdmin.php # 扩展管理（主题/插件/模块）
│   │
│   ├── Api/V1/                # REST API v1
│   │   ├── ApiResource.php    # 统一响应封装
│   │   ├── SystemApi.php      # 系统信息
│   │   ├── PostsApi.php       # 文章
│   │   ├── CategoriesApi.php  # 分类
│   │   ├── TagsApi.php        # 标签
│   │   ├── CommentsApi.php    # 评论
│   │   ├── UploadsApi.php     # 上传
│   │   └── UserApi.php        # 用户
│   │
│   ├── Middleware/            # 中间件
│   │   ├── AuthMiddleware.php       # 认证
│   │   ├── RoleMiddleware.php       # 角色
│   │   ├── CsrfMiddleware.php       # CSRF
│   │   ├── CorsMiddleware.php       # CORS
│   │   ├── ApiAuthMiddleware.php    # API Token 认证
│   │   └── ApiAbilityMiddleware.php # API 能力检查
│   │
│   ├── Validation/            # 输入验证
│   │   └── Validator.php      # 验证器（required / max / email / unique / regex ...）
│   │
│   ├── Helper/                # 辅助函数
│   │   └── TemplateTags.php   # 模板标签（title / content / permalink / pagination ...）
│   │
│   ├── Theme/                 # 系统兜底主题模板
│   ├── Languages/             # 语言包（zh-cn / zh-tw / en）
│   ├── assets/                # 后台静态资源（CSS / IMG）
│   └── script/                # 后台脚本（JS）
│
├── content/                   # 用户内容目录
│   ├── plugins/               # 插件目录
│   │   ├── tabler/            # 后台样式 Provider
│   │   ├── quill/             # 富文本编辑器 Provider
│   │   ├── search-like/       # 搜索 Provider（LIKE）
│   │   ├── notify-email/      # 邮件 Provider
│   │   ├── notify-sms/        # 短信 Provider
│   │   ├── captcha-gd/        # 验证码 Provider（GD）
│   │   ├── social-github/     # GitHub 社交登录 Provider
│   │   └── ai-openai/         # OpenAI 兼容 AI Provider
│   ├── themes/                # 主题目录
│   │   └── default/           # 默认主题
│   │       ├── template/      # 模板文件
│   │       ├── assets/        # 主题静态资源
│   │       └── theme.json     # 主题元数据
│   ├── modules/               # 官方模块目录
│   │   └── blog/              # 博客模块
│   └── uploads/               # 上传文件目录
│
├── storage/                   # 运行时数据目录
│   ├── cache/                 # 缓存
│   ├── data/                  # 数据库文件（SQLite）
│   └── logs/                  # 日志文件
│
├── install/                   # 安装与部署
│   ├── index.php              # 安装向导
│   ├── stage20_verify.sh      # 端到端验证脚本
│   ├── DEPLOY.md              # 部署文档
│   ├── API.md                 # API 文档
│   └── nginx-uploads.conf.example # Nginx 上传安全配置示例
│
└── scripts/                   # 开发工具脚本
    ├── auto-sync-daemon.sh    # 自动同步守护进程
    ├── auto-sync-start.sh     # 启动自动同步
    ├── auto-sync-stop.sh      # 停止自动同步
    └── auto-sync-status.sh    # 查看同步状态
```

---

## 🚀 快速开始

### 环境要求

- PHP 8.0+（推荐 8.4+）
- PDO 扩展 + 至少一个驱动（`pdo_mysql` 或 `pdo_sqlite`）
- 其他必需扩展：`json`、`mbstring`、`session`、`openssl`、`fileinfo`、`gd`、`curl`

### 安装步骤

1. 将项目文件上传至 Web 服务器根目录。
2. 确保 `content/` 和 `storage/` 目录可写。
3. 浏览器访问站点根目录，自动跳转安装向导 `/install/`。
4. 按向导完成数据库配置和管理员创建。
5. 访问 `/admin/login` 进入后台。

### 命令行快速启动（开发调试）

```bash
# 使用 PHP 内置服务器（含路由脚本）
php -S 127.0.0.1:8080 router.php

# 浏览器访问
open http://127.0.0.1:8080/install/
```

---

## ⚙️ 服务器伪静态配置

FinchPHP 使用 Rewrite 路由（如 `/post/hello-world`、`/category/tech`），需要服务器将非静态文件请求转发到 `index.php`。

### Nginx / 宝塔面板

在网站伪静态规则中添加：

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

> **宝塔操作**：网站设置 → 伪静态 → 粘贴上述规则 → 保存

### Apache

项目根目录已内置 `.htaccess`，无需额外配置。确保已启用 `mod_rewrite`（`a2enmod rewrite`）。

### PHP 内置服务器（开发调试）

```bash
php -S 127.0.0.1:8080 router.php
```

### Caddy

```
example.com {
    root * /var/www/finchphp
    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
}
```

<details>
<summary>🔧 安全加固（推荐）</summary>

在伪静态规则**之前**添加以下 Nginx 配置，防止敏感目录被直接访问：

```nginx
# 上传目录禁止执行 PHP
location ~* ^/content/uploads/ {
    location ~* \.(php|phtml|php7|phar|inc)$ { deny all; }
    autoindex off;
}

# 禁止访问敏感目录
location ~* ^/(system|storage)/ { deny all; }

# 禁止访问隐藏文件
location ~* /\.(?!well-known) { deny all; }
```

Apache 的安全规则已包含在项目内置 `.htaccess` 中，无需额外操作。
</details>

---

## 🔌 插件开发

在 `content/plugins/` 下创建插件目录，包含 `plugin.json` 和 `include.php`：

```
content/plugins/my-plugin/
├── plugin.json    # 插件元数据
└── include.php    # 运行时入口（注册钩子回调）
```

`include.php` 示例：

```php
<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    // 注册动作钩子
    $app->hooks->add('fp_init', static function () use ($app): void {
        // 系统初始化后执行
    });

    // 注册过滤器钩子
    $app->hooks->add('fp_provider_search_my_search_query', static function (mixed $value, array $payload = []) {
        return [
            'data' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 10,
            'last_page' => 0,
            'provider' => 'my-search',
        ];
    });
};
```

`plugin.json` 示例：

```json
{
  "name": "My Plugin",
  "version": "1.0.0",
  "description": "A custom plugin",
  "author": "Your Name",
  "settings_page": "",
  "menu_location": "hidden",
  "preinstalled": false
}
```

---

## 🎨 主题开发

在 `content/themes/` 下创建主题目录：

```
content/themes/my-theme/
├── template/          # 模板文件
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   ├── index.php      # 首页/列表
│   ├── single.php     # 文章详情
│   └── page.php       # 独立页面
├── assets/            # 主题静态资源
│   └── css/
└── theme.json         # 主题元数据
```

可用模板标签：

| 标签 | 说明 |
|------|------|
| `site_name()` | 站点名称 |
| `site_subtitle()` | 站点副标题 |
| `title()` | 当前页面/文章标题 |
| `content()` | 文章内容 |
| `excerpt()` | 文章摘要 |
| `permalink()` | 文章链接 |
| `post_date()` | 发布日期 |
| `author()` | 作者名称 |
| `categories()` | 分类链接列表 |
| `tags()` | 标签链接列表 |
| `list_posts()` | 文章列表数据 |
| `pagination()` | 分页 HTML |
| `get_header()` | 加载 header 模板 |
| `get_footer()` | 加载 footer 模板 |
| `get_sidebar()` | 加载 sidebar 模板 |
| `head()` | 触发 `fp_head` 钩子 |
| `footer()` | 触发 `fp_footer` 钩子 |

---

## 📡 API 使用

### 认证

```bash
# Bearer Token
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://example.com/api/v1/posts
```

### 主要端点

| 方法 | 路径 | 说明 | 能力 |
|------|------|------|------|
| GET | `/api/v1/system` | 系统信息 | `system:read` |
| GET | `/api/v1/posts` | 文章列表 | `posts:read` |
| GET | `/api/v1/posts/{id}` | 文章详情 | `posts:read` |
| GET | `/api/v1/categories` | 分类列表 | `categories:read` |
| GET | `/api/v1/categories/{id}` | 分类详情 | `categories:read` |
| GET | `/api/v1/tags` | 标签列表 | `tags:read` |
| GET | `/api/v1/tags/{id}` | 标签详情 | `tags:read` |
| GET | `/api/v1/posts/{id}/comments` | 评论列表 | `comments:read` |
| POST | `/api/v1/posts/{id}/comments` | 提交评论 | `comments:create` |
| GET | `/api/v1/uploads` | 上传列表 | `uploads:read` |
| POST | `/api/v1/uploads` | 上传文件 | `uploads:create` |
| GET | `/api/v1/me` | 当前用户 | `users:read` |
| GET | `/api/v1/users` | 用户列表 | `users:read` |
| GET | `/api/v1/users/{id}` | 用户详情 | `users:read` |

---

## 📄 开源协议

本项目基于 [MIT License](LICENSE) 开源。
