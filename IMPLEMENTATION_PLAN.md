# Finch PHP CMS 实施规划文档

> 本文档为技术实施前的详细规划，不包含具体代码实现

---

## 一、数据库迁移文件规范

### 1.1 迁移文件命名与存放

**文件路径**：`/system/Database/migrations/`

**命名规则**：`{timestamp}_{snake_case_description}.php`
- 时间戳格式：`YYYYMMDDHHMMSS`（精确到秒）
- 示例：
  ```
  20260101100000_create_posts_table.php
  20260101100100_create_categories_table.php
  20260101100200_create_post_category_table.php
  20260101100300_create_tags_table.php
  20260101100400_create_post_tag_table.php
  20260101100500_create_users_table.php
  20260101100600_create_roles_table.php
  20260101100700_create_sessions_table.php
  20260101100800_create_csrf_tokens_table.php
  20260101100850_create_remember_tokens_table.php
  20260101100900_create_cache_table.php
  ```

**类命名规范**：
```php
class CreatePostsTable extends Migration
{
    // 迁移逻辑
}
```

### 1.2 数据库表结构详细定义

#### post 表（文章/页面/自定义内容类型）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | 主键 |
| author_id | BIGINT | INDEX, FOREIGN KEY | 作者ID，关联用户表 |
| primary_category_id | BIGINT | NULL, INDEX | 主分类ID，NULL表示未分类 |
| title | VARCHAR(255) | NOT NULL | 标题 |
| slug | VARCHAR(200) | UNIQUE | URL别名，用于SEO友好链接 |
| content | LONGTEXT | NOT NULL | 正文内容 |
| excerpt | VARCHAR(1000) | NULL | 摘要，可选 |
| type | VARCHAR(50) | DEFAULT 'post', INDEX | 内容类型：post/page/自定义类型 |
| status | VARCHAR(20) | DEFAULT 'draft', INDEX | 状态：draft/publish/pending/private/trash |
| previous_status | VARCHAR(20) | NULL | 软删除前的状态，用于恢复 |
| view_count | INT | DEFAULT 0 | 浏览次数 |
| created_at | TIMESTAMP | NOT NULL | 创建时间 |
| updated_at | TIMESTAMP | NOT NULL | 更新时间 |
| deleted_at | TIMESTAMP | NULL | 软删除时间，NULL表示未删除 |

**索引设计**：
```sql
CREATE INDEX idx_author_id ON post(author_id);
CREATE INDEX idx_primary_category_id ON post(primary_category_id);
CREATE UNIQUE INDEX idx_slug ON post(slug);
CREATE INDEX idx_type ON post(type);
CREATE INDEX idx_status ON post(status);
CREATE INDEX idx_created_at ON post(created_at DESC);
CREATE INDEX idx_deleted_at ON post(deleted_at);
```

#### category 表（分类）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | 主键 |
| parent_id | BIGINT | NULL, INDEX | 父分类ID，NULL表示顶级分类 |
| name | VARCHAR(100) | NOT NULL | 分类名称 |
| slug | VARCHAR(100) | UNIQUE | URL别名 |
| description | TEXT | NULL | 分类描述 |
| sort_order | INT | DEFAULT 0 | 排序权重，数字越小越靠前 |
| post_count | INT | DEFAULT 0 | 文章计数，由服务层维护 |
| status | VARCHAR(20) | DEFAULT 'active' | 状态：active/inactive |
| created_at | TIMESTAMP | NOT NULL | 创建时间 |
| updated_at | TIMESTAMP | NOT NULL | 更新时间 |

**索引设计**：
```sql
CREATE INDEX idx_parent_id ON category(parent_id);
CREATE UNIQUE INDEX idx_slug ON category(slug);
CREATE INDEX idx_sort_order ON category(sort_order);
CREATE INDEX idx_status ON category(status);
```

#### post_category 表（文章-分类多对多关系）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| post_id | BIGINT | NOT NULL, INDEX | 文章ID |
| category_id | BIGINT | NOT NULL, INDEX | 分类ID |

**主键与索引**：
```sql
PRIMARY KEY (post_id, category_id);
CREATE INDEX idx_post_id ON post_category(post_id);
CREATE INDEX idx_category_id ON post_category(category_id);
```

#### tag 表（标签）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | 主键 |
| name | VARCHAR(100) | NOT NULL, UNIQUE | 标签名称 |
| slug | VARCHAR(100) | UNIQUE | URL别名 |
| post_count | INT | DEFAULT 0 | 文章计数 |
| created_at | TIMESTAMP | NOT NULL | 创建时间 |
| updated_at | TIMESTAMP | NOT NULL | 更新时间 |

**索引设计**：
```sql
CREATE UNIQUE INDEX idx_name ON tag(name);
CREATE UNIQUE INDEX idx_slug ON tag(slug);
CREATE INDEX idx_post_count ON tag(post_count DESC);
```

#### post_tag 表（文章-标签多对多关系）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| post_id | BIGINT | NOT NULL, INDEX | 文章ID |
| tag_id | BIGINT | NOT NULL, INDEX | 标签ID |

**主键与索引**：
```sql
PRIMARY KEY (post_id, tag_id);
CREATE INDEX idx_post_id ON post_tag(post_id);
CREATE INDEX idx_tag_id ON post_tag(tag_id);
```

#### user 表（用户）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | 主键 |
| username | VARCHAR(50) | UNIQUE, NOT NULL | 用户名 |
| email | VARCHAR(100) | UNIQUE, NOT NULL | 邮箱 |
| password | VARCHAR(255) | NOT NULL | 密码哈希 |
| display_name | VARCHAR(100) | NULL | 显示名称 |
| avatar_url | VARCHAR(500) | NULL | 头像URL |
| role_id | BIGINT | NOT NULL, FOREIGN KEY | 角色ID |
| status | VARCHAR(20) | DEFAULT 'active' | 状态：active/inactive/banned/pending |
| is_admin | TINYINT(1) | DEFAULT 0 | 是否管理员 |
| last_login_at | TIMESTAMP | NULL | 最后登录时间 |
| last_login_ip | VARCHAR(45) | NULL | 最后登录IP |
| login_fail_count | INT | DEFAULT 0 | 连续登录失败次数 |
| locked_until | TIMESTAMP | NULL | 锁定截止时间 |
| password_reset_token | VARCHAR(100) | NULL | 密码重置令牌 |
| password_reset_expires | TIMESTAMP | NULL | 密码重置令牌过期时间 |
| created_at | TIMESTAMP | NOT NULL | 创建时间 |
| updated_at | TIMESTAMP | NOT NULL | 更新时间 |

**索引设计**：
```sql
CREATE UNIQUE INDEX idx_username ON user(username);
CREATE UNIQUE INDEX idx_email ON user(email);
CREATE INDEX idx_role_id ON user(role_id);
CREATE INDEX idx_status ON user(status);
CREATE INDEX idx_is_admin ON user(is_admin);
CREATE INDEX idx_last_login_at ON user(last_login_at);
CREATE INDEX idx_password_reset_token ON user(password_reset_token);
```

#### role 表（角色）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | 主键 |
| name | VARCHAR(50) | UNIQUE, NOT NULL | 角色名称 |
| slug | VARCHAR(50) | UNIQUE, NOT NULL | 角色标识符 |
| description | TEXT | NULL | 角色描述 |
| capabilities | JSON | NOT NULL | 权限列表，JSON数组 |
| is_system | TINYINT(1) | DEFAULT 0 | 是否系统内置角色（不可删除） |
| created_at | TIMESTAMP | NOT NULL | 创建时间 |
| updated_at | TIMESTAMP | NOT NULL | 更新时间 |

**索引设计**：
```sql
CREATE UNIQUE INDEX idx_name ON role(name);
CREATE UNIQUE INDEX idx_slug ON role(slug);
```

#### session 表（会话）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | VARCHAR(128) | PRIMARY KEY | 会话ID |
| user_id | BIGINT | NULL, INDEX | 用户ID，NULL表示访客 |
| ip_address | VARCHAR(45) | NOT NULL | IP地址 |
| user_agent | TEXT | NULL | 用户代理 |
| payload | MEDIUMTEXT | NOT NULL | 会话数据 |
| last_activity | TIMESTAMP | NOT NULL | 最后活动时间 |

**索引设计**：
```sql
CREATE INDEX idx_user_id ON session(user_id);
CREATE INDEX idx_last_activity ON session(last_activity);
```

#### csrf_token 表（CSRF令牌）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| token | VARCHAR(64) | PRIMARY KEY | CSRF令牌 |
| ip_address | VARCHAR(45) | NOT NULL | IP地址 |
| user_agent | TEXT | NULL | 用户代理 |
| expires_at | TIMESTAMP | NOT NULL | 过期时间 |

**索引设计**：
```sql
CREATE INDEX idx_ip_address ON csrf_token(ip_address);
CREATE INDEX idx_expires_at ON csrf_token(expires_at);
```

#### remember_token 表（"记住我"令牌，独立表支持多设备）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | BIGINT | PRIMARY KEY, AUTO_INCREMENT | 主键 |
| user_id | BIGINT | NOT NULL, INDEX, FOREIGN KEY | 用户ID |
| token_hash | VARCHAR(255) | NOT NULL, UNIQUE | 令牌哈希（SHA256，不明文存储） |
| user_agent_hash | VARCHAR(64) | NULL | UA 哈希（同设备续期/设备数限制） |
| ip | VARCHAR(45) | NULL | 颁发IP |
| expires_at | TIMESTAMP | NOT NULL | 过期时间 |
| last_used_at | TIMESTAMP | NULL | 最后使用时间 |
| created_at | TIMESTAMP | NOT NULL | 创建时间 |

**索引设计**：
```sql
CREATE UNIQUE INDEX idx_token_hash ON remember_token(token_hash);
CREATE INDEX idx_user_id ON remember_token(user_id);
CREATE INDEX idx_expires_at ON remember_token(expires_at);
```

> 单用户最多保留 5 个有效令牌，超出时由 AuthService 自动删除最早记录。

#### cache 表（缓存，可选，也可使用Redis）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| key | VARCHAR(255) | PRIMARY KEY | 缓存键 |
| value | MEDIUMTEXT | NOT NULL | 缓存值 |
| expired_at | TIMESTAMP | NOT NULL | 过期时间 |

**索引设计**：
```sql
CREATE INDEX idx_expired_at ON cache(expired_at);
```

## 二、项目目录结构树

```
finchphp/
├── public/                          # Web 服务器根目录
│   ├── index.php                    # 唯一入口文件，所有请求经此路由
│   ├── .htaccess                    # Apache URL 重写规则
│   ├── robots.txt                   # 搜索引擎爬虫规则
│   ├── favicon.ico                  # 网站图标
│   └── assets/                      # 静态资源目录（可配置 CDN 加速）
│       ├── css/                     # CSS 样式文件
│       │   ├── admin.css            # 后台样式（Tabler 编译后）
│       │   ├── quill.snow.css       # Quill 编辑器样式
│       │   └── front.css            # 前台主题公共样式
│       ├── js/                      # JavaScript 脚本文件
│       │   ├── jquery.min.js        # jQuery 库
│       │   ├── jquery.form.min.js   # jQuery Form 插件
│       │   ├── jquery.cookie.min.js # jQuery Cookie 插件
│       │   ├── quill.min.js         # Quill 编辑器
│       │   ├── tabler.min.js        # Tabler 后台交互
│       │   └── front.js             # 前台主题公共脚本
│       └── images/                  # 公共图片资源
│           ├── logo.png             # 网站 Logo
│           ├── default-avatar.png   # 默认头像
│           └── loading.gif          # 加载中动画
│
├── system/                          # 系统核心代码（版本控制，不可修改）
│   ├── Core/                        # 核心类（全局单例、容器、配置）
│   │   ├── App.php                  # 应用主类，全局单例，依赖注入容器
│   │   ├── Container.php            # IoC 容器，管理依赖注入
│   │   ├── Config.php               # 配置管理器，读取 config.php
│   │   ├── Event.php                # 事件管理器，钩子系统的底层实现
│   │   ├── Hook.php                 # 钩子系统，面向用户的 API
│   │   ├── Log.php                  # 日志记录器
│   │   └── Request.php              # HTTP 请求封装
│   │
│   ├── Database/                    # 数据库层
│   │   ├── Connection.php           # 数据库连接管理器（PDO 封装）
│   │   ├── QueryBuilder.php         # 查询构建器（链式调用）
│   │   ├── Model.php                # 模型基类
│   │   ├── Migration.php            # 迁移管理
│   │   └── migrations/              # 迁移文件目录
│   │       ├── 20260101000000_create_core_tables.php
│   │       ├── 20260101000100_create_user_tables.php
│   │       ├── 20260101000200_create_content_type_tables.php
│   │       └── 20260101000300_create_plugin_tables.php
│   │
│   ├── Model/                       # 数据模型层
│   │   ├── BaseModel.php            # 基础模型类（继承自 Database\Model）
│   │   ├── User.php                 # 用户模型
│   │   ├── Role.php                 # 角色模型
│   │   ├── Post.php                 # 文章模型
│   │   ├── Category.php             # 分类模型
│   │   ├── Tag.php                  # 标签模型
│   │   ├── ContentType.php          # 内容类型模型
│   │   ├── PostMeta.php             # 文章元数据模型
│   │   ├── Media.php                # 媒体文件模型
│   │   ├── Menu.php                 # 菜单模型
│   │   ├── Setting.php              # 设置模型
│   │   └── Plugin.php               # 插件模型
│   │
│   ├── Service/                     # 业务逻辑层
│   │   ├── UserService.php          # 用户服务（认证、权限、CRUD）
│   │   ├── AuthService.php          # 认证服务（登录、登出、会话管理）
│   │   ├── PostService.php          # 文章服务（CRUD、发布、草稿）
│   │   ├── ContentTypeService.php   # 内容类型服务（管理字段配置）
│   │   ├── CategoryService.php      # 分类服务（树形结构、计数维护）
│   │   ├── TagService.php           # 标签服务（自动创建、清理）
│   │   ├── MediaService.php         # 媒体服务（上传、缩略图、存储）
│   │   ├── MenuService.php          # 菜单服务（层级管理、位置绑定）
│   │   ├── SettingService.php       # 设置服务（全局配置、主题配置）
│   │   ├── PluginService.php        # 插件服务（加载、激活、钩子注册）
│   │   ├── ThemeService.php         # 主题服务（激活、模板加载）
│   │   ├── CacheService.php         # 缓存服务（文件缓存、Redis 支持）
│   │   ├── EmailService.php         # 邮件服务（SMTP、PHP mail）
│   │   └── SchedulerService.php     # 定时任务服务（伪 Cron）
│   │
│   ├── Controller/                  # 控制器层
│   │   ├── BaseController.php       # 基础控制器（请求响应工具、权限检查）
│   │   ├── FrontendController.php   # 前台控制器（首页、文章、分类、标签）
│   │   ├── AdminController.php      # 后台控制器基类（身份验证、菜单加载）
│   │   └── RestController.php       # RESTful API 控制器基类
│   │
│   ├── Middleware/                   # 中间件
│   │   ├── MiddlewareInterface.php   # 中间件接口
│   │   ├── AuthMiddleware.php       # 认证中间件（检查登录状态）
│   │   ├── PermissionMiddleware.php # 权限中间件（检查用户权限）
│   │   ├── CsrfMiddleware.php       # CSRF 保护中间件
│   │   └── ThrottleMiddleware.php   # 请求限流中间件
│   │
│   ├── View/                        # 视图和模板
│   │   ├── View.php                 # 视图渲染器
│   │   ├── Template.php             # 模板引擎（原生 PHP）
│   │   ├── Tags.php                 # 模板标签处理器
│   │   └── Shortcode.php            # 短代码解析器
│   │
│   ├── Form/                        # 表单构建器
│   │   ├── FormBuilder.php          # 表单构建器
│   │   ├── Fields/                  # 字段类型
│   │   │   ├── TextField.php        # 文本输入字段
│   │   │   ├── TextareaField.php    # 多行文本字段
│   │   │   ├── SelectField.php      # 下拉选择字段
│   │   │   ├── CheckboxField.php    # 复选框字段
│   │   │   ├── FileField.php        # 文件上传字段
│   │   │   ├── ImageField.php       # 图片上传字段
│   │   │   ├── DateField.php        # 日期选择字段
│   │   │   ├── WysiwygField.php     # 富文本编辑字段（Quill）
│   │   │   └── RelationField.php    # 关联字段（下拉选择关联对象）
│   │   ├── Validation.php           # 表单验证
│   │   └── Sanitizer.php            # 数据净化
│   │
│   ├── Router/                      # 路由系统
│   │   ├── Router.php               # 路由管理器
│   │   ├── Route.php                # 路由定义
│   │   └── Dispatcher.php           # 路由分发器
│   │
│   ├── Security/                    # 安全相关
│   │   ├── Password.php             # 密码哈希和验证
│   │   ├── Token.php                # 令牌生成和验证
│   │   ├── Csrf.php                 # CSRF 令牌管理
│   │   └── Sanitizer.php            # HTML 净化（防 XSS）
│   │
│   ├── Helper/                      # 辅助函数和工具类
│   │   ├── functions.php            # 全局辅助函数（the_title, the_content 等）
│   │   ├── Str.php                  # 字符串工具（截断、转义、slug 生成）
│   │   ├── Arr.php                  # 数组工具（递归、点号访问）
│   │   ├── Url.php                  # URL 工具（生成、解析、重定向）
│   │   ├── File.php                 # 文件工具（读写、上传、权限）
│   │   ├── Validator.php            # 数据验证工具
│   │   ├── Paginator.php            # 分页工具
│   │   └── DateHelper.php           # 日期处理工具
│   │
│   └── Console/                     # 命令行工具
│       ├── Console.php              # 控制台应用
│       └── Commands/                # 命令
│           ├── MigrateCommand.php    # 数据库迁移命令
│           ├── SeedCommand.php       # 数据填充命令
│           ├── CacheClearCommand.php # 缓存清理命令
│           └── SchedulerRunCommand.php # 定时任务执行命令
│
├── config/                          # 配置文件目录
│   ├── app.php                      # 应用配置（名称、时区、调试模式）
│   ├── database.php                 # 数据库配置（连接参数）
│   ├── security.php                 # 安全配置（密码策略、CSRF）
│   ├── media.php                    # 媒体配置（上传限制、缩略图规格）
│   ├── cache.php                    # 缓存配置（存储类型、TTL）
│   └── email.php                    # 邮件配置（SMTP 参数）
│
├── content/                         # 用户内容目录（不进入版本控制）
│   ├── uploads/                     # 上传文件存储
│   │   ├── 2026/                    # 按年月组织
│   │   │   ├── 01/
│   │   │   │   ├── original/        # 原始文件
│   │   │   │   └── thumbnails/      # 缩略图
│   │   │   └── ...
│   │   └── index.html               # 防止目录浏览
│   │
│   ├── plugins/                     # 插件目录
│   │   ├── hello-world/             # 示例插件
│   │   │   ├── plugin.json          # 插件元数据
│   │   │   ├── Plugin.php           # 插件主类
│   │   │   ├── functions.php        # 插件函数
│   │   │   ├── assets/              # 插件资源
│   │   │   └── README.md            # 插件说明
│   │   └── ...
│   │
│   ├── themes/                      # 主题目录
│   │   ├── default/                 # 默认主题
│   │   │   ├── theme.json           # 主题元数据
│   │   │   ├── screenshot.png       # 主题截图
│   │   │   ├── functions.php        # 主题函数
│   │   │   ├── templates/           # 模板文件
│   │   │   │   ├── header.php       # 公共头部
│   │   │   │   ├── footer.php       # 公共底部
│   │   │   │   ├── index.php        # 首页模板
│   │   │   │   ├── single.php       # 文章详情模板
│   │   │   │   ├── page.php         # 页面模板
│   │   │   │   ├── archive.php      # 归档模板
│   │   │   │   ├── category.php     # 分类归档模板
│   │   │   │   ├── tag.php          # 标签归档模板
│   │   │   │   ├── search.php       # 搜索结果模板
│   │   │   │   └── 404.php          # 404 错误模板
│   │   │   ├── assets/              # 主题资源
│   │   │   │   ├── css/
│   │   │   │   ├── js/
│   │   │   │   └── images/
│   │   │   └── README.md            # 主题说明
│   │   └── ...
│   │
│   ├── languages/                   # 语言包
│   │   ├── zh_CN.php                # 简体中文
│   │   ├── en_US.php                # 英文
│   │   └── ...
│   │
│   └── cache/                       # 缓存目录
│       ├── views/                   # 编译后的视图缓存
│       ├── queries/                 # 查询缓存
│       └── data/                    # 数据缓存
│
├── storage/                         # 运行时存储目录
│   ├── logs/                        # 日志文件
│   │   ├── app.log                  # 应用日志
│   │   ├── error.log                # 错误日志
│   │   ├── security.log             # 安全日志
│   │   └── access.log               # 访问日志
│   │
│   ├── sessions/                    # 会话文件（如果使用文件存储）
│   │   └── ...
│   │
│   ├── temp/                        # 临时文件
│   │   └── ...
│   │
│   └── backups/                     # 备份文件
│       └── ...
│
├── tests/                           # 测试目录
│   ├── Unit/                        # 单元测试
│   │   ├── Model/
│   │   │   ├── UserTest.php
│   │   │   ├── PostTest.php
│   │   │   └── CategoryTest.php
│   │   ├── Service/
│   │   │   ├── UserServiceTest.php
│   │   │   ├── PostServiceTest.php
│   │   │   └── CacheServiceTest.php
│   │   └── Helper/
│   │       ├── StrTest.php
│   │       ├── UrlTest.php
│   │       └── ValidatorTest.php
│   │
│   ├── Feature/                     # 功能测试
│   │   ├── AuthTest.php             # 认证功能测试
│   │   ├── PostApiTest.php          # 文章 API 测试
│   │   └── MediaUploadTest.php      # 媒体上传测试
│   │
│   ├── Integration/                 # 集成测试
│   │   ├── DatabaseTest.php         # 数据库集成测试
│   │   └── PluginTest.php           # 插件集成测试
│   │
│   └── bootstrap.php                # 测试引导文件
│
├── docs/                            # 文档目录
│   ├── README.md                    # 项目说明
│   ├── INSTALL.md                   # 安装指南
│   ├── UPGRADE.md                   # 升级指南
│   ├── API.md                       # REST API 文档
│   ├── HOOKS.md                     # 钩子参考
│   ├── THEMES.md                    # 主题开发指南
│   ├── PLUGINS.md                   # 插件开发指南
│   ├── SECURITY.md                  # 安全指南
│   ├── ARCHITECTURE.md              # 架构设计文档
│   └── CHANGELOG.md                 # 更新日志
│
├── admin/                           # 后台管理界面（独立应用）
│   ├── public/                      # 后台入口
│   │   ├── index.php                # 后台入口文件
│   │   └── assets/                  # 后台静态资源
│   │       ├── css/
│   │       ├── js/
│   │       └── images/
│   │
│   ├── controllers/                 # 后台控制器
│   │   ├── DashboardController.php  # 仪表盘
│   │   ├── PostController.php       # 文章管理
│   │   ├── CategoryController.php   # 分类管理
│   │   ├── TagController.php        # 标签管理
│   │   ├── MediaController.php      # 媒体管理
│   │   ├── UserController.php       # 用户管理
│   │   ├── ContentTypeController.php # 内容类型管理
│   │   ├── MenuController.php       # 菜单管理
│   │   ├── PluginController.php     # 插件管理
│   │   ├── ThemeController.php      # 主题管理
│   │   └── SettingController.php    # 系统设置
│   │
│   ├── views/                       # 后台视图
│   │   ├── layouts/
│   │   │   ├── main.php             # 主布局（侧边栏、顶部导航）
│   │   │   └── login.php            # 登录页面布局
│   │   ├── dashboard/
│   │   │   └── index.php
│   │   ├── posts/
│   │   │   ├── index.php            # 文章列表
│   │   │   ├── edit.php             # 文章编辑
│   │   │   └── trash.php            # 回收站
│   │   ├── categories/
│   │   │   └── index.php
│   │   ├── tags/
│   │   │   └── index.php
│   │   ├── media/
│   │   │   └── index.php
│   │   ├── users/
│   │   │   ├── index.php
│   │   │   └── edit.php
│   │   ├── content-types/
│   │   │   ├── index.php
│   │   │   └── edit.php
│   │   ├── menus/
│   │   │   └── index.php
│   │   ├── plugins/
│   │   │   └── index.php
│   │   ├── themes/
│   │   │   └── index.php
│   │   └── settings/
│   │       ├── general.php          # 常规设置
│   │       ├── reading.php          # 阅读设置
│   │       ├── media.php            # 媒体设置
│   │       └── security.php         # 安全设置
│   │
│   └── routes.php                   # 后台路由定义
│
├── composer.json                    # PHP 依赖管理（仅开发依赖）
├── phpunit.xml                      # PHPUnit 配置
├── .gitignore                       # Git 忽略规则
├── .editorconfig                    # 编辑器配置
├── LICENSE                          # 开源协议
└── README.md                        # 项目根目录说明

---

## 三、核心类接口定义

### 3.1 ServiceProvider 接口

**作用**：服务提供者接口，用于定义服务的注册和管理机制。

**方法**：
- `register()`: 注册服务
- `boot()`: 启动服务

### 3.2 Plugin 接口

**作用**：插件接口，用于定义插件的加载和激活机制。

**方法**：
- `load()`: 加载插件
- `activate()`: 激活插件
- `deactivate()`: 禁用插件

### 3.3 Middleware 接口

**作用**：中间件接口，用于定义中间件的执行机制。

**方法**：
- `handle()`: 处理请求
- `terminate()`: 终止请求

### 3.4 Controller 接口

**作用**：控制器接口，用于定义控制器的请求处理机制。

**方法**：
- `handle()`: 处理请求
- `terminate()`: 终止请求

### 3.5 Model 接口

**作用**：模型接口，用于定义模型的数据库交互机制。

**方法**：
- `save()`: 保存数据
- `delete()`: 删除数据
- `find()`: 查找数据

### 3.6 Service 接口

**作用**：服务接口，用于定义服务的业务逻辑。

**方法**：
- `execute()`: 执行业务逻辑
- `handle()`: 处理请求

---

## 四、开发阶段任务清单

### 阶段一：核心架构（第1-2周）

#### 1.1 基础框架搭建
- [ ] 创建项目目录结构
- [ ] 实现 `App` 核心类（IoC 容器、单例模式）
- [ ] 实现配置管理器 `Config`
- [ ] 实现日志系统 `Log`
- [ ] 实现请求封装 `Request`

#### 1.2 数据库层
- [ ] 设计并创建核心数据表（posts, users, roles, categories, tags）
- [ ] 实现数据库连接管理器 `Connection`
- [ ] 实现查询构建器 `QueryBuilder`
- [ ] 实现基础模型 `Model`
- [ ] 实现迁移工具 `Migration`
- [ ] 编写初始迁移文件

#### 1.3 路由系统
- [ ] 实现路由器 `Router`
- [ ] 实现路由分发逻辑
- [ ] 定义路由规则（前台、后台、API）
- [ ] 实现 URL 自动生成

#### 1.4 中间件系统
- [ ] 定义中间件接口
- [ ] 实现中间件管理器
- [ ] 实现基础中间件（认证、CSRF、限流）

### 阶段二：用户与认证系统（第3周）

#### 2.1 用户模型与数据
- [ ] 实现用户模型 `UserModel`
- [ ] 实现用户服务 `UserService`
- [ ] 实现角色和权限模型
- [ ] 设计用户数据表结构

#### 2.2 认证系统
- [ ] 实现认证服务（登录、登出）
- [ ] 实现会话管理（Session/Token）
- [ ] 实现密码哈希和验证
- [ ] 实现"记住我"功能

#### 2.3 权限控制
- [ ] 实现角色基础系统
- [ ] 实现权限检查中间件
- [ ] 实现基于角色的访问控制（RBAC）
- [ ] 实现管理员后台权限

#### 2.4 后台用户管理
- [ ] 实现用户列表页面
- [ ] 实现用户创建/编辑/删除
- [ ] 实现角色分配
- [ ] 实现用户搜索和过滤

### 阶段三：内容管理系统（第4-6周）

#### 3.1 内容类型系统
- [ ] 实现内容类型模型 `ContentTypeModel`
- [ ] 实现内容类型服务 `ContentTypeService`
- [ ] 设计内容类型配置存储
- [ ] 实现自定义字段类型定义

#### 3.2 内容模型
- [ ] 实现内容模型 `ContentModel`
- [ ] 实现内容服务 `ContentService`
- [ ] 实现内容元数据管理
- [ ] 实现内容版本控制

#### 3.3 后台内容管理
- [ ] 实现内容列表页面
- [ ] 实现内容创建/编辑页面
- [ ] 实现自定义字段渲染
- [ ] 实现内容预览功能
- [ ] 实现内容发布/草稿管理

#### 3.4 分类与标签
- [ ] 实现分类模型和树形结构
- [ ] 实现标签模型
- [ ] 实现分类关联（多对多）
- [ ] 实现标签关联（多对多）
- [ ] 实现分类和标签的后台管理

#### 3.5 媒体管理
- [ ] 实现媒体模型 `MediaModel`
- [ ] 实现文件上传处理
- [ ] 实现图片裁剪和缩放
- [ ] 实现媒体库浏览器
- [ ] 实现媒体文件存储策略

### 阶段四：插件与钩子系统（第7-8周）

#### 4.1 钩子基础系统
- [ ] 实现钩子管理器 `HookManager`
- [ ] 实现事件系统 `EventManager`
- [ ] 定义核心钩子点（pre_post, post_save, user_login 等）
- [ ] 实现钩子优先级机制

#### 4.2 插件架构
- [ ] 实现插件基类 `PluginBase`
- [ ] 实现插件加载器 `PluginLoader`
- [ ] 设计插件目录结构规范
- [ ] 实现插件激活/停用生命周期

#### 4.3 插件管理
- [ ] 实现插件服务 `PluginService`
- [ ] 实现插件安装/卸载
- [ ] 实现插件配置UI
- [ ] 实现插件权限控制

#### 4.4 开发示例插件
- [ ] 创建 Hello World 示例插件
- [ ] 创建 SEO 插件（meta 标签管理）
- [ ] 创建缓存插件（页面缓存）
- [ ] 编写插件开发文档

### 阶段五：主题系统（第9-10周）

#### 5.1 主题架构
- [ ] 实现主题基类 `ThemeBase`
- [ ] 实现主题加载器 `ThemeLoader`
- [ ] 设计主题目录结构
- [ ] 实现主题配置解析

#### 5.2 模板引擎
- [ ] 实现模板引擎（基于PHP）
- [ ] 实现模板继承和块系统
- [ ] 实现模板标签系统
- [ ] 实现模板缓存

#### 5.3 主题管理
- [ ] 实现主题服务 `ThemeService`
- [ ] 实现主题激活/切换
- [ ] 实现主题设置页面
- [ ] 实现主题自定义器

#### 5.4 开发默认主题
- [ ] 创建默认主题目录结构
- [ ] 实现响应式布局
- [ ] 实现核心模板（首页、文章、分类、标签）
- [ ] 实现主题样式和脚本

### 阶段六：API与集成（第11-12周）

#### 6.1 RESTful API
- [ ] 实现API路由和控制器
- [ ] 实现API认证（Bearer Token）
- [ ] 实现API限流
- [ ] 实现API响应格式化

#### 6.2 核心API端点
- [ ] 实现用户API（CRUD、认证）
- [ ] 实现内容API（CRUD、查询）
- [ ] 实现媒体API（上传、管理）
- [ ] 实现分类和标签API

#### 6.3 API文档
- [ ] 编写API使用文档
- [ ] 实现API测试工具
- [ ] 提供API示例代码

#### 6.4 第三方集成
- [ ] 实现OAuth集成框架
- [ ] 实现社交媒体登录
- [ ] 实现邮件服务集成
- [ ] 实现CDN集成

### 阶段七：性能优化与安全（第13周）

#### 7.1 缓存系统
- [ ] 实现缓存管理器 `CacheManager`
- [ ] 实现多种缓存后端（文件、Redis、Memcached）
- [ ] 实现页面缓存
- [ ] 实现查询缓存
- [ ] 实现对象缓存

#### 7.2 性能优化
- [ ] 实现数据库查询优化
- [ ] 实现静态资源压缩和合并
- [ ] 实现图片懒加载
- [ ] 实现CDN支持
- [ ] 实现性能监控

#### 7.3 安全加固
- [ ] 实现安全扫描工具
- [ ] 实现SQL注入防护
- [ ] 实现XSS防护
- [ ] 实现CSRF防护增强
- [ ] 实现安全审计日志

### 阶段八：测试与文档（第14周）

#### 8.1 单元测试
- [ ] 核心类和工具类测试
- [ ] 数据库模型测试
- [ ] 服务层测试
- [ ] 测试覆盖率达到80%以上

#### 8.2 集成测试
- [ ] API集成测试
- [ ] 插件集成测试
- [ ] 主题集成测试
- [ ] 用户流程测试

#### 8.3 文档完善
- [ ] 编写用户手册
- [ ] 编写开发者文档
- [ ] 编写API参考文档
- [ ] 编写安装和部署指南
- [ ] 编写故障排除指南

#### 8.4 Demo环境
- [ ] 搭建在线演示环境
- [ ] 准备示例数据
- [ ] 录制视频教程
- [ ] 创建示例插件和主题