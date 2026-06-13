# Finch PHP CMS · 项目自述规划

> 规划文档版本：Draft 3 | 日期：2026-06-12 | 状态：持续完善架构规划
> 目标首发版本：1.0.0

---

## 一、项目定位与设计原则

### 1.1 定位

**Finch PHP（代号 Preacher）** 是一套纯原生 PHP 8.0+ 开发的通用内容管理系统（CMS）。
支持文章、页面、自定义内容类型，通过插件系统和模板主题实现高度灵活的内容展示。

### 1.2 设计原则（分别对比 Z-Blog / WordPress / ThinkPHP / Laravel）

Finch PHP 不照搬某一个系统，而是分别吸收它们的优点，避开旧式 CMS 和传统框架的常见问题。

| 参考对象 | 值得借鉴 | 需要避免 | Finch PHP 取舍 |
| ------ | ------ | ------ | ------ |
| Z-Blog | 轻量、部署简单、插件钩子直接、三级路由清晰、侧栏模块实用 | PHP 历史包袱重、全局状态偏多、接口现代化不足、权限模型较粗 | 保留轻量部署、钩子、中断信号、三级路由和模块思想；核心代码改为更清晰的类结构与中间件/权限能力模型 |
| WordPress | 原生 PHP 模板生态成熟、函数标签易懂、主题/插件生态清晰、后台操作路径熟悉 | 历史包袱重、全局函数过多、`term`/`meta`/`option` 容易被滥用、分类标签合表会拖累性能 | 借鉴主题/插件/模板标签体验；分类与标签拆表，限制 meta/option 滥用，核心能力用类和服务收束 |
| ThinkPHP | 中文开发者熟悉、MVC 思路明确、路由和数据库封装完整、上手快 | 更偏通用框架而非 CMS，目录和依赖对普通虚拟主机不够友好，CMS 的主题/插件/安装升级需要另行设计 | 借鉴路由、请求、验证、数据库封装思路；保持 CMS 根目录部署友好，不要求 Composer 运行时依赖 |
| Laravel | 现代工程化清晰，中间件、验证、迁移、API Resource、服务提供者、权限思路成熟 | 依赖 Composer 生态和较高运行环境，不适合轻量虚拟主机；完整框架体量偏大 | 借鉴中间件、验证、迁移、API、模型关联、服务提供者理念；用原生 PHP 8.0+ 做轻量实现 |

**核心设计取舍**：

| 原则 | Finch PHP 做法 |
| ------ | ------ |
| PHP 版本 | **最低支持 PHP 8.0**。新项目无历史包袱，直接采用性能最优写法：构造器属性提升、联合类型、`match` 表达式、nullsafe 运算符 `?->`、命名参数、Attributes 等，并默认开启严格类型 |
| 第三方依赖 | **后端零 Composer 运行时依赖**，前端库以静态资源内置且可替换 |
| 全局状态 | 保留 `$fp` 单例作为入口，但模块状态内聚到类，不把业务数据散落到全局变量 |
| 模板系统 | 原生 PHP + 简化函数标签（`title()`, `content()` 等；缩略图函数按独立规则保留 `the_post_thumbnail()`） |
| 数据库 | PDO 统一接口，MySQL/MariaDB 优先，SQLite 作为轻量部署/本地测试方案 |
| 文章类型 | 不采用 Z-Blog 的 0-255 数字类型体系，改用语义化字符串类型；1.0 内置 `article` / `page`，后续由插件/模块注册扩展类型 |
| 插件系统 | 事件驱动钩子 + 中断信号，钩子参数和返回值需要文档化，避免不可控的全局副作用 |
| 路由系统 | 保留 active/rewrite/default 三级路由，路由定义结构化，Web/API/后台统一走 Request/Response |
| 数据模型 | 分类和标签拆表，热点字段真实列，低频扩展才进入 meta 表 |
| 静态资源 | JS 脚本放 `system/script/`，CSS 与图片放 `system/assets/`，后台/主题/插件资源各自独立 |

### 1.3 不做什么（明确排除）

- ❌ 不做 PHP 5.x 兼容层
- ❌ 不做 PHP 7.x 兼容层（最低要求 PHP 8.0，全面使用 PHP 8 语法）
- ❌ 不做后台可视化拖拽建站（第一版）
- ❌ 不做内置 Markdown 编辑器（插件负责）
- ❌ 不做多站点/SAAS 模式
- ❌ 不做无版本、临时拼参数的 API；API 必须版本化、结构化
- ❌ 不引入 Composer 运行时依赖；不要求用户安装 Node/NPM 构建环境
- ❌ 不做独立商城订单/支付/物流系统（简易商品展示即可）

### 1.4 第一轮架构审计结论

为避免 Finch PHP 变成“新名字的旧 CMS”，以下问题必须在 1.0 设计阶段先解决：

| 旧式 CMS 常见问题 | Finch PHP 修正方向 |
| ------ | ------ |
| 分类、标签、扩展字段混在一张大表里，后期查询越来越慢 | **分类和标签拆表**，分类保持轻量稳定，标签允许高容量增长 |
| 没有数据库迁移机制，升级只能靠手写 SQL 或覆盖安装 | **1.0 即内置 migration 表和迁移执行器**，安装建表、升级迁移分离 |
| option/meta 表被滥用，所有业务字段都塞序列化字符串 | 热点字段必须是实体表真实列，meta 只给低频扩展字段使用 |
| 后台、前台、API 逻辑互相穿插，二次开发难维护 | Web 页面、后台页面、API、模块路由都走统一 Request/Response/Router/Middleware |
| 插件钩子到处飞，没有边界，导致核心行为不可预测 | 钩子按生命周期、数据事件、模板事件、API 事件分类，并明确参数和返回值 |
| API 后补，缺少版本和权限模型 | API 作为一等公民，`/v1/` 版本化，Token + Capability 校验 |
| 上传目录可执行 PHP，容易形成安全洞 | 上传目录禁止执行脚本，文件名随机化，MIME + 扩展名双校验 |
| 只追求表少，牺牲查询性能和数据边界 | 不以“少表”为目标，以高频查询稳定、扩展边界清晰为目标 |

---

## 二、技术栈

| 层 | 技术选择 | 原因 |
| ---- | --------- | ------ |
| 语言 | PHP 8.0+ | 全面使用 PHP 8 语法：构造器属性提升、联合类型、`match`、nullsafe `?->`、命名参数、Attributes，默认严格类型；JIT 与引擎优化带来运行时性能提升 |
| 数据库 | PDO（MySQL 5.7+ / SQLite 3） | 统一 PDO 接口，两种驱动 |
| 模板 | 原生 PHP + 简化函数标签 | WordPress 风格，开发者熟悉 |
| 国际化 | 后台/前端两套独立语言包 | 后台语言系统自带，前端语言主题自带，可分别设置 |
| 前端 | 原生 HTML/CSS/JS + 内置静态库 | 前台保持轻量；后台样式与编辑器由预装插件提供（静态文件，无需构建工具） |
| JS 基础库 | jQuery + jquery.form.js + jquery.cookie.js（系统内置于 `system/script/`） | jQuery 作为基础库；jquery.form.js 用于 Ajax 表单/上传；jquery.cookie.js 仅用于非敏感 UI 偏好，登录态仍由服务端 Cookie 管理 |
| 前端构建 | 1.0 不引入 Node/NPM/Vite/Webpack | 后台 CSS/JS 以静态文件内置和手写维护，降低安装门槛，后续 2.0 可再评估构建工具 |
| 后台 UI | Tabler（Bootstrap 管理面板专用） | 作为**系统内置「后台样式插件」默认启用**；开箱即用的仪表盘/表格/侧栏/响应式，可被同类插件替换，后续 2.0 可提供 FinchUI 样式插件 |
| 编辑器 | Quill | 作为**系统内置「编辑器插件」默认启用**；现代编辑体验，输出 HTML，200KB，可被同类插件替换 |
| 后台适配 | 移动端自适应（响应式） | 手机/平板/桌面均可管理 |
| HTTP | 原生 `$_SERVER` / `file_get_contents` / cURL | 完全自给自足 |
| 图片 | GD 库（Imagick 可选） | 验证码 + 缩略图 |
| 缓存 | 文件缓存（`storage/cache/`） | 页面缓存、数据缓存、缩略图缓存统一管理 |

---

## 三、目录结构（规划）

```text
/                               # 站点根目录（Web 根）
├── index.php                   # 唯一入口：所有请求经此路由分发
├── config.php                  # 安装级配置（安装后自动生成，只保存数据库账号、表前缀、密钥等必要信息）
├── install/                    # 安装向导目录
│   └── index.php               #   安装向导入口：引导用户完成数据库配置与管理员初始化
├── system/                     # 系统核心目录（禁止用户修改，升级时整体覆盖）
│   ├── bootstrap.php           #   启动引导：定义常量、注册自动加载、初始化 $fp 单例
│   ├── App.php                 #   全局单例：Finch\App 类，持有 db/settings/user/router/hooks 等核心状态
│   ├── script/                 #   系统内置公共 JavaScript 脚本（与 css/img 资源分离）
│   │   ├── jquery.min.js       #     jQuery 基础库：供后台、主题、插件按需引用
│   │   ├── jquery.form.min.js  #     jQuery Form：后台 Ajax 表单、文件上传、插件配置页提交辅助
│   │   ├── jquery.cookie.min.js #    jQuery Cookie：后台 UI 偏好、主题非敏感偏好读写辅助
│   │   └── admin.js            #     后台公共业务脚本（菜单、表格、表单、编辑器初始化等）
│   ├── assets/                 #   系统内置公共静态资源（只放 css 和 img）
│   │   ├── css/                #     系统/后台公共基础样式（后台框架样式与编辑器样式改由预装插件提供）
│   │   └── img/                #     系统公共图片、图标、默认资源
│   ├── Core/                   #   框架基础设施层
│   │   ├── Database.php        #     数据库抽象层：封装 PDO，提供统一的增删改查接口
│   │   ├── Router.php          #     路由器：三级路由（active/rewrite/default）匹配与分发
│   │   ├── Template.php        #     模板加载器：按优先级加载主题模板文件
│   │   ├── Hook.php            #     钩子系统：注册/触发/过滤钩子，驱动插件机制
│   │   ├── Middleware.php      #     中间件管道：CORS / Auth / Role / 请求前置处理链
│   │   ├── Validation.php      #     输入验证：声明式规则校验（required/max/email/unique 等）
│   │   ├── Config.php          #     安装级配置管理：读取 config.php、校验数据库/密钥/Cookie 等启动配置
│   │   ├── Cache.php           #     缓存管理：文件缓存读写、过期清理
│   │   ├── Thumbnail.php       #     缩略图处理：提取文章首图、压缩裁剪到指定尺寸
│   │   ├── Asset.php           #     资源管理：生成 system/script、system/assets、主题、插件静态资源 URL
│   │   ├── Session.php         #     会话管理：登录态、Cookie 参数、Session 安全封装、记住我 token 校验
│   │   ├── TemplateContext.php #     模板上下文栈：为模板标签提供当前 post/comment/category/query 等
│   │   ├── HtmlCleaner.php     #     HTML 清洗器：按白名单过滤 Quill 输出，去除 XSS 风险（1.0 自写轻量版）
│   │   ├── Logger.php          #     日志管理：错误/调试/安全日志写入 storage/logs/
│   │   ├── ErrorHandler.php    #     错误处理：异常捕获、友好错误页、调试模式输出
│   │   ├── Request.php         #     请求封装：安全获取 GET/POST/SERVER 参数
│   │   └── Response.php        #     响应封装：JSON/HTML 输出与状态码控制
│   ├── Controller/             #   控制器层（只协调请求，不堆业务逻辑）
│   │   ├── BaseController.php  #     控制器基类：Request/Response/View/权限辅助方法
│   │   ├── HomeController.php  #     首页/列表控制器
│   │   ├── PostController.php  #     文章详情控制器
│   │   ├── PageController.php  #     独立页面控制器
│   │   ├── CategoryController.php #  分类列表控制器
│   │   ├── TagController.php   #     标签列表控制器
│   │   └── SearchController.php #    搜索结果控制器
│   ├── Service/                #   业务服务层（承载跨模型业务，避免控制器/模型臃肿）
│   │   ├── AuthService.php     #     登录、退出、密码校验、当前用户恢复
│   │   ├── PostService.php     #     文章保存、发布、分类/标签关联维护
│   │   ├── UploadService.php   #     上传校验、文件命名、图片尺寸读取、上传记录写入
│   │   ├── SettingService.php  #     运行期设置：系统/主题/插件配置读取、写入、缓存失效
│   │   ├── PluginService.php   #     插件扫描、启用、禁用、卸载、生命周期管理
│   │   ├── ThemeService.php    #     主题扫描、切换、语言包/模板加载辅助
│   │   ├── ModuleService.php   #     官方模块注册、启用、禁用、路由注入
│   │   ├── MailService.php     #     邮件门面：找回密码/验证/通知调度 + PHP mail() 保底；发送通道由邮件 Provider 插件提供
│   │   ├── SmsService.php      #     短信门面：验证码生成/校验/限流；发送通道由短信 Provider 插件提供
│   │   ├── CaptchaService.php  #     验证码门面：校验调度；图形/滑块等由验证码 Provider 插件提供
│   │   ├── SearchService.php   #     搜索门面：查询调度；LIKE/全文索引等由搜索 Provider 插件提供
│   │   ├── AiService.php       #     AI 门面：调用调度/限流/日志；协议适配由 AI Provider 插件提供
│   │   └── SocialLoginService.php #  社会化登录门面：授权/绑定/建号；渠道由 OAuth Provider 插件提供
│   ├── Model/                  #   数据模型层（每文件一个实体类，继承 BaseModel）
│   │   ├── BaseModel.php       #     ORM 基类：通用 CRUD、字段映射、查询构建
│   │   ├── Post.php            #     文章/页面/自定义类型模型
│   │   ├── Category.php        #     分类模型（层级结构，低频变化，独立于标签）
│   │   ├── Tag.php             #     标签模型（高容量、平铺结构，可批量清理/合并）
│   │   ├── PostCategory.php    #     文章-分类关联模型（支持主分类与多分类）
│   │   ├── PostTag.php         #     文章-标签关联模型（高容量多对多关联）
│   │   ├── Comment.php         #     评论模型（支持嵌套回复）
│   │   ├── User.php            #     用户/会员模型
│   │   ├── Role.php            #     角色模型（保存角色与 Capability 集合）
│   │   ├── ApiToken.php        #     API Token 模型（小程序/App/第三方调用认证）
│   │   ├── UserSocialAccount.php #   第三方登录账号绑定模型（GitHub/QQ/微信等）
│   │   ├── SmsCode.php         #     手机验证码模型（验证码、过期时间、使用状态）
│   │   ├── Module.php          #     侧栏模块/组件模型
│   │   ├── Upload.php          #     上传文件模型
│   │   ├── SystemSetting.php   #     系统运行期配置模型（站点、评论、固定链接、邮件、API 等）
│   │   ├── ThemeSetting.php    #     主题配置模型（按 theme 分组保存主题自定义项）
│   │   ├── PluginSetting.php   #     插件配置模型（按 plugin 分组保存插件配置与菜单位置）
│   │   ├── ModuleSetting.php   #     官方模块配置模型（按 module 分组保存模块运行期配置）
│   │   ├── AiModel.php         #     AI 大模型配置模型（平台、接口地址、Key、模型名、参数）
│   │   ├── AiLog.php           #     AI 调用摘要日志模型（模型、调用方、耗时、状态、Token 等）
│   │   └── Migration.php       #     数据库迁移记录模型
│   ├── Database/               #   数据库层
│   │   ├── Driver.php          #     数据库驱动接口：定义所有驱动必须实现的方法
│   │   ├── Mysql.php           #     PDO MySQL 驱动
│   │   ├── Sqlite.php          #     PDO SQLite 驱动
│   │   ├── Query.php           #     SQL 构建器：链式调用生成 SELECT/INSERT/UPDATE/DELETE
│   │   ├── Migration.php       #     单个迁移基类：定义 up()/down() 与版本号
│   │   ├── Migrator.php        #     迁移执行器：安装/升级时按版本执行数据库变更
│   │   ├── Schema/             #     数据库结构定义
│   │   │   ├── mysql.sql       #       MySQL/MariaDB 建表语句
│   │   │   └── sqlite.sql      #       SQLite 建表语句
│   │   └── Migrations/         #     版本化迁移脚本（升级时逐个执行）
│   │       └── 1.0.0.php       #       1.0.0 初始迁移定义
│   ├── Admin/                  #   后台管理类（经 Router 分发，命名空间 Finch\Admin）
│   │   ├── DashboardAdmin.php  #     仪表盘：网站统计概览、快速入口
│   │   ├── PostAdmin.php       #     文章编辑器：撰写/编辑/管理文章与页面
│   │   ├── PageAdmin.php       #     页面管理：独立页面列表与编辑
│   │   ├── CategoryAdmin.php   #     分类管理：层级、排序、别名、文章数量
│   │   ├── TagAdmin.php        #     标签管理：标签合并、清理、别名、文章数量
│   │   ├── CommentAdmin.php    #     评论管理：审核/回复/删除/批量操作
│   │   ├── UserAdmin.php       #     用户管理：列表/编辑/角色分配
│   │   ├── ModuleAdmin.php     #     模块管理：侧栏模块的增删改与排序
│   │   ├── UploadAdmin.php     #     文件管理：上传文件浏览/删除
│   │   ├── SettingAdmin.php    #     系统设置：基本/阅读/评论/固定链接/API 配置
│   │   ├── PluginAdmin.php     #     插件管理：启用/禁用/安装/卸载
│   │   ├── ThemeAdmin.php      #     主题管理：切换/预览/自定义
│   │   ├── AiAdmin.php         #     AI 大模型设置：OpenAI 协议平台、模型、Key 与参数管理
│   │   ├── ModuleFeatureAdmin.php #  功能模块管理：官方模块启用/禁用
│   │   └── assets/             #     后台静态资源（只放 css 和 img；后台 JS 统一放 system/script/）
│   │       ├── css/            #       后台样式表
│   │       └── img/            #       后台图片/图标
│   ├── Api/                    #   API 接口模块（JSON 响应，供第三方/移动端调用）
│   │   ├── PostApi.php         #     文章 API：文章的列表/详情/创建/更新/删除
│   │   ├── CategoryApi.php     #     分类 API：分类树、列表、详情、CRUD
│   │   ├── TagApi.php          #     标签 API：标签列表、详情、CRUD、合并清理
│   │   ├── CommentApi.php      #     评论 API：评论的提交/审核/删除
│   │   ├── UserApi.php         #     用户 API：用户信息、登录认证
│   │   ├── ModuleApi.php       #     模块 API：侧栏模块的管理
│   │   ├── UploadApi.php       #     上传 API：文件上传与删除
│   │   └── SystemApi.php       #     系统 API：站点信息、设置读写
│   ├── Helper/                 #   辅助函数集合（按职责拆分，避免单文件臃肿）
│   │   ├── TemplateTags.php    #     模板函数标签：title() / content() / permalink() / the_post_thumbnail() 等
│   │   ├── Security.php        #     安全过滤：XSS 清洗 / SQL 转义 / CSRF 令牌
│   │   └── Utility.php         #     通用工具：URL 构建 / 文件操作 / 日期格式化
│   ├── Languages/               #   系统语言包（后台管理 + 前端公共字符串，全部 14 种）
│   │   ├── zh-cn.php            #     简体中文 ✅ 1.0
│   │   ├── zh-tw.php            #     繁体中文 ✅ 1.0
│   │   ├── en.php               #     英文     ✅ 1.0
│   │   ├── de.php               #     德语     🔜 后续
│   │   ├── it.php               #     意大利语 🔜 后续
│   │   ├── es.php               #     西班牙语 🔜 后续
│   │   ├── fr.php               #     法语     🔜 后续
│   │   ├── ja.php               #     日文     🔜 后续
│   │   ├── ko.php               #     韩文     🔜 后续
│   │   ├── ru.php               #     俄语     🔜 后续
│   │   ├── pt.php               #     葡萄牙语 🔜 后续
│   │   ├── ar.php               #     阿拉伯语 🔜 后续（含 RTL 支持）
│   │   ├── tr.php               #     土耳其语 🔜 后续
│   │   └── pl.php               #     波兰语   🔜 后续
│   └── Theme/                  #   系统默认主题（兜底模板，主题目录无对应文件时回退到此）
│       ├── index.php           #     首页/文章列表模板
│       ├── single.php          #     文章详情页模板
│       ├── page.php            #     独立页面模板
│       ├── header.php          #     公共头部（<head> + 导航）
│       ├── footer.php          #     公共底部（版权 + JS 引入）
│       ├── sidebar.php         #     侧栏模板
│       └── assets/             #     系统默认主题静态资源
│           └── img/            #       默认图片资源
├── content/                    # 用户创作与配置内容目录（备份/迁移时重点关注此目录）
│   ├── plugins/                #   插件目录（含预装插件；预装插件默认启用，部分不可删除，均可被同类插件替换）
│   │   ├── tabler/             #     预装·不可删除：后台样式提供者，提供 Tabler 后台 UI
│   │   │   ├── include.php     #       注册为后台样式提供者，注入 Tabler CSS/JS
│   │   │   ├── script/
│   │   │   │   └── tabler.min.js   # Tabler 后台交互脚本
│   │   │   └── assets/
│   │   │       └── css/
│   │   │           └── tabler.min.css
│   │   ├── quill/              #     预装·不可删除：编辑器提供者，提供 Quill 富文本编辑器
│   │   │   ├── include.php     #       注册为编辑器提供者，注入 Quill JS/CSS 与初始化脚本
│   │   │   ├── script/
│   │   │   │   └── quill.min.js    # Quill 编辑器脚本
│   │   │   └── assets/
│   │   │       └── css/
│   │   │           └── quill.snow.css
│   │   ├── social-github/      #     预装：GitHub 社会化登录（注册 fp_social_login）
│   │   ├── notify-sms/         #     预装：短信通知插件（验证码/系统通知短信，对接服务商，可替换）
│   │   ├── notify-email/       #     预装：邮件通知插件（注册通知/找回密码等邮件通道，可替换）
│   │   ├── captcha-gd/         #     预装：GD 图形验证码（可替换滑块/hCaptcha/reCAPTCHA）
│   │   ├── search-like/        #     预装：SQL LIKE 搜索（可替换全文索引/第三方搜索）
│   │   ├── ai-openai/          #     预装：OpenAI 协议 AI 适配（可替换其他协议/网关）
│   │   └── {plugin_name}/      #     第三方插件（目录名即插件 ID）
│   │       ├── include.php     #       插件入口：定义插件元信息（名称/版本/作者），注册钩子
│   │       ├── include/        #       插件函数库（按用途分文件，由 index.php 加载）
│   │       │   ├── hooks.php   #         钩子回调：处理插件注册的钩子
│   │       │   ├── core.php    #         核心业务逻辑
│   │       │   └── helpers.php #         通用辅助函数
│   │       ├── script/         #       插件 JavaScript 脚本
│   │       │   └── main.js     #         插件主脚本
│   │       └── assets/         #       插件静态资源
│   │           ├── css/        #         插件样式表
│   │           └── img/        #         插件图片/图标
│   ├── modules/                #   官方功能模块（与插件同接口，标记为 official）
│   │   └── {module_name}/      #     单个功能模块（如 shop / community / qa）
│   │       ├── include.php     #       模块入口：注册模块元信息与钩子
│   │       ├── include/        #       模块函数库
│   │       ├── template/       #       模块自带前端模板
│   │       └── assets/         #       模块静态资源
│   ├── themes/                 #   主题目录
│   │   └── {theme_name}/       #     单个主题（目录名即主题 ID）
│   │       ├── theme.json      #       主题信息：名称/版本/作者/截图/描述
│   │       ├── screenshot.png  #       主题截图（后台主题列表展示用）
│   │       ├── include.php     #       主题核心配置：注册/卸载主题，加载 include/ 下的函数文件
│   │       ├── template/       #       模板文件（页面结构与 HTML 输出）
│   │       │   ├── index.php   #         首页模板：文章列表页
│   │       │   ├── single.php  #         文章详情页模板
│   │       │   ├── page.php    #         独立页面模板
│   │       │   ├── header.php  #         公共头部模板（<head> + 导航 + 页头）
│   │       │   ├── footer.php  #         公共底部模板（页脚 + 版权）
│   │       │   └── sidebar.php #         侧栏模板（加载模块/组件）
│   │       ├── include/        #       主题函数库（按用途分目录，由 include.php 统一加载）
│   │       │   ├── hooks.php   #         钩子回调：注册/处理主题级别的钩子
│   │       │   ├── tags.php    #         自定义模板标签函数
│   │       │   ├── widgets.php #         小工具/组件相关函数
│   │       │   └── helpers.php #         通用辅助函数
│   │       ├── script/         #       主题 JavaScript 脚本
│   │       │   └── main.js     #         主题主脚本
│   │       ├── assets/         #       主题静态资源
│   │       │   ├── css/        #         主题样式表
│   │       │   └── img/        #         主题图片（logo/背景/图标等）
│   │       └── languages/      #       主题自带语言包（前端翻译，优先级高于系统语言包，语种同上 14 种）
│   │           ├── zh-cn.php   #         简体中文 ✅ 1.0
│   │           ├── zh-tw.php   #         繁体中文 ✅ 1.0
│   │           ├── en.php      #         英文     ✅ 1.0
│   │           └── ...         #         其他语种 🔜 后续版本逐步补齐
│   ├── uploads/                #   用户上传文件（按年月子目录组织）
│   │   ├── 2026/               #     年份目录
│   │   │   └── 06/             #       月份目录
│   │   │       └── *.jpg       #         上传的图片/附件文件
│   │   └── index.html          #       空文件，防止目录列表暴露

└── storage/                    # 程序运行时自动生成的数据（可清空，不影响业务数据）
    ├── cache/                  #   页面/数据缓存文件
    │   ├── index.html          #     空文件，防止目录列表暴露
    │   ├── thumb/              #     缩略图缓存目录（按尺寸和源图生成）
    │   └── *.cache             #     缓存数据文件
    ├── logs/                   #   错误日志、调试日志
    │   ├── error-{date}.log    #     按日期命名的错误日志文件
    │   └── ai-{date}.log       #     AI 调用排错日志（按后台开关写入，必须脱敏）
    └── data/                   #   系统运行时持久化数据
        └── index.html          #     空文件，防止目录列表暴露
```

---

## 四、核心架构设计

### 4.1 启动流程

```text
1. index.php → require 'system/bootstrap.php'
2. bootstrap.php:
    - 定义 FP_PATH, FP_CONTENT_DIR, FP_STORAGE_DIR 常量
    - 加载 config.php（数据库、密钥、安装级配置包括 timezone）
    - 设置 PHP 临时时区为 config.php 的 app.timezone（默认 UTC），保证 settings 加载前的 date/time 操作正确
    - 注册 PSR-4 风格 spl_autoload_register（`Finch\` => `system/`）
    - 初始化 $fp = Finch\App::getInstance()
3. $fp->init():
   a. 连接数据库（根据配置选择驱动）；**连接失败时进入启动降级：显示友好维护页，不暴露堆栈/空白页**
   b. 加载系统运行期设置 → $fp->settings（此时覆盖 PHP 时区为 site_timezone）
   c. 恢复用户会话 → $fp->user
   d. 加载激活的插件（按依赖顺序 require include.php）
4. $fp->route():
   a. 解析当前 URL
   b. 匹配路由表（active → rewrite → default）
   c. 分发到对应控制器/处理函数
5. 触发 'fp_init' 钩子 → 执行业务逻辑 → 触发 'fp_shutdown' 钩子
6. 输出响应
```

**时区双轨制设计**：

- `config.php['app']['timezone']`：安装级时区，默认 UTC，**不可通过后台修改**（仅能手动编辑 config.php 或重新安装）
- `system_setting.site_timezone`：运行期业务时区，后台可改，默认 `Asia/Shanghai`
- **数据库所有时间字段**统一存 UTC `Y-m-d H:i:s`，写入时已转换
- **模板显示** / **用户输入解析**统一按 `site_timezone` 转换
- bootstrap 启动时立刻设 PHP timezone 为 config.php 的 timezone，避免 settings 加载前出现时区错误
- **时区合法性校验**：bootstrap 校验 `config.php` 的 timezone，若非法（`timezone_identifiers_list()` 不含）自动 fallback 到 UTC 并写警告日志，避免错误时区导致全站时间异常

**最小化启动模式**：

- `install/index.php` 启动时，bootstrap.php 以"最小模式"运行：定义常量、加载自动加载、读取 config.php，但**不连数据库、不加载 settings/user/plugins**
- 判断依据：`config.php` 不存在或 `config['installed'] !== true`
- **安装中断检测**：若 `config.php` 已存在但 `installed !== true`（安装中途中断），自动跳转安装向导并提示"检测到未完成安装"，而非抛数据库连接错误
- 避免安装向导在未完成的系统上触发错误

### 4.2 全局单例 `$fp`（`Finch\App` 类）

核心属性：

- `$fp->db` — 数据库连接（PDO 封装）
- `$fp->config` — 安装级配置（从 config.php 读取，只含数据库、密钥、Cookie、timezone 等启动必需项）
- `$fp->settings` — 运行期设置（从 system_setting / theme_setting / plugin_setting 等表加载并缓存）
- `$fp->user` — 当前登录用户对象（或 null）
- `$fp->router` — 路由器实例
- `$fp->hooks` — 钩子管理器
- `$fp->cache` — 缓存管理器
- `$fp->request` — 请求封装
- `$fp->context` — 模板上下文栈（TemplateContext 实例），为模板标签提供 currentPost/currentComment/currentCategory/currentQuery 等
- `$fp->logger` — 日志服务（统一写盘、脱敏、分流）
- `$fp->isLoggedIn` / `$fp->isAdmin` — 状态标志

**全局状态保护**：核心属性（`db`/`settings`/`user` 等）对外**只读**——通过 `__get` 暴露读取、禁止 `__set` 外部直接覆盖，防止业务/插件代码意外篡改全局状态；不强制改为 `getDb()` 全 getter，保留开发者书写习惯。

### 4.3 数据库层

数据库层必须比传统 CMS 更清晰：**MySQL 是第一优先级，SQLite 是轻量部署/本地测试方案**。所有业务 SQL 通过 PDO 预处理和 Query Builder 生成，不允许拼接用户输入。

**接口**：`Finch\Database\Driver`（init/query/select/insert/update/delete/prepare/beginTransaction/commit/rollback/getInsertId/getPrefix/getVersion）

**驱动**（2 种）：

- `Finch\Database\Mysql` — PDO MySQL/MariaDB，默认 InnoDB + utf8mb4
- `Finch\Database\Sqlite` — PDO SQLite，初始化时自动执行默认 PRAGMA：

  ```sql
  PRAGMA journal_mode=WAL;     -- 并发读写
  PRAGMA foreign_keys=ON;      -- 启用外键约束
  PRAGMA synchronous=NORMAL;   -- 平衡性能与安全
  PRAGMA temp_store=MEMORY;    -- 临时表入内存
  PRAGMA cache_size=-2000;     -- 2MB 缓存（可配置；默认不用 20MB 以适配小内存虚拟主机）
  ```

**连接超时**：`config.php` database 段提供连接超时参数（默认 5 秒），避免数据库不可达时长时间阻塞请求。

**SQL 构建器**：`Finch\Database\Query` 提供链式调用：`table()`、`select()`、`where()`、`whereIn()`、`join()`、`orderBy()`、`limit()`、`paginate()`、`insert()`、`update()`、`delete()`、`count()`、`exists()`。

**迁移机制**：1.0 必须内置 `migration` 表和 `Migrator`。安装时执行初始 Schema，升级时按版本执行迁移脚本，避免旧 CMS 常见的“覆盖文件后数据库结构不一致”。

**表前缀**：`{prefix}` 在运行时替换为配置中的表前缀；表名和字段名只允许来自白名单，不能接受用户输入。

**索引原则**：

- 列表页常用筛选字段必须建组合索引，例如 `post(type, status, published_at)`。
- `slug` 类字段使用唯一索引，避免同名 URL 冲突。
- 所有关联表必须同时建立正向和反向索引，例如 `post_id + tag_id` 与 `tag_id + post_id`。
- 计数字段如 `post_count` 属于冗余字段，写入时通过事务维护，读取时避免反复 `COUNT(*)`。

**原子计数原则**：`category.post_count` / `tag.post_count` 的增减一律用数据库原子操作 `UPDATE ... SET post_count = post_count ± 1 WHERE id = ?`，**禁止先读再写**（避免并发下计数错乱）；全量重算仅用于修正可能的计数漂移。

**软删除全局范围**：`BaseModel` 提供 `scopeNotDeleted()`，默认自动过滤 `deleted_at IS NOT NULL` 的记录，减少业务代码遗漏；需含已删除记录时显式调用 `withTrashed()`。

**事务原则**：文章保存、分类关联、标签关联、上传归档、用户绑定第三方账号等操作必须使用事务，避免半成功数据。

**扩展字段原则**：允许 `post_meta` / `user_meta` 这类低频扩展表，但禁止把标题、状态、分类、价格、库存等高频查询字段塞进 meta。官方模块如果有高频字段，应使用模块自己的表和迁移脚本。

**配置读取原则**：`config.php` 只存安装级配置；后台可修改的系统、主题、插件、模块、AI、邮件等运行期配置必须进入独立数据表。`system_setting.autoload = 1` 的系统配置在启动阶段一次性加载；大文本、第三方密钥、AI 参数、插件低频设置不得 autoload，按需读取并缓存。

### 4.4 数据模型（按性能边界拆分）

Finch PHP 不以“表越少越好”为目标，而以**查询稳定、边界清晰、升级可维护**为目标。分类和标签明确拆表：分类是站点结构，标签是内容标注，两者增长模式完全不同。

#### 4.4.1 内容核心表

| 表名 | 用途 | 关键字段 |
| ------ | ------ | ---------- |
| `post` | 文章/页面/自定义类型 | id, author_id, primary_category_id, title, slug, content, excerpt, type, status, previous_status, comment_status, is_top, view_count, published_at, created_at, updated_at, deleted_at |
| `category` | 分类 | id, parent_id, name, slug, description, sort_order, post_count, status, created_at, updated_at |
| `post_category` | 文章-分类关联 | post_id, category_id |
| `tag` | 标签 | id, name, slug, post_count, created_at, updated_at |
| `post_tag` | 文章-标签关联 | post_id, tag_id |
| `comment` | 评论 | id, post_id, author_id, parent_id, author_name, author_email, content, status, ip, user_agent, created_at, updated_at, deleted_at |
| `upload` | 上传文件 | id, user_id, post_id, filename, original_name, path, mime_type, extension, size, width, height, created_at |

**`post.status` 状态枚举**（1.0 启用 5 个值 + 预留 1 个）：

| 值 | 含义 |
| ------ | ------ |
| `draft` | 草稿，未发布 |
| `publish` | 已发布 |
| `pending` | 待审核 |
| `private` | 私密（仅作者和管理员可见） |
| `trash` | 回收站 |
| `scheduled` | **（预留）**定时发布：`published_at` 作为定时发布时间。**1.0 仅预留枚举值与钩子，不实现调度逻辑**；提前预留避免将来改枚举造成迁移 |

- `status = 'trash'` 时，`deleted_at` 同时被填充（时间戳）。
- `deleted_at` 非空 → 无论 status 值如何，都被视为**回收站**状态，前台查询默认过滤。
- "恢复文章"操作：清空 `deleted_at`，`status` 恢复到 `previous_status` 字段记录的值。
- "永久删除"操作：物理删除 `post` 记录，级联清理 `post_category`、`post_tag`、`post_meta`、`comment` 关联行。
- API 默认只返回 `status IN (publish, private)` 且 `deleted_at IS NULL` 的记录，其他状态需显式传参。

**`previous_status` 字段**：`post` 表增加 `previous_status` 字段（VARCHAR(32), NULL），用于软删除前暂存旧状态。当文章从任何非 trash 状态进入 trash 时，把原 status 写入此字段；恢复时读回并写回 status，然后置空 previous_status，避免文章恢复后状态错乱。

**主分类字段一致性规则**：

`post.primary_category_id` 是**唯一真实字段**，`post_category` 表不再保留 `is_primary` 标志，仅作为多对多映射表。这样做的原因：

- `post_category.is_primary` 是 `post.primary_category_id` 的衍生信息，两份数据必然不一致。
- 列表页高频查询 `SELECT ... FROM post WHERE primary_category_id = ?` 不需要 JOIN。
- 所有写入操作（创建/更新/删除文章、调整分类关联）必须通过 `PostService` 完成，`PostService` 在事务内同步维护 `post.primary_category_id`，禁止 Model 直接操作此字段。
- 查询"一篇文章的主分类"一律用 `JOIN category ON category.id = post.primary_category_id`，不从 `post_category` 推断。
- 关联表 `post_category` 仅记录"这篇文章属于哪些分类"（多分类展示、分类页筛选用）。

**分类/标签拆分原因**：

- 分类数量通常少、层级稳定、参与导航和列表页高频查询，必须保持小表和强索引。
- 标签可能被用户滥用，数量和关联行会快速膨胀，必须独立承载高容量增长。
- 分类查询只走 `category` + `post_category`，不会扫描标签表。
- 标签可单独做合并、清理、限流、禁用热门标签缓存，不影响分类性能。

**建议索引**：

- `post`: `UNIQUE(slug, type)`，`INDEX(type, status, published_at)`，`INDEX(author_id, status)`，`INDEX(primary_category_id, status, published_at)`。
- `category`: `UNIQUE(slug)`，`INDEX(parent_id, sort_order)`，`INDEX(status, sort_order)`。
- `post_category`: `UNIQUE(post_id, category_id)`，`INDEX(category_id, post_id)`。
- `tag`: `UNIQUE(slug)`，`INDEX(name)`，`INDEX(post_count)`。
- `post_tag`: `UNIQUE(post_id, tag_id)`，`INDEX(tag_id, post_id)`。

**`post_count` 字段维护责任**：

`category.post_count` 和 `tag.post_count` 是冗余计数字段，所有变更都要通过 `PostCountService::syncCategory($categoryId)` / `syncTag($tagId)` 原子化维护（内部用 `UPDATE ... SET post_count = post_count ± 1 WHERE id = ?` 原子增减，全量重算仅用于修漂移），**禁止**业务代码在事务外随意读改 `post_count`。

以下路径必须经过 PostCountService：

- PostService 创建/删除文章
- PostCategory / PostTag::sync 调整关联
- PostService 软删除 / 恢复 / 回收站清空
- TagAdmin 的合并/清理
- 插件触发 `fp_post_save` 后若改动关联关系，必须显式调用 sync

**文章类型定案**：

- 不保留 Z-Blog 的 0-255 数字自定义类型作为核心设计，避免类型含义不透明、API 不语义化、插件互相抢数字段。
- `post.type` 使用语义化字符串，1.0 内置 `article` 和 `page`。
- 类型 slug 规则：小写字母开头，只允许 `a-z`、`0-9`、`_`，长度 2-32，例如 `product`、`topic`、`question`。
- 插件/模块后续通过内容类型注册接口声明类型名称、标签、权限、路由、模板优先级和是否支持评论。
- 官方预留类型（仅保留命名、防止第三方占用，各自由对应版本的官方模块注册实现）：`topic`（1.1 社区）、`product`（1.2 商城）、`question`（1.3 问答）。1.0 不内置这些类型。
- 如果未来做 Z-Blog 数据导入，数字类型只在导入器中映射到字符串类型，不进入 Finch PHP 核心数据模型。

#### 4.4.2 用户与权限表

| 表名 | 用途 | 关键字段 |
| ------ | ------ | ---------- |
| `user` | 用户/会员 | id, username, password, email, phone, role_id, capabilities, status, display_name, avatar_id, login_fail_count, locked_until, last_login_at, last_login_ip, created_at, updated_at |
| `role` | 角色与能力集合 | id, name, title, capabilities, is_system, created_at, updated_at |
| `api_token` | API 访问令牌 | id, user_id, token_hash, name, abilities, last_used_at, expires_at, created_at |
| `remember_token` | 记住我长期登录 token | id, user_id, token_hash, user_agent_hash, ip, expires_at, last_used_at, created_at |
| `user_social_account` | 第三方账号绑定 | id, user_id, provider, openid, unionid, nickname, avatar_url, raw_data, created_at, updated_at |
| `sms_code` | 手机验证码 | id, phone, scene, code_hash, ip, expires_at, used_at, created_at |

**`user.status` 状态枚举**（固定 4 个值）：

| 值 | 含义 |
| ------ | ------ |
| `active` | 活跃正常，可登录 |
| `pending_verify` | 等待手机/邮箱验证 |
| `pending_email` | 等待邮箱激活 |
| `disabled` | 被管理员禁用 |

**1.0 不做用户注销（GDPR）**，原因：注销涉及清理/匿名化关联的文章、评论、上传归属，会牵动大量业务表。

- `user` 表**没有 `deleted_at`** 字段，禁用只能改 `status = disabled`。
- 后台管理员"删除用户"在 1.0 只支持禁用，不支持真正删除（避免关联数据清理返工）。
- 2.0 若需支持注销，建议引入 `user.deleted_at` + 数据匿名化 Service。

**`role.capabilities` 字段格式**：

- 数据类型：MySQL 用 `JSON`，SQLite 用 `TEXT`（SQLite 无原生 JSON，按 JSON 字符串存）。
- 默认值：`[]`（空 JSON 数组），**禁止**为 `null`，避免读出来时分叉判断。
- 特殊值：`["*"]` 表示"全部权限"（仅 `administrator` 预设角色允许使用）。
- 模型层封装 `grant($cap)` / `revoke($cap)` / `has($cap)` 方法，禁止业务代码对字符串做 split/push。
- 插件注册的自定义 capability 命名：`{plugin_id}_{action}`，例如 `manage_sample_settings`，避免和系统 capability 冲突。

**`user.capabilities` 用户级权限（预留）**：

- `user` 表增加 `capabilities` JSON 字段（默认 `[]`），支持给单个用户额外授予/撤销权限，**优先级高于角色权限**。
- 权限合并规则：最终能力 = 角色 capabilities ⊕ 用户级授予 ⊖ 用户级撤销。
- **1.0 仅建字段 + 权限校验逻辑预留**，不做后台管理 UI，避免权限模型过早复杂化；UI 缓至后续版本。

**`remember_token` 表设计细节**：

- `token_hash`：用 SHA256 对明文 token 做 hash 存储，明文只下发给客户端 Cookie，服务端不留。
- `user_agent_hash`：SHA256 截前 32 字符，用于同设备续期校验和设备数量限制。
- `expires_at`：默认 30 天，后台可配置为 7/30/90/365 天保存到 `system_setting.remember_token_days`。
- 续期机制（Sliding Expiration）：每次登录成功且 token 有效，更新 `last_used_at`，但 `expires_at` 不变；剩余时间小于 1/3 时自动续期。
- 登出时删除当前设备 token（`WHERE user_agent_hash = ?`），不影响其他设备。
- **单用户最多保留 5 个有效 token**，超过时由 AuthService 自动删除 `last_used_at` 最早的记录，限制多设备泛滥。
- 与 `api_token` 表分离，因为前者面向用户浏览器，后者面向小程序/App/第三方，安全策略和权限粒度不同。

#### 4.4.3 系统与扩展表

| 表名 | 用途 | 关键字段 |
| ------ | ------ | ---------- |
| `module` | 侧栏模块/官方功能模块启用状态 | id, name, type, content, sidebar, position, config, is_active, created_at, updated_at |
| `system_setting` | 系统运行期配置 | id, name, value, type, autoload, updated_at |
| `theme_setting` | 主题配置 | id, theme, name, value, type, autoload, updated_at |
| `plugin_setting` | 插件配置 | id, plugin, name, value, type, autoload, updated_at |
| `module_setting` | 官方模块配置 | id, module, name, value, type, autoload, updated_at |
| `ai_model` | AI 大模型配置 | id, platform, protocol, base_url, api_key_encrypted, model, params_json, is_default, status, created_at, updated_at |
| `ai_log` | AI 调用摘要日志 | id, model_id, model_name, platform_name, user_id, caller_type, caller_name, scene, status, prompt_tokens, completion_tokens, duration_ms, error_code, request_hash, created_at |
| `migration` | 数据库迁移记录 | id, owner_type, owner_id, version, name, batch, executed_at |
| `post_meta` | 文章低频扩展字段 | id, post_id, meta_key, meta_value, meta_type, autoload, created_at, updated_at |
| `user_meta` | 用户低频扩展字段 | id, user_id, meta_key, meta_value, meta_type, autoload, created_at, updated_at |

**`migration` 表增强设计**：

支持插件/模块独立管理自己的迁移文件，避免所有迁移都混在核心迁移序列里：

- `owner_type`：`core` / `plugin` / `module`，标记迁移归属
- `owner_id`：插件 ID 或模块 ID（`core` 类型时填 `system`）
- 唯一约束：`UNIQUE(owner_type, owner_id, version)`，防止同一来源的版本号重复
- `batch`：批次号，用于回滚时按批次回滚同一轮的所有变更
- `executed_at`：迁移执行时间

**执行顺序与依赖管理**：

```text
Migrator::runPending():
  1. 按 owner_type = 'core' 执行核心迁移（按 version 升序）
  2. 遍历已激活的插件，按激活顺序执行 plugin 类型迁移
  3. 遍历已激活的模块，执行 module 类型迁移
  4. 每个迁移文件必须声明依赖清单（可选），执行前检查依赖均已运行
  5. 每个迁移放在事务中，失败回滚当前批次
```

**迁移文件格式示例**：

```php
// system/Database/Migrations/1.0.0_001_create_posts.php
class Migration_1_0_0_001_create_posts extends \Finch\Database\Migration {
    public string $version = '1.0.0-001';
    public string $ownerType = 'core';
    public string $ownerId = 'system';
    public array $dependencies = []; // 无依赖

    public function up(): void {
        $this->db->execute('CREATE TABLE ...');
    }

    public function down(): void {
        $this->db->execute('DROP TABLE posts');
    }
}
```

**`ai_log` 表的快照字段**：

为避免模型被删除或改名后无法追溯，增加快照字段：

- `model_name`：调用时的模型名称（VARCHAR(100)），不依赖外键
- `platform_name`：调用时的平台名称（VARCHAR(100)），不依赖外键
- 这两列与 `model_id` 冗余保存，保证历史日志的可读性，即使对应的 `ai_model` 记录被删除

**运行期配置分表原则**：

- `system_setting` 只放系统后台可修改的系统级配置（站点、阅读、评论、用户、固定链接、邮件、API、外观/编辑器、各 Provider 当前启用项等），完整键名清单见下方「`system_setting` 配置项总表」，全文以该表为唯一权威来源。
- `theme_setting` 按主题 ID 分组保存主题自定义项，切换主题时不删除旧主题配置。
- `plugin_setting` 按插件 ID 分组保存插件配置、默认配置页、菜单位置等，插件卸载时由用户选择保留或删除。
- `module_setting` 保存官方模块的运行配置，避免把模块配置混入系统配置。
- `ai_model` 独立保存多个 OpenAI 协议兼容模型；平台 Key 必须加密保存，Service 层对主题/插件返回数组形式的可选模型清单。
- `ai_log` 只保存后台可筛选、可统计的调用摘要，不默认保存完整 prompt 和 response。
- 不再设置泛化的 `option` 总表，避免所有模块把配置都塞进一张表后重走旧 CMS 的 option 滥用问题。

**`system_setting` 配置项总表**（1.0 内置项的唯一权威清单；安装时写入默认值，后台「系统设置」分组编辑。`autoload=1` 的项在启动阶段一次性加载，其余按需读取并缓存。键名以此表为准，全文其他章节引用须与此一致）：

| 分组 | 键名 `name` | 类型 `type` | 默认值 | autoload | 说明 |
| ------ | ------------ | ------------ | -------- | :--------: | ------ |
| 基本 | `site_name` | string | `Finch` | 1 | 站点标题 |
| 基本 | `site_subtitle` | string | （空） | 1 | 站点副标题/标语 |
| 基本 | `site_url` | string | 安装时探测 | 1 | 站点根 URL（用于生成绝对链接） |
| 基本 | `site_description` | string | （空） | 1 | 站点描述（SEO meta） |
| 基本 | `site_keywords` | string | （空） | 1 | 站点关键词（SEO meta） |
| 基本 | `admin_language` | string | `zh-cn` | 1 | 后台默认语言包 |
| 基本 | `site_timezone` | string | `Asia/Shanghai` | 1 | 运行期业务时区，覆盖 PHP 时区 |
| 基本 | `maintenance_mode` | bool | `0` | 1 | 维护模式开关（开启时前台对游客显示维护页） |
| 阅读 | `posts_per_page` | int | `10` | 1 | 前台列表每页文章数 |
| 阅读 | `feed_items` | int | `10` | 1 | RSS/Feed 输出条数 |
| 阅读 | `default_post_type` | string | `article` | 1 | 后台新建内容默认类型 |
| 评论 | `comment_enabled` | bool | `1` | 1 | 全站评论总开关 |
| 评论 | `comment_need_audit` | bool | `1` | 1 | 新评论是否需审核后显示 |
| 评论 | `comment_guest_allowed` | bool | `1` | 1 | 是否允许游客评论 |
| 评论 | `comment_login_required` | bool | `0` | 1 | 评论是否必须登录 |
| 用户 | `registration_enabled` | bool | `1` | 1 | 是否开放注册 |
| 用户 | `default_role` | string | `subscriber` | 1 | 新注册用户默认角色 |
| 用户 | `remember_token_days` | int | `30` | 1 | 「记住我」有效天数（7/30/90/365） |
| 固定链接 | `permalink_structure` | string | `/post/{id}` | 1 | 文章 URL 结构模板 |
| 固定链接 | `category_base` | string | `category` | 1 | 分类 URL 前缀 |
| 固定链接 | `tag_base` | string | `tag` | 1 | 标签 URL 前缀 |
| 邮件 | `mail_from_name` | string | 同 `site_name` | 0 | 发件人显示名 |
| 邮件 | `mail_from_address` | string | （空） | 0 | 发件人地址 |
| 邮件 | `smtp_host` / `smtp_port` / `smtp_user` / `smtp_pass` / `smtp_secure` | string | （空） | 0 | SMTP 连接参数（`smtp_pass` 加密存储）；缺省时邮件门面降级用 PHP `mail()` |
| API | `api_enabled` | bool | `1` | 1 | REST API 总开关 |
| API | `api_cors_origins` | string | （空） | 0 | 允许跨域来源白名单（逗号分隔） |
| 外观/编辑器 | `admin_style` | string | `tabler` | 1 | 当前启用的后台样式提供者 `style_id` |
| 外观/编辑器 | `editor` | string | `quill` | 1 | 当前启用的编辑器提供者 ID |
| 外观/编辑器 | `active_theme` | string | 默认主题 ID | 1 | 当前启用的前台主题 |
| Provider 当前项 | `sms_provider` | string | `notify-sms` | 1 | 当前启用的短信通道 Provider |
| Provider 当前项 | `mail_provider` | string | `notify-email` | 1 | 当前启用的邮件通道 Provider（降级回 `mail()`） |
| Provider 当前项 | `captcha_provider` | string | `captcha-gd` | 1 | 当前启用的验证码 Provider |
| Provider 当前项 | `search_provider` | string | `search-like` | 1 | 当前启用的搜索 Provider |
| Provider 当前项 | `ai_provider` | string | （空，未配置） | 0 | 当前启用的 AI 协议 Provider |

> 说明：上表为 1.0 系统级配置基线；主题项进 `theme_setting`、插件项进 `plugin_setting`、模块项进 `module_setting`、AI 模型与密钥进 `ai_model`，均不写入本表。各 `*_provider` 仅记录"当前启用项"，对应 Provider 的内部参数（如服务商 AppKey）由该插件的 `plugin_setting` 保存。

**关键设计变化 vs Z-Blog/WordPress**：

- 分类和标签拆表，不采用 WordPress 式统一 `term` 大表。
- 文章保留 `primary_category_id`，列表页可快速读取主分类；多分类通过 `post_category` 承载。
- User 用 `role_id` 关联角色表，Capability 存在 `role.capabilities`，插件可注册能力并授权。
- Post 用 `slug` 代替 `Alias`，并通过唯一索引保证 URL 稳定。
- 统一用 `created_at` / `updated_at` / `deleted_at` 时间字段命名。

### 4.5 ORM 层

借鉴 Z-Blog 思路，简化实现：

1. **字段映射定义**（FP 的 `datainfo` 等价物）在每个 Model 类中作为静态属性
2. **Base Model** 提供通用 `find()` / `save()` / `delete()` / `query()` 方法
3. **业务 Model** 继承 Base，添加业务方法

**BaseModel 通用能力**（减少业务样板代码）：

- **自动时间戳**：`created_at` / `updated_at` 由 BaseModel 在 `save()` 时自动写入，统一存 UTC，**不依赖数据库默认值**（保证 MySQL/SQLite 行为一致），业务代码无需手动赋值。
- **软删除全局范围**：`scopeNotDeleted()` 默认过滤 `deleted_at IS NOT NULL`，`withTrashed()` 显式包含已删除记录。
- **预加载防 N+1**：`with()` 支持关联预加载；1.0 实现单层（`with('author', 'comments')`），**嵌套点号语法（`with('comments.author')`）接口预留、实现缓至 1.1**。

**`post.excerpt` 自动生成**：保存文章时，若用户未手动填写摘要，自动从 `content` 截取前 200 个纯文本字符（先经 `HtmlCleaner` 的 `excerpt` profile 去除所有标签，避免截断半截标签），允许用户手动覆盖；摘要在 `fp_post_save` 前置钩子内生成。

**内置模型关联**（为二次开发提供便捷的联表查询）：

| 关系 | 方法 | 示例 |
| ------ | ------ | ------ |
| 归属 | `belongsTo(Class, $foreignKey)` | 文章 → 作者 |
| 一对多 | `hasMany(Class, $foreignKey)` | 文章 → 评论 |
| 一对一 | `hasOne(Class, $foreignKey)` | 用户 → 头像 |
| 多对多 | `belongsToMany(Class, $pivotTable)` | 文章 ↔ 标签（post_tag） |

```php
// 概念示意：
class Post extends BaseModel {
    public function author() {
        return $this->belongsTo(User::class, 'author_id');
    }
    public function comments() {
        return $this->hasMany(Comment::class, 'post_id');
    }
    public function categories() {
        return $this->belongsToMany(Category::class, 'post_category');
    }
    public function tags() {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }
}
// 一条语句完成联表：Post::with('author', 'comments', 'categories', 'tags')->find(1);
```

```php
// 字段映射定义（与关联互补）：
class Post extends BaseModel {
    protected static $table = 'post';
    protected static $fields = [
        'id' => ['type'=>'int', 'primary'=>true],
        'title' => ['type'=>'string', 'max'=>250],
        // ...
    ];
}
```

### 4.6 钩子/插件系统（继承 Z-Blog 模式）

**钩子注册**：`$fp->hooks->add('hook_name', $callback, $priority)`
**钩子触发**：`$fp->hooks->do('hook_name', ...$args)`
**过滤器**：`$fp->hooks->filter('hook_name', $value, ...$args)`

**命名规范**：`fp_{context}_{action}`，例如：

- `fp_init` — 系统初始化后
- `fp_post_save` — 保存文章前
- `fp_post_saved` — 保存文章后
- `fp_comment_insert` — 评论插入前
- `fp_route_match` — 路由匹配时
- `fp_template_load` — 模板加载时
- `fp_head` — 前端 `<head>` 输出前
- `fp_footer` — 前端 `</body>` 输出前
- `fp_admin_head` — 后台 `<head>` 输出前
- `fp_register_admin_style` — 注册后台样式提供者（内置 Tabler 及第三方替换插件在此注册）
- `fp_register_editor` — 注册编辑器提供者（内置 Quill 及第三方替换插件在此注册）
- `fp_social_login` — 注册社会化登录渠道（GitHub/QQ/微信等 OAuth 插件在此注册）
- `fp_register_sms_provider` — 注册短信通道 Provider（短信通知/各服务商插件在此注册）
- `fp_register_mail_provider` — 注册邮件发送通道 Provider（SMTP/第三方邮件 API 插件在此注册）
- `fp_register_captcha` — 注册验证码 Provider（GD/滑块/hCaptcha 插件在此注册）
- `fp_register_search` — 注册搜索 Provider（LIKE/全文索引/第三方搜索插件在此注册）
- `fp_register_ai_provider` — 注册 AI 协议 Provider（OpenAI 及其他协议插件在此注册）

**插件 `include.php` 执行顺序与规范**：

插件的 `include.php` 文件执行顺序严格规定如下，以保证 PluginService 正确识别插件元信息和注册的钩子：

```php
<?php
/**
 * Plugin Name: 示例插件
 * Plugin ID: sample_plugin
 * Version: 1.0.0
 * Description: 简短描述（50字内）
 * Author: 作者
 * Author URI: https://example.com
 * Requires Finch: 1.0.0
 * Requires PHP: 8.0
 */

// ========== 第一步：注册钩子和过滤器（顶部代码） ==========
// 通过顶层 require_once 加载插件函数文件，这些文件中调用 hooks->add()
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/filters.php';

// ========== 第二步：注册自定义路由、资源、内容类型（中间代码） ==========
// 调用 PluginService 注册接口，而非直接操作内部数组
$fp->routes->addPluginRoute('sample_plugin', '/my-page', 'SamplePageController::show');
$fp->assets->register('sample_plugin_style', '/sample/style.css', ['admin']);
$fp->contentTypes->registerType('book', $fp->t('book_type_label'));

// 插件也可以注册钩子（非必须，但推荐用函数文件）
$fp->hooks->add('fp_post_save', [SamplePlugin::class, 'onPostSave']);

// ========== 第三步：return 数组声明插件元信息（文件末尾） ==========
// PluginService::loadPlugins() 会 require_once 这个文件并接收返回值
return [
    'plugin_id' => 'sample_plugin',
    'name' => '示例插件',
    'version' => '1.0.0',
    'author' => '作者',
    'author_uri' => 'https://example.com',
    'description' => '简短描述',
    'requires_finch' => '1.0.0',
    'requires_php' => '8.0',
    'settings_page' => 'SamplePluginAdmin::settings',  // 后台设置页的 Handler
    'menu_location' => 'sidebar',  // sidebar / top / settings / hidden
    'menu_title' => '示例插件',
    'menu_icon' => 'fa-book',
    'menu_order' => 50,
];
```

**PluginService 加载流程**：

```text
1. PluginService::loadPlugins() 查询 plugin_setting 表获取激活插件列表
2. 根据各插件声明的 requires_plugins 依赖做拓扑排序（被依赖者先加载）；同优先级再按 menu_order 排序保证后台菜单顺序稳定
3. 依赖缺失或版本不满足时，该插件不加载并记录提示（不影响其他插件）
4. require_once plugin_dir/include.php
5. 捕获返回值（数组），写入内部 $pluginMeta[$plugin_id] 字典
6. 检查返回值中是否存在 plugin_id、version 等必要字段，缺失则禁用并记录日志
7. PluginService 根据 menu_location 决定如何把插件设置页注入后台菜单
8. 所有激活插件的钩子在 require_once 期间已自动注册到 HookManager
```

**插件依赖与冲突检测**：

- `include.php` 元信息可声明 `requires_plugins`（依赖插件 ID + 版本要求），PluginService 按依赖拓扑顺序加载，避免加载顺序问题。
- 激活前检测**类名 / 插件 ID 冲突**（依托 `Finch\` 命名空间 + 插件 ID 前缀），冲突时给出警告不予激活；钩子同名属设计意图，不作冲突判定。

**注意事项**：

- `include.php` **绝不可以**输出任何字符（echo/print），否则破坏 AJAX 响应和 JSON API
- 不要在 `include.php` 里直接执行业务逻辑（数据库操作、网络请求等），业务逻辑应放在钩子回调里
- 不要在 `include.php` 里 `exit()` 或 `die()`，会导致后续插件无法加载
- 插件 ID 必须唯一，且与目录名一致
- 插件可以在 `activate()` 生命周期注册自己的 migration，Migrator 会按插件独立执行；插件升级时自带的迁移脚本自动执行数据库变更
- 插件卸载时弹出确认框，提供"保留数据"（默认）与"删除全部数据"两个选项
- **所有核心钩子必须带 PHPDoc 注释**（说明用途、参数、返回值、触发时机），作为后续自动生成钩子文档的数据源

**插件结构**：

```text
content/plugins/{plugin_name}/
├── include.php        # 插件入口：注册钩子 + 返回元信息数组
├── include/           # 插件函数库
│   ├── hooks.php      #   钩子回调：处理插件注册的钩子
│   ├── core.php       #   核心业务逻辑
│   └── helpers.php    #   通用辅助函数
├── script/            # 插件 JavaScript 脚本
│   └── main.js        #   插件主脚本
└── assets/            # 插件静态资源
    ├── css/           #   插件样式表
    └── img/           #   插件图片/图标
```

**中断信号**（保留 Z-Blog 模式）：

- `FP_HOOK_CONTINUE` — 继续
- `FP_HOOK_RETURN` — 返回插件结果
- `FP_HOOK_BREAK` — 中断后续

**插件默认配置页与后台菜单位置**：插件可在 `include.php` 的元信息中声明配置页和菜单展示位置，由 `PluginService` 读取后注入后台菜单。

```php
return [
    'plugin_id' => 'sample_plugin',
    'name' => '示例插件',
    'version' => '1.0.0',
    'description' => '简短描述（50字内）',
    'author' => '作者',
    'author_uri' => 'https://example.com',
    'requires_finch' => '1.0.0',
    'requires_php' => '8.0',
    'settings_page' => 'SamplePluginAdmin::settings',
    'menu_location' => 'sidebar', // top / sidebar / settings / hidden
    'menu_title' => '示例插件',
    'menu_icon' => 'plug',
    'menu_order' => 80,
    'capability' => 'manage_sample_plugin',
];
```

菜单位置规则：

- `top`：显示在后台顶部菜单，适合高频、独立业务型插件。
- `sidebar`：显示在左侧栏主菜单，适合有多个管理页面的插件。
- `settings`：归入「系统设置」或「插件管理」下的配置入口，适合低频配置型插件。
- `hidden`：不自动显示菜单，只允许插件自行注册入口或通过 URL 访问。
- 插件菜单是否显示必须同时通过 `capability` 校验，避免普通管理员看到无权访问的插件设置页。

**预装插件统一放在 `content/plugins/`（默认启用，可替换；部分不可删除）**

所有官方随核心发布的插件**统一放在 `content/plugins/` 下集中管理**，与第三方插件**共用同一套插件接口**（`include.php` + 钩子 + 元信息）和同一个插件管理界面。区别仅在于：官方预装、安装时默认启用、随核心升级更新；其中少数承载后台基础设施的插件标记为**不可删除**（但仍可在有替代时停用），其余可由用户自由停用/删除。

按"是否可删除"分两类：

### A. 不可删除的预装插件（后台基础设施，无它后台无法正常显示/编辑）

- **Tabler 后台样式插件**（`tabler`）：默认的「后台样式提供者」，向后台注入 Tabler 的 CSS/JS，决定整个后台 UI 外观。
- **Quill 文章编辑器插件**（`quill`）：默认的「编辑器提供者」，向文章/页面编辑界面注入 Quill 富文本编辑器与初始化脚本，输出 HTML 经 `HtmlCleaner` 清洗后入库。

这两个插件可被同类第三方插件替换，但**不允许删除**（随核心升级更新），以保证后台始终有可用样式与编辑器：

- **后台样式提供者插槽**：插件在 `include.php` 通过 `$fp->hooks->add('fp_register_admin_style', ...)` 注册自身为后台样式提供者，声明 `style_id`、显示名与资源加载回调；后台「外观/系统设置」列出所有已注册的后台样式，由 `system_setting.admin_style`（默认 `tabler`）指定当前启用项。同一时刻只有一个后台样式提供者生效。
- **编辑器提供者插槽**：插件通过 `$fp->hooks->add('fp_register_editor', ...)` 注册自身为编辑器提供者，声明 `editor_id`、显示名、资源与初始化脚本，并约定输出为可被 `HtmlCleaner` 清洗的 HTML；由 `system_setting.editor`（默认 `quill`）指定当前启用项。同一时刻只有一个编辑器提供者生效。
- 用户启用替换用的第三方后台样式/编辑器插件并在设置中切换后，`tabler` / `quill` 自动让位（不再注入资源），但仍保留为可随时切回的兜底项；当前选定的提供者被停用或缺失时，系统自动回退到 `tabler` / `quill`。

### B. 可停用/删除的预装功能插件（Provider 门面模式）

其余预装功能插件承载「可选业务能力」，删除后只是对应功能不可用，不影响后台正常运转。设计统一采用 **「核心门面 + Provider 插槽」** 模式：核心层只提供一个 Service 门面（定义接口、调度、限流、降级），具体实现由 Provider 插件通过钩子注册；门面在无可用 Provider 时按既定规则优雅降级。这样主程序与各 Provider 分别维护，互不牵连。

| 门面（核心） | 注册钩子 | 预装默认插件（`content/plugins/`） | 可替换为 | 无 Provider 时降级 |
| ------ | ------ | ------ | ------ | ------ |
| `SocialLoginService` | `fp_social_login` | `social-github`（GitHub OAuth） | QQ / 微信 / 任意 OAuth 插件 | 对应第三方登录入口不显示 |
| `SmsService` | `fp_register_sms_provider` | `notify-sms`（短信通知：验证码/系统通知短信，对接服务商） | 阿里云 / 腾讯云 / Twilio 等服务商插件 | 短信验证码与短信通知功能禁用 |
| `MailService` | `fp_register_mail_provider` | `notify-email`（邮件通知：找回密码/验证/系统通知，默认 SMTP） | 第三方邮件 API（SendGrid / 阿里云邮推等）插件 | 门面回退到核心保底 PHP `mail()`，保证找回密码等关键邮件仍可发 |
| `CaptchaService` | `fp_register_captcha` | `captcha-gd`（GD 图形验证码） | 滑块 / hCaptcha / reCAPTCHA 插件 | 核心保底最简校验，登录/注册不至于卡死 |
| `SearchService` | `fp_register_search` | `search-like`（SQL LIKE 搜索） | 全文索引 / Meilisearch / ES 插件 | 核心内联最简 LIKE 兜底，搜索仍可用 |
| `AiService` | `fp_register_ai_provider` | `ai-openai`（OpenAI 协议适配） | 其他协议 / 自建网关插件 | `$fp->ai->chat()` 返回"AI 未配置"错误，调用方需处理 |

Provider 注册与生效规则：

- 每个门面同一时刻只启用「一个」当前 Provider（由对应 `system_setting` 指定，如 `sms_provider` / `mail_provider` / `captcha_provider` / `search_provider` / `ai_provider`）；社会化登录例外，允许同时启用多个登录渠道。
- 启用了替换插件并在设置中切换后，预装默认插件自动让位但保留为可切回项。
- 当前选定 Provider 被停用/删除时，门面自动回退到「默认插件 → 核心保底」链路：搜索/验证码/邮件有核心保底（保证站点与找回密码可用），短信/AI/社会化登录无保底则优雅禁用对应入口（属可选能力，合理）。
- 核心**只依赖门面接口**，绝不直接引用具体 Provider；`MailService` 门面本身（含 PHP `mail()` 保底）、`HtmlCleaner`、缩略图、评论等核心依赖与安全基线**不做成可停用插件**——可停用/删除的只是邮件「发送通道 Provider」，而非邮件门面。

> 插件分层不再按物理目录区分，而按"是否可删除"区分：Tabler/Quill 因属后台基础设施标记为不可删除（随核心升级更新），其余预装插件可被用户完全掌控（停用/删除/替换）。全部预装插件都位于 `content/plugins/`，在同一插件管理界面统一管理。

### 4.7 路由系统（保留三级）

**路由类型**：

1. **active** — 动态 URL：`?fp=post&id=1`
2. **rewrite** — 伪静态：`/post/sample-slug.html`
3. **default** — 兜底路由

**路由规则**：`{%host%}/post/{%slug%}.html` → 解析后匹配

**路由定义**：

```php
// 概念示意：
$fp->router->add('post_single', [
    'type' => 'rewrite',
    'pattern' => '/post/{slug}.html',
    'handler' => 'Finch\\Controller\\PostController::single',
    'params' => ['slug']
]);
```

**默认处理器**（对应 Z-Blog 的 View* 思路，但实际由控制器承载）：

- `Finch\Controller\HomeController::index()` — 首页/列表
- `Finch\Controller\PostController::single()` — 文章详情
- `Finch\Controller\PageController::single()` — 独立页面
- `Finch\Controller\CategoryController::list()` — 分类文章列表
- `Finch\Controller\TagController::list()` — 标签文章列表
- `Finch\Controller\SearchController::index()` — 搜索结果

### 4.8 模板系统（原生 PHP + 函数标签）

不使用编译型模板引擎，采用 **WordPress 风格**：

- 模板文件就是 `.php` 文件，直接 `require`
- 提供一套简化的模板函数标签

**模板函数标签示例**：

```php
// 概念示意：
title()               // 输出文章标题
content()             // 输出文章内容
permalink()           // 输出文章链接
post_date()           // 输出发布日期（避免与 PHP date() 冲突）
author()              // 输出作者名
categories()          // 输出分类列表
tags()                // 输出标签列表
comments()            // 输出评论列表
the_post_thumbnail('', 300, 200)  // 输出文章缩略图
sidebar('side1')      // 输出指定侧栏模块
pagination()          // 输出分页
head()                // 输出 head 钩子
footer()              // 输出 footer 钩子
```

**模板文件结构**（以 `single.php` 为例）：

```html
<?php head(); ?>
<?php get_header(); ?>
<article>
    <h1><?php title(); ?></h1>
    <div><?php content(); ?></div>
</article>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
```

**模板加载优先级**（向 Z-Blog 学习）：

`子主题 template/` > `当前（父）主题 template/` > `插件注册模板` > `system/Theme/`（系统兜底）

- 子主题优先级最高，便于用户在不改动原主题的前提下覆盖个别模板。
- 插件可注册模板片段（如模块卡片），优先级低于主题、高于系统兜底，保证主题始终能覆盖插件外观。
- 任意层级缺失某模板时逐级回退，最终落到系统兜底模板，保证不白屏。

**主题依赖与兼容性**：`theme.json` 增加 `requires_finch`（依赖的核心版本）和 `requires_plugins`（依赖插件 ID + 版本），切换主题时自动检查兼容性，不满足时给出提示并阻止启用。

**主题后台预览**：管理员可在后台通过预览参数（如 `?fp_preview_theme={theme}`，仅管理员会话生效）查看主题效果，不切换全站主题、不影响前台普通访客访问。

**TemplateContext 模板上下文栈**：

模板标签函数（`title()`、`content()` 等）不直接依赖全局变量或魔法方法，而是通过 `$fp->context` 上下文栈获取当前数据。栈式设计允许嵌套循环（如文章中的评论列表、分类中的子分类）：

```php
// TemplateContext 核心方法
$fp->context->push(Post $post);          // 进入文章详情时入栈
$fp->context->pop();                      // 离开文章详情时出栈
$fp->context->current(): ?Post;           // 获取栈顶（当前上下文）
$fp->context->peek(int $depth): mixed;    // 查看栈中第 N 层（0=栈顶）
```

**模板标签实现示例**：

```php
function title(): string {
    $post = app()->context->current();
    return $post ? esc_html($post->title) : '';
}

function content(): string {
    $post = app()->context->current();
    return $post ? $post->content : ''; // content 已在保存时清洗
}

function comment_author(): string {
    $comment = app()->context->current();
    return $comment ? esc_html($comment->author_name) : '';
}
```

**上下文入栈时机**：

- `PostController::single()` → push(Post) 后渲染模板
- `CommentController::loadComments()` → 每条评论 push(Comment) 后渲染评论模板片段
- 嵌套循环模板（如 `comments.php` 中再循环回复）→ 多层 push/pop

**禁止反模式**：

- 不允许在模板标签内直接 `global $post` 或访问 `$GLOBALS`
- 不允许在 Service 层操作上下文（仅 Controller 和模板加载器操作）

**缩略图机制**：

- `the_post_thumbnail($imagePath, $width, $height)` 输出文章缩略图，例如 `the_post_thumbnail('图片路径', 300, 200)`
- `$imagePath` 为空时，默认提取当前文章内容中第一张 `<img>` 作为源图
- `$imagePath` 包含本地图片路径或远程图片链接时，压缩该路径/链接对应的图片
- 源图经 GD 库压缩/裁剪到指定尺寸，生成缩略图缓存在 `storage/cache/thumb/`
- 如果既没有传入图片路径，文章内容也没有图片，则返回空内容，不输出默认占位图
- 后续请求直接命中缓存，避免重复压缩

**语言包加载机制**：

- 后台管理：始终使用 `system/Languages/{locale}.php`，在系统设置中切换
- 网站前端：优先加载当前主题的 `content/themes/{name}/languages/{locale}.php`
- 前端回退：主题未提供某条翻译时，回退到 `system/Languages/{locale}.php`
- 后台语言和前端语言可独立设置（如：后台中文 + 前台英文）
- 模板中通过 `trans('key')` 获取翻译字符串

**Quill 编辑器 HTML 白名单与清洗规则**：

Quill 编辑器输出的 HTML 在保存前必须经过 `HtmlCleaner` 清洗，防止 XSS 攻击和非法 HTML 注入。

1.0 版本 `HtmlCleaner` 的默认白名单（可在 `system_setting.html_whitelist` 覆盖）：

**允许标签**：`p`、`br`、`strong`、`em`、`u`、`s`、`sub`、`sup`、`blockquote`、`pre`、`code`、`h1-h6`、`ul`、`ol`、`li`、`a`、`img`、`figure`、`figcaption`、`table`、`thead`、`tbody`、`tfoot`、`tr`、`th`、`td`、`caption`、`colgroup`、`col`、`hr`、`div`、`span`、`video`、`source`、`iframe`（仅限白名单域名）

**允许属性**：

- 通用：`class`、`id`、`style`（仅白名单 CSS 属性）
- `a`：`href`（仅 http/https/mailto/锚点）、`title`、`target`（强制 `_blank` 时添加 `rel="noopener noreferrer"`）、`rel`
- `img`：`src`（仅 http/https/本站相对路径）、`alt`、`title`、`width`、`height`、`class`、`loading`
- `td`/`th`：`colspan`、`rowspan`、`scope`
- `video`/`source`：`src`、`controls`、`width`、`height`、`poster`、`type`、`preload`
- `iframe`：`src`（仅白名单域名）、`width`、`height`、`frameborder`、`allowfullscreen`

**iframe 白名单域名**（可在后台扩展）：`youtube.com`、`youtu.be`、`bilibili.com`、`player.bilibili.com`、`v.qq.com`、`youku.com`、`vimeo.com`

**强制剥离**：

- 所有 `on*` 事件属性（`onclick`、`onload`、`onerror` 等）
- `javascript:` 协议
- `<script>`、`<style>`（非白名单场景）、`<base>`、`<meta>`、`<link>`、`<object>`、`<embed>`、`<applet>` 等危险标签
- `data:` URL（除非 MIME 是白名单图片类型）
- 所有 `data-*` 自定义属性（除非显式白名单）

**清洗流程**（`HtmlCleaner::clean($html, $profile = 'post_content')`）：

```text
1. DOMDocument 加载 HTML（自动补全 doctype）
2. 递归遍历节点，每个节点按白名单过滤：
   - 标签不在白名单 → 剥离标签但保留文本内容
   - 属性不在白名单 → 删除该属性
   - 属性值不合规（如 javascript: 协议） → 删除该属性
3. 特殊处理 <a target="_blank">：强制补全 rel="noopener noreferrer"
4. 特殊处理 <iframe src>：仅保留白名单域名，其余整标签剥离
5. 序列化输出，移除多余空白
6. 返回清洗后的安全 HTML
```

**清洗 profiles**：

| Profile | 用途 | 标签范围 |
| --- | --- | --- |
| `post_content` | 文章正文（Quill 输出） | 允许全部白名单标签 |
| `comment_content` | 评论内容 | 仅 `p`、`br`、`strong`、`em`、`code`、`a`、`img`（`<img>` 限本站 + 白名单外链） |
| `excerpt` | 摘要 | 仅文本标签，剥离所有 `<img>`、`<video>`、`<iframe>` |
| `user_bio` | 用户简介 | 与评论一致，进一步限制 `<a>` 数量 |

**Quill 图片上传入口约束**：

Quill 内置图片按钮需要重定向到 Finch 统一上传接口，禁止前端直接 Base64 内嵌超大图片：

- 拦截 Quill 的 `image` handler，弹窗调用 Admin 上传接口 `POST /admin/upload/image`
- 接口返回 `{ "url": "https://..." }`，Quill 插入到内容中
- 后台 Quill 图片工具栏提供"图库选择"和"本地上传"两个按钮
- 上传接口强制走 `UploadService::store()` 流程（含 EXIF 剥离、MIME 双校验）

### 4.9 API 系统

API 层作为"一等公民"设计，支撑小程序、App 后端和第三方集成。

**入口**：`/index.php?api=v1/posts/1`（所有请求经 index.php 路由分发，RESTful 风格）

**版本策略**：URL 前缀 `/v1/`，后续可并行运行 `/v2/`，确保旧客户端不受影响。

**认证**：Bearer Token（`Authorization: Bearer xxx`）或请求参数 `token`

**响应格式**（统一通过 `ApiResource` 基类输出）：

```json
{
    "code": 0,
    "message": "success",
    "data": { ... }
}
```

**分页响应**：

```json
{
    "code": 0,
    "data": [...],
    "meta": {
        "total": 150,
        "per_page": 15,
        "current_page": 1,
        "last_page": 10
    }
}
```

**CORS 中间件**：所有 API 响应自动添加跨域头，支持小程序/Web/ajax 跨域请求。

**内置 API 模块**：

- `posts` — 文章 CRUD + 列表 + 分页
- `categories` — 分类树、分类 CRUD、分类文章列表
- `tags` — 标签 CRUD、标签合并、标签文章列表
- `comments` — 评论 CRUD + 列表
- `users` — 用户信息 + 登录认证
- `modules` — 模块管理
- `uploads` — 文件上传/删除
- `system` — 系统信息/设置

**扩展**：插件通过 `fp_api_register_mod` 钩子注册自定义 API 端点。

**API 错误码分段**（20000-29999 保留给系统，其余分配给各模块）：

| 错误码范围 | 模块 | 说明 |
| ------ | ------ | ------ |
| 0 | 成功 | 请求成功 |
| 1000-1999 | 通用错误 | 参数校验失败、权限不足、未登录、CSRF 失败 |
| 2000-2999 | 系统模块 | 系统配置错误、维护模式、速率限制 |
| 3000-3999 | 用户/认证 | 登录失败、密码错误、验证码失效、Token 过期 |
| 4000-4999 | 内容模块 | 文章/分类/标签不存在、已删除、无权限 |
| 5000-5999 | 上传模块 | 文件类型不允许、大小超限、上传失败 |
| 6000-6999 | 评论模块 | 评论被禁用、审核中、重复提交 |
| 7000-7999 | AI 模块 | 模型调用失败、超时、余额不足 |
| 8000-8999 | 插件/模块 | 插件加载失败、模块冲突 |
| 9000-9999 | 预留 | 未来模块使用 |

**示例**：

- `1001` = 参数校验失败
- `3001` = 用户名或密码错误
- `3002` = 验证码已过期
- `4001` = 文章不存在
- `4002` = 文章已删除
- `7001` = AI 模型调用超时

**HTTP 状态码与业务码结合**：响应同时设置合适的 HTTP 状态码（便于 CDN/客户端按标准处理）和精确业务码（便于定位具体错误）。映射关系（与上表错误码分段对齐，无重叠）：

| HTTP 状态码 | 业务码段 | 含义 |
| ------ | ------ | ------ |
| 200 | 0 | 请求成功 |
| 400 | 1000-1999 | 客户端参数/通用错误 |
| 401 | 3000-3099 | 未登录 / 认证失败 |
| 403 | 3100-3999 | 权限不足 |
| 404 | 4000-4999 | 资源不存在 / 已删除 |
| 429 | 2900-2999 | 请求频率超限（节流） |
| 500 | 5000-5999 等 | 服务器内部错误 |

> 注：豆包原表把 429 与 500 同映射到 `2000-2099` 属冲突，此处已分开（429→`29xx`、500→`5xxx`），与 4.9 错误码分段保持一致。

**API 缓存**：GET 请求可按资源类型附加可选的 `Cache-Control` 响应头（如公开文章列表 `public, max-age=60`），支持浏览器和 CDN 缓存；写操作和需鉴权的资源强制 `no-store`。

### 4.10 中间件管道

请求进入控制器前，经过可串联的中间件链处理。借鉴 Laravel 的设计：

```text
Request → CORS → Auth → Role → Throttle → Controller → Response
```

| 中间件 | 用途 | 1.0 |
| ------ | ------ | :---: |
| `CorsMiddleware` | 跨域头处理（API 必备） | ✅ |
| `AuthMiddleware` | 校验登录态 | ✅ |
| `RoleMiddleware` | 角色权限校验 | ✅ |
| `ThrottleMiddleware` | 频率限制（防刷） | 🔜 1.1 |

路由注册时指定中间件链，替代每个页面手写权限检查：

```php
$fp->router->add('api_posts', [
    'middleware' => ['cors', 'auth', 'role:editor'],
    'throttle'   => '60,1', // 预留：每分钟 60 次；1.0 仅解析存储，节流逻辑由 1.1 的 ThrottleMiddleware 实现
    'handler' => 'PostApi::list'
]);
```

> `throttle` 参数 1.0 即在路由定义中预留并解析（避免 1.1 引入时改动既有路由声明），但限流执行缓至 1.1。

### 4.11 输入验证

声明式规则校验，Web 表单和 API 请求统一处理：

```php
$rules = [
    'title'   => 'required|max:250',
    'email'   => 'required|email|unique:user',
    'price'   => 'numeric|min:0',
    'content' => 'required',
];
$validator = validate($request, $rules);
if ($validator->fails()) {
    return json_error($validator->errors(), 422);
}
```

内置规则：`required` `max` `min` `email` `numeric` `unique:table,column` `in:a,b,c` `regex`。插件可扩展自定义规则。

### 4.12 权限系统

采用 **角色（Role）+ 能力（Capability）** 二维模型，比 Z-Blog 的 1-6 数字等级更语义化、更可扩展。

```text
角色（Role）          ← 用户归属
  └─ 能力（Capability） ← 角色自带的操作权限集合
       └─ 权限检查（Gate） ← 运行时校验：$user->can('edit_post', $postId)
```

**预设角色**：

| 角色 | 自带 Capability |
| ------ | ------ |
| `administrator` | `*`（全部权限） |
| `editor` | `edit_others_post` `publish_post` `moderate_comment` `upload_file` `manage_categories` |
| `author` | `edit_post` `upload_file`（仅自己） |
| `contributor` | `edit_post`（不能发布） |
| `subscriber` | `read` |

**权限检查**：`$fp->user->can('edit_post', $post_id)`

**用户级权限覆盖**：权限检查时按"角色 capabilities ⊕ 用户级授予 ⊖ 用户级撤销"合并，`user.capabilities`（见 4.4.2）优先级高于角色；1.0 仅预留字段与合并逻辑，不做后台 UI。

**API Token 能力（abilities）细粒度控制**：`api_token.abilities` 支持通配符与排除语法，中间件按优先级校验：

- 通配符：`posts:*` 表示文章相关全部操作。
- 精确：`posts:read`、`posts:create`。
- 排除：`!posts:delete` 表示在已授予范围内排除删除（排除优先级最高）。
- 校验顺序：先看排除项命中→拒绝；再看精确/通配符授予→放行；否则拒绝。

**插件扩展**：任意插件可注册自定义 Capability，并授权给任意角色：

```php
$fp->registerCapability('manage_orders');
$editorRole->grant('manage_orders');
```

### 4.13 后台管理

**UI 框架**：由内置「后台样式提供者」插件提供，默认 `tabler`（基于 Bootstrap，专为管理面板设计，内置响应式布局），可在外观设置中切换为其他样式插件；后续 2.0 版可提供 FinchUI 样式插件。

**编辑器**：由内置「编辑器提供者」插件提供，默认 `quill`（现代所见即所得，输出纯 HTML 经 `HtmlCleaner` 清洗后存库），可切换为其他编辑器插件。

**移动端适配**：所有后台页面自适应手机/平板/桌面，侧栏折叠、表格横向滚动、表单全宽适配。

**页面清单**：

1. 仪表盘 — 统计概览
2. 文章管理 — 列表 + 编辑器
3. 页面管理 — 独立页面列表
4. 分类管理 — 层级、排序、别名、文章数量
5. 标签管理 — 标签合并、清理、别名、文章数量
6. 评论管理 — 审核/回复/删除
7. 用户管理 — 用户列表 + 编辑
8. 模块管理 — 侧栏模块配置
9. 文件管理 — 上传文件浏览
10. 系统设置 — 基本/阅读/评论/固定链接/API/邮件
11. 插件管理 — 启用/禁用/安装
12. 主题管理 — 切换/自定义
13. 功能模块 — 官方模块启用/禁用
14. AI 大模型设置 — OpenAI 协议平台、模型、Key、默认模型、参数管理

**后台权限中间件**：每个管理页面开头检查 `$fp->user->can('access_admin')`

### 4.14 用户中心与社会化登录

**登录方式分期**：

| 方式 | 1.0 | 说明 |
| ------ | :---: | ------ |
| 用户名/邮箱 + 密码 | ✅ | 基础登录（核心内置） |
| 手机验证码 | ✅ | `SmsService` 门面 + 短信 Provider 插件；预装 `notify-sms`（短信通知插件，对接服务商），可替换 |
| GitHub | ✅ | 预装 `social-github` 插件（纯 OAuth，零成本），默认启用 |
| QQ | 🔜 1.1 | 独立 OAuth 插件，需 QQ 互联资质审核 |
| 微信 | 🔜 1.1 | 独立 OAuth 插件，需微信开放平台认证 |

**账号绑定机制**：

- 用户中心提供统一的「账号绑定」页面，展示已绑定的第三方平台
- 第三方登录时可选择绑定已有账号或自动创建新账号
- 所有第三方登录通过 `fp_social_login` 钩子注册，核心 `SocialLoginService` 门面只负责授权回调、绑定、建号调度，渠道实现全部来自 OAuth Provider 插件，核心不硬编码任何平台

**短信通知（SmsService 门面 + Provider 插件）**：

- 核心 `SmsService` 负责验证码生成、校验、频率限流、`sms_code` 表读写，**不绑定任何服务商**
- 具体发送通道由短信 Provider 插件通过 `fp_register_sms_provider` 注册，`system_setting.sms_provider` 指定当前启用项
- 预装 `notify-sms` 短信通知插件作默认，负责发送验证码短信与系统通知短信，对接服务商通道（在其设置页填写服务商密钥/签名/模板）；生产环境可直接配置使用，也可替换为阿里云/腾讯云/Twilio 等专用插件
- 无任何短信 Provider 时，短信验证码与短信通知功能自动禁用（属可选能力，合理降级）

**邮件通知（MailService 门面 + Provider 插件）**：

- 业务服务层必须内置 `MailService` 门面，因为找回密码、邮箱验证、管理员通知、评论通知、插件通知都依赖邮件。**门面本身属核心强依赖，不做成可停用插件**
- 实际发送通道做成可替换的邮件 Provider 插件，通过 `fp_register_mail_provider` 注册，`system_setting.mail_provider` 指定当前启用项
- 预装 `notify-email` 邮件通知插件作默认（默认走 SMTP），负责发送找回密码/验证/系统通知邮件；SMTP 主机、端口、账号、加密方式、发件人名称等保存到 `system_setting`，不写入 `config.php`；可替换为 SendGrid / 阿里云邮件推送等第三方 API 插件
- 当邮件 Provider 被停用/缺失时，门面自动回退到核心保底 PHP `mail()`，保证找回密码等关键邮件仍能发出
- 插件通过统一 `MailService` 门面调用邮件，不直接拼接邮件头或自行处理 SMTP 连接

### 4.15 功能模块系统

商城、社区、问答等场景功能，统一为「功能模块」机制——它们本质是**自定义文章类型 + 扩展字段 + 前端模板**的组合，不需要各自独立实现。

**目录结构**：

```text
content/modules/{module_name}/
├── include.php        # 注册模块（与插件同接口，标记 type=official）
├── include/           # 模块函数库
├── template/          # 模块自带前端模板
└── assets/            # 静态资源
```

**模块与插件的关系**：共用同一套钩子接口，区别在于模块由官方维护、后台单独管理、安装时可预选启用。

**分期交付**：

| 版本 | 模块 | 说明 |
| ------ | ------ | ------ |
| 1.0 | 功能模块框架 | 模块注册/激活/路由注入/模板加载基础设施 |
| 1.1 | 社区（community） | topic 文章类型，复用评论系统 |
| 1.2 | 商城（shop） | product 类型 + 简易下单（无支付），通过钩子扩展 |
| 1.3 | 问答（qa） | question 类型 + 状态流转（已解决/未解决） |

### 4.16 伪静态与 URL 管理

URL 路由是系统入口，**不可拆成插件**。三级路由配置放在后台系统设置页：

| 模块 | 说明 |
| ------ | ------ |
| 动态模式 | `/?post=1`，默认开启，安装即用 |
| 伪静态模式 | `/post/my-article.html`，用户一键切换 |
| 自定义规则 | 支持 `{%id%}` `{%slug%}` `{%year%}` `{%month%}` `{%day%}` `{%category%}` 等占位符 |

用户切换后，`system/Core/Router.php` 初始化时读取 `system_setting` 表中的固定链接配置生效。

**Nginx 伪静态配置示例**：

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/finch-cms;
    index index.php;
    
    # 伪静态规则（文章详情页）
    rewrite ^/article/([0-9]+)\.html$ /index.php?mod=article&act=detail&id=$1 last;
    rewrite ^/article/category/([0-9]+)\.html$ /index.php?mod=article&act=category&id=$1 last;
    rewrite ^/article/tag/([0-9]+)\.html$ /index.php?mod=article&act=tag&id=$1 last;
    
    # 伪静态规则（页面）
    rewrite ^/page/([a-zA-Z0-9_-]+)\.html$ /index.php?mod=page&act=detail&slug=$1 last;
    
    # 伪静态规则（分类和标签）
    rewrite ^/category/([a-zA-Z0-9_-]+)\.html$ /index.php?mod=category&act=detail&slug=$1 last;
    rewrite ^/tag/([a-zA-Z0-9_-]+)\.html$ /index.php?mod=tag&act=detail&slug=$1 last;
    
    # 静态资源直接返回，不经过 PHP
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    
    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
    }
}
```

**Apache 伪静态配置示例**（`.htaccess`）：

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # 如果请求的是真实文件或目录，直接返回
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    
    # 伪静态规则（文章详情页）
    RewriteRule ^article/([0-9]+)\.html$ index.php?mod=article&act=detail&id=$1 [L,QSA]
    RewriteRule ^article/category/([0-9]+)\.html$ index.php?mod=article&act=category&id=$1 [L,QSA]
    RewriteRule ^article/tag/([0-9]+)\.html$ index.php?mod=article&act=tag&id=$1 [L,QSA]
    
    # 伪静态规则（页面）
    RewriteRule ^page/([a-zA-Z0-9_-]+)\.html$ index.php?mod=page&act=detail&slug=$1 [L,QSA]
    
    # 伪静态规则（分类和标签）
    RewriteRule ^category/([a-zA-Z0-9_-]+)\.html$ index.php?mod=category&act=detail&slug=$1 [L,QSA]
    RewriteRule ^tag/([a-zA-Z0-9_-]+)\.html$ index.php?mod=tag&act=detail&slug=$1 [L,QSA]
</IfModule>

# 禁止访问隐藏文件
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>
```

**IIS 伪静态配置示例**（`web.config`，Windows 服务器）：

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <!-- 真实文件或目录直接返回 -->
        <rule name="Ignore Existing Files" stopProcessing="true">
          <match url="^(.*)$" />
          <conditions>
            <add input="{REQUEST_FILENAME}" matchType="IsFile" />
          </conditions>
          <action type="None" />
        </rule>
        <!-- 文章详情页 -->
        <rule name="Article Detail" stopProcessing="true">
          <match url="^article/([0-9]+)\.html$" />
          <action type="Rewrite" url="index.php?mod=article&amp;act=detail&amp;id={R:1}" appendQueryString="true" />
        </rule>
        <!-- 页面 -->
        <rule name="Page Detail" stopProcessing="true">
          <match url="^page/([a-zA-Z0-9_-]+)\.html$" />
          <action type="Rewrite" url="index.php?mod=page&amp;act=detail&amp;slug={R:1}" appendQueryString="true" />
        </rule>
        <!-- 分类与标签 -->
        <rule name="Category Detail" stopProcessing="true">
          <match url="^category/([a-zA-Z0-9_-]+)\.html$" />
          <action type="Rewrite" url="index.php?mod=category&amp;act=detail&amp;slug={R:1}" appendQueryString="true" />
        </rule>
        <rule name="Tag Detail" stopProcessing="true">
          <match url="^tag/([a-zA-Z0-9_-]+)\.html$" />
          <action type="Rewrite" url="index.php?mod=tag&amp;act=detail&amp;slug={R:1}" appendQueryString="true" />
        </rule>
      </rules>
    </rewrite>
    <!-- 禁止访问敏感目录/文件 -->
    <security>
      <requestFiltering>
        <hiddenSegments>
          <add segment="config.php" />
          <add segment="storage" />
        </hiddenSegments>
      </requestFiltering>
    </security>
  </system.webServer>
</configuration>
```

**动态生成配置文件**：

- 安装向导阶段 10 自动检测 Web 服务器类型（Nginx/Apache/IIS）
- 根据用户选择的伪静态规则，动态生成对应的配置文件
- Nginx：生成到 `install/nginx-rewrite.conf`，提示用户复制到 Nginx 配置目录
- Apache：直接写入根目录 `.htaccess`
- IIS：生成 `web.config` 示例，提示用户放置到站点根目录
- 后台"伪静态设置"页面提供"重新生成配置"按钮

### 4.17 AI 大模型设置

AI 大模型作为系统级能力进入 1.0 规划，采用 **`AiService` 门面 + AI Provider 插件** 模式：核心 `AiService` 只定义统一调用接口（`chat()`、模型选择、超时、日志、降级），**协议适配由 Provider 插件提供**。1.0 预装 `ai-openai` 插件（默认启用，兼容 OpenAI 协议），可被其他协议或自建网关插件替换/扩展。后台可以添加多个平台和多个模型，主题与插件接入后可选择调用不同平台的不同模型。

- Provider 插件通过 `fp_register_ai_provider` 注册自身支持的协议（如 `openai`），`system_setting.ai_provider` 指定默认协议 Provider。
- 主题/插件统一通过 `$fp->ai->chat()` 门面调用，**不感知底层协议实现**；停用所有 AI Provider 后门面返回"AI 未配置"错误，调用方需处理降级。
- `AiService` 门面、`ai_model` 表、密钥加密、日志策略属核心；具体 HTTP 协议适配属插件，二者分别维护。

**后台配置字段**：

| 字段 | 说明 |
| ------ | ------ |
| 平台名称 | 如 OpenAI、DeepSeek、通义千问兼容接口、本地网关等 |
| 协议 | **下拉项来自当前已注册并启用的 AI Provider 插件**，不由核心硬编码；1.0 预装 `ai-openai` 提供 `openai` 协议，安装其他协议插件后此处自动出现新选项 |
| 接口地址 | 由所选协议 Provider 决定格式（如 `openai` 协议为兼容 `base_url`：`https://api.example.com/v1`） |
| API Key | 加密保存到 `ai_model.api_key_encrypted`，后台列表不明文显示 |
| 模型名称 | 如 `gpt-4o-mini`、`deepseek-chat` 等，由用户填写 |
| 其他参数 | JSON/数组形式保存，如 temperature、max_tokens、timeout、代理、组织 ID 等；协议 Provider 可声明各自的附加字段提示 |
| 默认模型 | 可设置一个系统默认模型，插件/主题未指定时使用 |
| 状态 | 启用/禁用，禁用后不允许被调用 |
| 日志开关 | 调用摘要日志、详细内容日志、文件调试日志、日志保留天数 |

> **后台 AI 设置页（核心）与 `ai-openai` 插件（Provider）的职责边界**：后台页面只做「模型配置数据的增删改查 + 调用调度 + 日志」，是通用的、与协议无关的；`ai-openai` 插件只提供「`openai` 协议的 HTTP 实现」。后台「协议」下拉选项即已注册 Provider 列表，二者互补不重复——前者类比"数据库连接配置界面"，后者类比"PDO 的某个数据库驱动"。若某条模型记录引用的协议 Provider 被停用/删除，该模型在后台标记为"协议不可用"并禁止调用。

**调用约定**：

```php
// 概念示意：主题/插件可按模型 ID 或平台+模型名调用
$result = $fp->ai->chat($modelId, [
    ['role' => 'system', 'content' => '你是网站助手'],
    ['role' => 'user', 'content' => '生成文章摘要'],
], ['temperature' => 0.7]);
```

**设计原则**：

- 数据库以多行保存多个模型配置，`params_json` 保存额外参数；Service 层对外返回数组，便于后台、主题、插件选择。
- `api_key` 必须使用站点密钥派生的加密方式保存，日志和异常不得输出 Key。
- 插件/主题只能通过 `AiService` 调用模型，不直接读取 `ai_model` 表中的密钥。
- AI 调用应记录必要的错误日志和耗时，1.0 先不做用量计费，但预留调用统计 hook。

**超时与重试策略**：

- 后台默认超时：`connect_timeout = 10s`，`read_timeout = 120s`
- 默认**不重试**：避免对大模型 API 重复计费、重复生成内容
- 用户可在 `ai_model.params_json` 中针对单个模型覆盖超时值
- 超时时统一返回 `AiTimeoutException`，业务层可选择：给用户提示“请稍后重试”、降级到备选模型、或记录到队列稍后补调

**AI 日志策略**：不建议只放数据库，也不建议只放文件。最佳方案是“数据库存摘要，文件存调试详情”。

| 类型 | 存放位置 | 默认状态 | 内容 |
| ------ | ------ | ------ | ------ |
| 调用摘要日志 | `ai_log` 表 | 开启 | 模型 ID、调用用户、调用方类型（system/theme/plugin/module）、场景、状态、Token、耗时、错误码、请求摘要 hash |
| 详细内容日志 | `storage/logs/ai-{date}.log` | 关闭 | 脱敏后的 prompt/response 摘要、请求参数、接口返回摘要，仅排查问题时临时开启 |
| 错误日志 | `storage/logs/ai-{date}.log` | 开启 | 脱敏错误信息、超时、HTTP 状态、异常类型，不记录 API Key |

后台提供以下开关，保存到 `system_setting`：

- `ai_log_enabled`：是否写入 `ai_log` 调用摘要。
- `ai_log_payload_enabled`：是否记录 prompt/response 摘要，默认关闭。
- `ai_log_file_enabled`：是否写入 AI 文件调试日志，默认关闭；错误日志仍保留脱敏记录。
- `ai_log_retention_days`：日志保留天数，默认 30 天，后台可清理历史记录。

### 4.18 安全特性

| 特性 | 实现方式 |
| ------ | ---------- |
| CSRF 防护 | 表单生成 Token，提交时验证；会话级有效期，登录成功后刷新 Token + Session ID（防会话固定） |
| CSRF + AJAX | jQuery 全局 `ajaxSetup` 自动从 cookie 读取 `XSRF-TOKEN` 并附加到请求头 `X-XSRF-TOKEN` |
| XSS 过滤 | 输出时 `esc_html()` / `esc_attr()`；Quill 富文本经 HtmlCleaner 白名单清洗 |
| SQL 注入 | PDO 预处理语句（强制） |
| 密码哈希 | `password_hash()` / `password_verify()` |
| 密码策略 | 密码至少 8 位，必须同时包含字母和数字（不强制符号/大小写混合），存 `system_setting` |
| 登录限流 | 同 IP/账号连续 5 次登录失败锁定 10 分钟（`login_fail_count` / `locked_until`） |
| 验证码 | `CaptchaService` 门面 + 验证码 Provider 插件；预装 `captcha-gd`（GD 图形验证码）默认启用，可替换滑块/hCaptcha/reCAPTCHA |
| 文件上传 | 白名单扩展名 + MIME 类型验证 + EXIF 剥离（默认，可选保留） + 随机重命名 |
| 图片处理 | 上传时 GD2 强制重建 JPEG/PNG 元数据，默认丢弃所有 EXIF（可后台开启保留） |
| 远程图片 SSRF | 仅 HTTP/HTTPS + 域名白名单 + ≤2MB + 超时≤5s + 拦截私网 IP |
| 会话安全 | Cookie HttpOnly + Secure（自动检测 HTTPS） + SameSite=Lax 标记 |
| 日志脱敏 | 所有日志对 API Key / 密码 / 手机号 / 邮箱 脱敏，禁明文记录 |

**CSRF + AJAX 实现细节**：

后台和前台表单默认使用隐藏字段 `_token`，AJAX 场景采用 Laravel 风格的双重机制：

- Cookie `XSRF-TOKEN`：每次响应自动下发，前端 JS 可读取
- 后台 JS 全局 `$.ajaxSetup` 配置：自动把 cookie 里的 token 加到请求头 `X-XSRF-TOKEN`
- 后端中间件优先校验 `X-XSRF-TOKEN` 头，缺失时回退到 `_token` 表单字段
- 插件自定义 API 端点如需豁免 CSRF，显式声明 `$skipCsrf = true`

**文件上传完整流程**：

```text
UploadService::store($file):
  1. 校验扩展名白名单（jpg/jpeg/png/gif/webp/pdf/zip等）
  2. 校验 MIME 类型（finfo 探测，禁止扩展名与 MIME 不一致）
  3. 校验文件大小（≤ system_setting.upload_max_size，默认 10MB）
  4. 生成随机文件名：{date}_{random_hash}.{ext}
  5. 若为图片：GD2 重建 → 丢弃 EXIF → 重新保存（防止 EXIF 中藏恶意 PHP）
  6. 计算 SHA256 作为 content_hash（用于去重、缓存键）
  7. 写入 content/uploads/{year}/{month}/
  8. 插入 upload 表（id, user_id, filename, original_name, path, mime_type, extension, size, width, height, content_hash）
  9. 触发 fp_upload_saved 钩子
  10. 返回 Upload 实体（Service 成功返回实体，不返回 HTML/JSON）
```

**EXIF 剥离的必要性**：

用户手机拍摄的照片包含 GPS 坐标、拍摄时间、设备序列号等敏感信息。上传到公开网站时这些元数据会被保留，造成隐私泄露。GD2 重建图片（`imagejpeg` / `imagepng`）会完全丢弃 EXIF，同时清除藏在图片中的恶意代码（PHP-in-image），这是最简单可靠的做法。**默认开启剥离**；提供后台开关"是否保留图片 EXIF 信息"，满足摄影类网站需求。

**远程图片缩略图 SSRF 防护**：

`the_post_thumbnail()` 处理远程图片 URL 时必须：

- 仅允许 HTTP/HTTPS 协议，拒绝 `file://`、`gopher://` 等
- 域名白名单（默认仅常用图床，后台可扩展）
- 限制文件大小 ≤ 2MB，超时 ≤ 5 秒
- 解析目标 IP 后**拦截私网/保留段**：`10.0.0.0/8`、`172.16.0.0/12`、`192.168.0.0/16`、`127.0.0.0/8`、`169.254.0.0/16`、`::1` 等，防止 SSRF 攻击打内网
- 跨重定向时对每个跳转后的目标重新校验 IP

**密码策略与登录限流**：

- 密码至少 8 位，必须同时包含字母和数字（不强制符号/大小写混合）；配置存 `system_setting`。
- 同 IP / 账号连续 5 次登录失败后锁定 10 分钟，落地 `user` 表的 `login_fail_count` / `locked_until` 字段，登录成功后清零。

**会话固定与 Cookie 安全**：

- CSRF Token 采用会话级有效期，登录成功后同时刷新 CSRF Token 与 Session ID，防止会话固定攻击。
- 启动时自动检测是否 HTTPS（同时判断 `$_SERVER['HTTPS']` 与 `X-Forwarded-Proto` 以适配反代），是则自动将 Cookie `Secure` 置 true；保留 config 强制开关供特殊部署覆盖。

**安装后安全**：安装完成后生成 `install.lock` 防重复安装，并提示用户删除或重命名 `install/` 目录。

**日志脱敏**：所有日志（含访问/错误/安全/AI 日志）在写盘前对敏感信息（API Key、密码、手机号、邮箱、Token）统一脱敏，禁止明文记录。

**上传目录安全**：

Nginx / Apache 配置示例在安装完成后由 InstallService 写入 `content/uploads/.htaccess` 和 `install/nginx-uploads.conf.example`：

```apache
# content/uploads/.htaccess
Options -ExecCGI -Indexes
AddHandler default-handler .html .htm
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|phps|phar|inc)$">
    Require all denied
</FilesMatch>
```

```nginx
# install/nginx-uploads.conf.example
location ~* ^/content/uploads/ {
    location ~* \.(php|phtml|php3|php4|php5|php7|phps|phar|inc)$ {
        deny all;
    }
    autoindex off;
}
```

### 4.19 开发前二次审计：仍需补齐的约束

如果现在开始开发并完成后续 11 个阶段，当前方案还需要补齐以下约束。它们不是功能愿望，而是会直接影响代码结构、数据库、接口兼容和安全边界的开发前提。

#### 4.19.1 阶段 1 已采纳的冻结规则

以下 5 项作为阶段 1 的硬性规则，后续编码、目录、类名、安装器和后台/API 都必须按此执行。

**命名空间与自动加载**：统一使用 `Finch\` 作为根命名空间，自实现 PSR-4 自动加载，不依赖 Composer。根映射固定为 `Finch\` => `system/`。

| 目录 | 命名空间 | 示例类 |
| ------ | ------ | ------ |
| `system/App.php` | `Finch\` | `Finch\App` |
| `system/Core/` | `Finch\Core\` | `Finch\Core\Router` |
| `system/Controller/` | `Finch\Controller\` | `Finch\Controller\PostController` |
| `system/Service/` | `Finch\Service\` | `Finch\Service\PostService` |
| `system/Model/` | `Finch\Model\` | `Finch\Model\Post` |
| `system/Database/` | `Finch\Database\` | `Finch\Database\Mysql` |
| `system/Api/` | `Finch\Api\` | `Finch\Api\PostApi` |
| `system/Admin/` | `Finch\Admin\` | `Finch\Admin\PostAdmin` |

全局函数只允许两类：模板标签函数（如 `title()`、`content()`、`the_post_thumbnail()`）和极少量通用助手函数。核心业务逻辑不得写成全局函数。

**配置结构**：`config.php` 必须返回数组，只允许 `Finch\Core\Config` 读取。业务代码不得直接 `include config.php`。

```php
return [
    'app' => [
        'key' => 'random-secret',
        'debug' => false,
        'installed' => true,
        'timezone' => 'UTC',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'prefix' => 'fp_',
        'charset' => 'utf8mb4',
    ],
    'cookie' => [
        'prefix' => 'fp_',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];
```

数据库连接、表前缀、站点密钥、安装状态、时区、Cookie 安全参数属于安装级配置，保存在 `config.php`。后台可修改的站点标题、语言、评论开关、固定链接、上传限制、邮件 SMTP、AI 模型、主题配置、插件配置等运行期设置必须保存在独立数据表，不写入 `config.php`。

**环境变量覆盖（可选层）**：`Finch\Core\Config` 读取 `config.php` 后，允许同名环境变量（如 `FINCH_DB_HOST`、`FINCH_DB_PASSWORD`）覆盖对应项，方便容器化/CI 部署；虚拟主机用户无需设置环境变量，仍以 `config.php` 为准。环境变量为可选增强，不强制依赖。

**启动健壮性**（启动流程的硬约束）：

- 时区合法性校验：`config.php` 的 timezone 非法时 fallback 到 UTC 并写警告日志。
- 安装中断检测：`config.php` 存在但 `installed !== true` 时跳安装向导并提示"检测到未完成安装"，不抛数据库错误。
- 启动失败降级：核心组件（DB 连接等）初始化失败显示友好维护页，不暴露堆栈。
- `$fp` 核心属性对外只读（`__get` 暴露、禁止 `__set`）。

**环境检查**：安装向导将检查项分为必需、权限、可选三类。

| 类型 | 检查项 | 处理方式 |
| ------ | ------ | ------ |
| 必需扩展 | PHP >= 8.0、PDO、pdo_mysql 或 pdo_sqlite 至少一个、json、mbstring、session、openssl、fileinfo、gd、curl | 缺失则阻断安装 |
| 目录权限 | 根目录写入 `config.php`、`content/uploads/`、`storage/cache/`、`storage/logs/`、`storage/data/` 可写 | 缺失则阻断安装 |
| 可选扩展 | Imagick、OPcache、ZipArchive | 缺失只提示警告，不阻断安装 |

**错误处理**：`Finch\Core\ErrorHandler` 负责捕获异常和 PHP 错误，`Finch\Core\Logger` 只负责写日志。业务代码不得直接写日志文件。

| 模式 | 行为 |
| ------ | ------ |
| 开发模式 | 显示错误详情、异常堆栈、完整日志，可开启调试输出 |
| 生产模式 | 不显示路径、SQL、密钥、堆栈；Web 显示友好错误页；API 返回统一 JSON 错误结构 |

日志文件按用途和日期拆分：

```text
storage/logs/error-{date}.log
storage/logs/security-{date}.log
storage/logs/api-{date}.log
storage/logs/install-{date}.log
storage/logs/ai-{date}.log
```

**Controller / Service / Model 边界**：严格三层，防止控制器和模型膨胀。

| 层 | 允许做 | 禁止做 |
| ------ | ------ | ------ |
| Controller | 读取 Request、调用 Validation、检查权限、调用 Service、返回 Response/View/JSON | 不直接写 SQL、不处理复杂业务、不操作多模型事务、不处理上传细节 |
| Service | 处理业务流程、事务、跨模型协作、缓存失效、插件/主题/模块生命周期 | 不直接读取 `$_GET`/`$_POST`、不输出 HTML/JSON |
| Model | 字段映射、CRUD、查询作用域、简单关联、类型转换、软删除 | 不处理 Request、不判断后台权限、不输出响应、不处理跨模型复杂流程 |

文章保存的标准调用链：

```text
PostController::store()
  -> validate()
  -> authorize('publish_post')
  -> PostService::createPost($data)
      -> beginTransaction()
      -> Post::create()
      -> PostCategory::sync()
      -> PostTag::sync()
      -> update category/tag post_count
      -> clear cache
      -> commit()
  -> redirect/json
```

**编码规范**：全面使用 PHP 8.0 语法，如构造器属性提升、联合类型、`match` 表达式、nullsafe 运算符 `?->`、命名参数、Attributes，文件首行声明 `declare(strict_types=1)`。数组使用短数组语法，类文件名与类名一致，公共方法命名使用小驼峰。

#### 4.19.2 数据库与模型还需细化

| 约束 | 需要讲清楚的细节 | 未明确的风险 |
| ------ | ------ | ------ |
| 字段类型映射 | MySQL 与 SQLite 的 int、text、datetime、json/meta 存储映射；时间字段统一格式 | 两种数据库行为不一致，迁移脚本难维护 |
| 外键策略 | 是否使用数据库外键，还是应用层维护关联；删除文章、分类、用户、上传时如何处理关联数据 | 删除产生孤儿数据，或 SQLite/MySQL 行为差异过大 |
| 软删除策略 | 哪些表有 `deleted_at`，哪些必须物理删除；后台回收站是否 1.0 实现 | 列表查询、统计、恢复逻辑反复返工 |
| Slug 唯一规则 | 文章、页面、分类、标签的 slug 生成、冲突后缀、大小写、中文转拼音/保留中文 | URL 冲突，伪静态和 API 详情页行为不稳定 |
| Migration 规则 | 迁移文件命名、batch、失败回滚、重复执行保护、升级前备份提示 | 版本升级时数据库半更新，难以恢复 |
| Meta 使用边界 | 哪些字段禁止进入 meta；模块自定义高频字段何时必须独立建表 | 重走 WordPress 的 meta 性能老路 |

#### 4.19.3 路由、API、后台需要补充契约

| 约束 | 需要讲清楚的细节 | 未明确的风险 |
| ------ | ------ | ------ |
| 路由命名与优先级 | active/rewrite/default 的匹配顺序、冲突处理、404、分页 URL、预览 URL | 伪静态规则和动态规则互相覆盖 |
| API HTTP 契约 | GET/POST/PUT/PATCH/DELETE 对应关系；HTTP 状态码；错误码表；分页参数名；排序/筛选参数 | 小程序/App 对接时接口不稳定，后期难兼容 |
| API Token 能力 | token 存储 hash、过期时间、能力范围、撤销机制、最后使用时间 | Token 泄露后不可控，权限粒度不清 |
| 后台表单契约 | CSRF 字段名、表单错误展示、批量操作、分页尺寸、移动端表格横向滚动规则 | 后台每个页面交互不一致 |
| Quill 内容安全 | 允许的 HTML 标签和属性、图片上传入口、外链图片处理、保存前清洗 | 编辑器内容引入 XSS 或脏 HTML |

#### 4.19.4 插件、主题、模块需要补充生命周期

| 约束 | 需要讲清楚的细节 | 未明确的风险 |
| ------ | ------ | ------ |
| 插件元信息 | 插件名称、ID、版本、作者、最低 Finch/PHP 版本、依赖插件、入口函数 | 后台无法判断兼容性和依赖关系 |
| 插件生命周期 | install、activate、deactivate、uninstall 的调用时机；卸载是否删除数据 | 插件残留表/配置，或误删用户数据 |
| 插件配置菜单 | 默认配置页、顶部菜单/左侧栏/系统设置/隐藏入口、图标、排序、权限能力 | 插件入口混乱，后台菜单不可控，权限边界不清 |
| 插件数据库迁移 | 插件/模块是否允许自带 migration；版本升级如何执行 | 插件一升级就需要手写 SQL，破坏核心迁移体系 |
| 主题元信息 | `theme.json` 字段、截图尺寸、支持的模板、支持的语言、最低版本 | 主题列表、切换、预览无法统一实现 |
| 静态资源加载 | 系统、后台、主题、插件的 CSS/JS 加载顺序；jQuery 是否默认加载或按需加载 | 重复加载、版本冲突、页面脚本顺序错误 |
| Hook 契约 | 每个核心 hook 的参数、返回值、是否可中断、默认优先级 | 插件互相影响，行为不可预测 |

#### 4.19.5 安全、缓存、上传、验收标准还需明确

| 约束 | 需要讲清楚的细节 | 未明确的风险 |
| ------ | ------ | ------ |
| 上传安全 | 允许扩展名、最大尺寸、随机文件名、MIME 校验、禁止上传目录执行 PHP、Nginx/Apache 示例 | 文件上传成为最高风险入口 |
| 远程缩略图安全 | `the_post_thumbnail()` 允许远程 URL 时，需要限制协议、超时、文件大小、私网 IP，防 SSRF | 用户传内网地址或大文件导致安全/性能问题 |
| 缓存失效 | 文章、分类、标签、评论、设置、主题、插件变更时清哪些缓存 | 页面显示旧内容，后台改动不生效 |
| Session/Cookie | Cookie 名称、SameSite、Secure、HttpOnly、过期时间、后台登录有效期 | 登录态不稳定，或跨站风险增加 |
| 安装锁 | 安装完成后如何禁止重复安装；`install/` 是否提示删除或写入 lock 文件 | 站点可能被二次安装覆盖 |
| 验收标准 | 每个阶段完成后必须通过哪些检查：安装、登录、发文、分类、标签、评论、上传、API、主题切换 | 11 个阶段完成后仍不知道是否真正可用 |

#### 4.19.6 可以后续细化，但需要预留接口

- 搜索策略：核心 `SearchService` 门面 + 搜索 Provider 插件；预装 `search-like` 插件（SQL `LIKE`）默认启用，可替换为全文索引/Meilisearch/ES 等插件。无可用 Provider 时门面回退到核心内联最简 `LIKE` 兜底，保证搜索始终可用。
- 队列/计划任务：1.0 可以不做队列，但应预留 `cron`/伪 cron 钩子，用于清理缓存、过期验证码、统计任务。
- 备份恢复：1.0 可先做手动备份文档，但数据库迁移前应提示用户备份。
- 统计系统：流量统计后续做插件，但核心应预留页面访问事件 hook。
- 安全防护插件：后续实现，但登录失败、短信发送、API Token 校验需要从 1.0 开始记录必要日志。

### 4.20 开发启动评估：可以开始，但需冻结高返工点

当前规划已经可以进入编码，但建议先从**阶段 1 骨架**和**阶段 2 数据库层**开始，不要立刻铺完整后台页面、主题生态和插件生态。可先实现：`bootstrap.php`、自实现 PSR-4、`Config`、`App`、`Request`、`Response`、`Logger`、`ErrorHandler`、`Session`、数据库驱动接口、Query Builder、Migrator 和初始 Schema。

暂缓大规模实现的部分：完整后台 14 个页面、全部 API 端点、插件/模块复杂生命周期、主题自定义器、AI 实际调用界面。这些可以先写接口和空壳，等下面的约束落到代码后再填业务。

| 高返工点 | 如果现在直接写，可能返工的原因 | 开发前冻结约束 |
| ------ | ------ | ------ |
| 类名与命名空间 | 文档中曾混用 `FP_*` 类名和 `Finch\` 命名空间，后期统一会大面积改文件名和引用 | 实际类名一律使用 `Finch\...` 命名空间；`FP_` 只保留给常量、表前缀、Hook 名和兼容型标识，不作为类名前缀 |
| 数据库字段类型 | MySQL 与 SQLite 类型差异大，先写 SQL 后补兼容会返工迁移 | Schema 以迁移类为第一来源；MySQL/SQLite 映射必须由 `Schema`/`Migrator` 统一生成或统一维护，禁止业务代码手写建表 SQL |
| 时间与时区 | 后台显示、API 返回、缓存过期如果混用本地时间会混乱 | 数据库统一存 UTC `Y-m-d H:i:s`；站点时区保存到 `system_setting.site_timezone`，只在显示和输入解析时转换 |
| JSON 与设置值 | 后台配置、主题配置、插件配置、AI 参数容易变成序列化大杂烩 | 设置表的 `type` 固定为 `string/int/bool/float/json/text/secret`；`secret` 必须加密；`json` 必须由 `SettingService` 编解码和校验 |
| 外键与删除 | MySQL/SQLite 外键行为差异会导致升级和删除逻辑返工 | 1.0 默认不依赖数据库外键，统一由 Service 层事务维护关联；所有关联表必须有唯一索引和反向索引 |
| 软删除 | 回收站、统计和列表筛选如果后补，会影响所有查询 | 1.0 仅 `post`、`comment` 使用 `deleted_at` 软删除；分类、标签、设置、迁移记录不软删除；用户用 `status` 禁用，不物理删除登录历史 |
| Slug 与 URL | 伪静态、API 详情、预览链接都依赖 slug 规则 | slug 统一小写，空 slug 先由标题生成，仍为空则用 `{type}-{id}`；冲突追加 `-2`、`-3`；文章 slug 唯一范围为 `type + slug`，分类/标签 slug 全局唯一 |
| 路由与控制器 | 后台、前台、API 如果各写一套路由，后续统一会很痛 | 所有路由必须有唯一 route name；handler 使用 `Finch\Controller\Class::method` 或可调用数组；URL 生成只能通过 route name，不在业务里拼 URL |
| API 契约 | 小程序/App 一旦接入，响应格式再改会破坏兼容 | API 成功固定 `{code,message,data,meta}`；错误固定 `{code,message,errors}`；分页参数固定 `page/per_page`；排序参数固定 `sort/order`；Token 只存 hash |
| 后台表单 | 每个页面各自处理 CSRF、错误和批量操作会返工交互 | 后台表单固定 `csrf_token` 字段；非 POST 方法用 `_method` 覆盖；批量 ID 字段固定 `ids[]`；字段错误统一返回 `errors[field][]` |
| Service 返回值 | Service 若混用数组、bool、HTML、Response，控制器会越来越乱 | Service 成功返回实体/DTO/数组数据，不输出 HTML/JSON；业务失败抛统一业务异常或验证异常，由 Controller/ErrorHandler 转响应 |
| 插件生命周期 | 先随意 include 插件，后续补安装/卸载会破坏生态 | `include.php` 只声明元信息、注册钩子和返回配置，不直接输出内容；install/activate/deactivate/uninstall 顺序固定；卸载默认保留数据，用户确认后才删除 |
| 插件/模块迁移 | 插件升级一旦手写 SQL，核心迁移体系会被绕开 | 插件/模块迁移文件必须走同一个 Migrator；迁移记录带 owner_type 和 owner_id，避免和核心迁移混在一起 |
| 静态资源加载 | 后台、主题、插件重复引 jQuery/Tabler 会产生冲突 | 资源必须通过 `Asset` 注册 handle、依赖和版本；核心 handle 固定为 `jquery`、`jquery-form`、`jquery-cookie`、`tabler`、`quill`、`admin` |
| AI 调用与日志 | 先把 AI 调用散落到插件/主题，后续很难统一 Key、超时和日志 | 主题/插件只能通过 `AiService` 调用；默认超时、重试、日志脱敏由核心统一；prompt/response 详细日志默认关闭 |
| 缓存失效 | 后台改设置、切主题、启插件后页面不刷新会难排查 | 缓存 key 必须带分组；文章/分类/标签/评论/设置/主题/插件变更必须触发对应缓存组失效 |
| 安装与升级 | 安装器、迁移、初始数据如果分散写，升级会返工 | 安装只执行迁移和 seed；`config.php` 只写安装级配置；后台可改配置通过 seed 写入设置表 |

**开发启动顺序约束**：

1. 先写能跑通请求生命周期的最小闭环：`index.php` → `bootstrap.php` → `App::init()` → `Router` → `Response`。
2. 再写数据库最小闭环：`Driver` → `Query` → `Migrator` → 初始 migration → 安装器执行迁移。
3. 再写设置最小闭环：`Config` 读取安装级配置，`SettingService` 从数据库读写运行期配置并处理类型转换。
4. 再写后台登录和系统设置，不先写文章编辑器；因为文章编辑依赖分类、标签、上传、Quill 清洗、权限、缓存失效。
5. 插件、主题、AI、邮件先写 Service 接口和后台配置页面，业务细节放到核心 CRUD 跑通之后。

---

## 五、安装流程

1. 上传程序到网站目录
2. 访问首页，检测无 `config.php` → 跳转 `install/`
3. 安装向导：
    - 步骤 1：环境检查（PHP 版本、扩展、目录权限）
    - 步骤 2：数据库配置（选择类型 MySQL / SQLite + 填写连接信息）
    - 步骤 3：创建数据表并写入初始 migration 版本
    - 步骤 4：设置管理员账号密码 + 站点基本信息
4. 释放并激活预装插件（`tabler` / `quill` / `social-github` / `notify-sms` / `notify-email` / `captcha-gd` / `search-like` / `ai-openai`），写入对应 `system_setting` 默认 Provider
5. 写入 `config.php`
6. 安装完成

---

## 六、开发阶段规划

### 阶段 1：骨架搭建（核心基础设施）

- [ ] 目录结构创建（system/ content/ storage/ install/）
- [ ] `system/bootstrap.php` 启动引导
- [ ] `config.php` 安装级配置加载与生成
- [ ] `Finch\App` 单例类
- [ ] `Finch\Core\Request` / `Finch\Core\Response` 请求响应基础类
- [ ] `Finch\` 根命名空间 + PSR-4 风格自动加载器（自实现，不依赖 Composer）
- [ ] 常量定义（FP_PATH / FP_CONTENT_DIR / FP_STORAGE_DIR）
- [ ] 命名空间映射：`Finch\` => `system/`
- [ ] `Config` 类读取数组式 `config.php`，业务代码禁止直接 include 配置文件，运行期设置交给 `SettingService`
- [ ] `Logger` / `ErrorHandler` / `Session` 基础类
- [ ] 开发模式与生产模式的错误输出和日志策略
- [ ] Controller / Service / Model 三层边界落实

### 阶段 2：数据库层

- [ ] `Finch\Database\Driver` 接口
- [ ] 2 种 PDO 驱动实现
- [ ] `Finch\Database\Query` SQL 构建器
- [ ] `Migrator` 数据库迁移执行器
- [ ] `migration` 表与初始版本记录
- [ ] 初始 migration + 建表 SQL（2 种数据库）
- [ ] Schema 类型映射：MySQL/SQLite 时间、JSON、索引、唯一约束
- [ ] `system_setting` / `theme_setting` / `plugin_setting` / `module_setting` 独立配置表
- [ ] `ai_model` 多平台大模型配置表与 Key 加密字段
- [ ] `ai_log` 调用摘要日志表与清理索引
- [ ] MySQL/SQLite 索引、唯一约束与事务行为验证
- [ ] 外键/应用层关联维护策略
- [ ] Migration 失败回滚与重复执行保护

### 阶段 3：数据模型 + ORM

- [ ] `Finch\Model\BaseModel` 基类
- [ ] 内容、用户、系统核心 Model 类实现
- [ ] `SystemSetting` / `ThemeSetting` / `PluginSetting` / `ModuleSetting` / `AiModel` 模型实现
- [ ] `SettingService` 类型转换、autoload 缓存、secret 加密读取
- [ ] 基本 CRUD 操作
- [ ] Controller / Service / Model 职责边界落实
- [ ] 字段 cast、时间格式、软删除、批量赋值白名单
- [ ] 分类/标签关联保存与 `post_count` 事务维护

### 阶段 4：钩子系统 + 插件基础设施

- [ ] `Finch\Core\Hook` 类
- [ ] 插件注册/激活/停用
- [ ] 关键钩子点铺设
- [ ] 插件元信息格式与兼容性检查
- [ ] 插件默认配置页、顶部菜单/左侧栏/系统设置/隐藏入口注册规则
- [ ] install / activate / deactivate / uninstall 生命周期
- [ ] Hook 参数、返回值、中断信号、优先级文档

### 阶段 5：路由 + 视图

- [ ] `Finch\Core\Router` 三级路由
- [ ] 6 个默认控制器动作（index/single/page/category/tag/search）
- [ ] route name 唯一规则与 URL 生成器
- [ ] URL 规则生成
- [ ] 404、分页、预览、slug 冲突处理规则
- [ ] 动态 URL 与伪静态 URL 的互相生成与回退
- [ ] Apache/Nginx 伪静态示例规则

### 阶段 6：模板系统

- [ ] 模板加载器
- [ ] 模板函数标签库
- [ ] 默认主题模板
- [ ] 模板变量作用域与输出转义规则
- [ ] `the_post_thumbnail()` 本地/远程图片安全约束
- [ ] 主题 `theme.json`、截图、语言包、模板优先级规则

### 阶段 7：后台管理

- [ ] 14 个后台页面
- [ ] 后台权限控制
- [ ] 用户中心：密码登录 + 手机验证码 + GitHub OAuth + 账号绑定页
- [ ] 功能模块管理：启用/禁用官方模块
- [ ] 伪静态设置：预设规则 + 自定义 URL
- [ ] 邮件设置：SMTP / PHP mail 降级 / 发件人配置 / 测试发送
- [ ] AI 大模型设置：OpenAI 协议平台、接口地址、Key、模型名、参数、默认模型、日志开关
- [ ] 系统配置、主题配置、插件配置分别写入独立设置表
- [ ] 后台表单 CSRF、错误提示、批量操作、分页规则
- [ ] Tabler 响应式断点、移动端表格横向滚动规则
- [ ] Quill 保存前 HTML 清洗与上传入口约束

### 阶段 8：API 系统

- [ ] API 入口 + Token 认证
- [ ] 8 个内置 API 模块
- [ ] RESTful 版本化路由（`/v1/posts/1`）
- [ ] ApiResource 统一响应 + 分页 meta
- [ ] CORS 中间件
- [ ] 中间件管道（Auth / Role）
- [ ] 输入验证层（声明式规则校验）
- [ ] 社会化登录 API 扩展点
- [ ] HTTP Method、状态码、错误码、分页参数、排序/筛选参数契约
- [ ] API Token hash 存储、能力范围、过期、撤销、最后使用时间
- [ ] CORS 白名单、Token 权限与频率限制预留

### 阶段 9：功能模块框架

- [ ] 模块注册/激活/路由注入机制
- [ ] 模块自带模板自动加载
- [ ] 安装向导中可选模块预选启用
- [ ] 模块元信息、依赖、最低版本、启用/停用生命周期
- [ ] 模块自带 migration 与独立数据表规则
- [ ] 模块与插件共用接口但后台管理入口分离

### 阶段 10：安装向导

- [ ] 环境检测
- [ ] 数据库配置
- [ ] 初始化安装
- [ ] 写入安装级 `config.php`，后台可改配置初始化到数据库设置表
- [ ] 必需 PHP 扩展与目录权限检测
- [ ] `config.php` 写入失败时的手动复制方案
- [ ] 安装完成写入 install lock，防止重复安装

### 阶段 11：补充安全、缓存、优化

- [ ] CSRF、XSS 全面覆盖
- [ ] 文件缓存系统
- [ ] 代码审查 + 性能调优
- [ ] 上传目录禁止执行 PHP 的 Apache/Nginx 示例
- [ ] AI Key 加密存储、日志脱敏、调用超时、日志开关与错误处理
- [ ] 缓存 key、缓存分组、失效事件、清理策略
- [ ] 全阶段验收清单：安装、登录、发文、分类、标签、评论、上传、API、主题切换

---

## 七、版本号策略

遵循语义化版本（SemVer），格式 `主版本.次版本.修订版`（MAJOR.MINOR.PATCH）：

| 变更类型 | 规则 | 示例 |
| ---------- | ------ | ------ |
| 修订版（PATCH） | Bug 修复、性能优化、不改变 API | 1.0.0 → 1.0.1 |
| 次版本（MINOR） | 新增功能、向后兼容的改进 | 1.0.0 → 1.1.0 |
| 主版本（MAJOR） | 颠覆性升级、不向后兼容的 API 变更 | 1.x.x → 2.0.0 |

首发版本从 **1.0.0** 开始，每个开发阶段完成后按变更性质递增对应版本号。

**预发布版本标识**：正式发布前支持预发布标识，如 `1.0.0-alpha.1`、`1.0.0-beta.1`、`1.0.0-rc.1`，用于内部测试和早期用户反馈；预发布版本不视为稳定版，可包含未冻结的接口。

**版本路线图**：

| 版本 | 内容 |
| ------ | ------ |
| **1.0** | 核心 CMS + 用户中心（密码登录 + 手机验证码 + GitHub 登录）+ 伪静态/自定义 URL + 功能模块框架 + RESTful API（版本化/CORS/资源/分页）+ 中间件管道 + 输入验证 + 模型关联 + 独立设置表 + 邮件服务 + AI 大模型设置；**安全基线 + 架构预留字段/接口**（见第九节 9.1） |
| **1.1** | QQ/微信登录 + 社区模块（community，topic 类型 + 评论）+ 服务提供者机制（框架化转型）+ **审计增强项**（见第九节 9.2）：嵌套预加载、view_count 批量同步、异地登录检测、后台操作日志独立表、邮件模板后台编辑、批量操作 UI、核心升级脚本/回滚、节流中间件 |
| **1.2** | 商城模块（shop，product 类型 + 简易下单）+ OpenAPI 文档自动生成 |
| **1.3** | 问答模块（qa，question 类型 + 状态流转） |
| **2.0** | 主题可视化自定义器框架（颜色/字体/布局所见即所得） |

---

## 八、已定案项

- ✅ 文章类型扩展：不保留 Z-Blog 的 0-255 数字类型体系；1.0 内置 `article` / `page`，后续插件/模块通过语义化字符串类型注册扩展
- ✅ 前端构建：1.0 不引入 Node/NPM/Vite/Webpack，后台核心 CSS/JS 原生手写维护；jQuery/jquery.form.js/jquery.cookie.js 等核心 JS 库以静态资源内置于 `system/script/`，后台基础 CSS/IMG 放 `system/assets/`；Tabler/Quill 作为预装插件，其静态资源随插件位于 `content/plugins/tabler/` 与 `content/plugins/quill/`
- ✅ 多语言：1.0.0 首发 zh-cn / zh-tw / en，其余 11 种后续补齐
- ✅ 数据库：MySQL + SQLite，砍掉 PgSQL
- ✅ PHP 版本：最低 PHP 8.0，全面使用 PHP 8 语法（构造器属性提升、联合类型、`match`、nullsafe、命名参数、Attributes）并默认严格类型，无 PHP 7.x 兼容包袱
- ✅ 命名空间：统一 `Finch\` 根命名空间，自实现 PSR-4，不依赖 Composer
- ✅ 配置结构：`config.php` 返回数组，只保存安装级必要信息，只由 `Finch\Core\Config` 读取
- ✅ 运行期设置：系统配置、主题配置、插件配置、模块配置分别存入独立数据表，不写入 `config.php`
- ✅ 环境检查：安装向导分必需扩展、目录权限、可选扩展三类检查
- ✅ 错误处理：开发/生产模式分离，`ErrorHandler` 捕获错误，`Logger` 写入分类日志
- ✅ 分层边界：Controller 只协调请求，Service 承载业务流程，Model 负责数据映射和单表行为
- ✅ 分类/标签：分类表与标签表分离，避免标签膨胀影响分类性能
- ✅ 数据库迁移：1.0 内置 migration 表与 Migrator
- ✅ JS 基础库：系统内置 jQuery、jquery.form.js、jquery.cookie.js，放在 `system/script/`，后台与前端主题/插件均可按需引用
- ✅ 邮件服务：业务服务层内置 `MailService`，SMTP 配置保存到 `system_setting`
- ✅ 插件配置入口：插件可声明默认配置页显示在顶部菜单、左侧栏、系统设置下或隐藏
- ✅ AI 大模型：后台可配置多个 OpenAI 协议兼容模型，主题和插件通过 `AiService` 按需选择调用
- ✅ 模板标签：常用模板标签去掉 `the_` 前缀，缩略图函数按 `the_post_thumbnail($imagePath, $width, $height)` 单独保留
- ✅ 伪静态：核心内置，不放插件
- ✅ 社会化登录：1.0 做密码+短信+GitHub；QQ/微信延至 1.1
- ✅ 商城/社区/问答：统一为功能模块机制，分期交付（1.1~1.3）
- ✅ 媒体管理：简易上传列表，不做独立媒体库
- ✅ 后台样式与编辑器插件化：Tabler（后台样式）与 Quill（文章编辑器）作为预装插件随核心发布、默认启用，统一位于 `content/plugins/`（标记为不可删除、随核心升级更新），通过「后台样式提供者」「编辑器提供者」插槽对外暴露，用户/开发者可开发同类插件替换（`system_setting.admin_style` / `system_setting.editor` 指定启用项，缺失时回退）
- ✅ 可选能力插件化（门面 + Provider 插槽）：社会化登录（`social-github`）、短信通知（`notify-sms`）、邮件通知（`notify-email`）、图形验证码（`captcha-gd`）、搜索（`search-like`）、AI（`ai-openai`）均为预装插件，随安装释放到 `content/plugins/`、默认启用、可停用/删除/替换；核心只保留 `SocialLoginService`/`SmsService`/`MailService`/`CaptchaService`/`SearchService`/`AiService` 门面，Provider 缺失时按规则降级（搜索/验证码/邮件有核心保底，短信/AI/社会化登录优雅禁用入口）。预装插件统一位于 `content/plugins/` 集中管理，按“是否可删除”区分：Tabler/Quill 不可删除，其余用户完全可控。`MailService` 门面（含 PHP mail() 保底）/HtmlCleaner/缩略图/评论等核心依赖与安全基线不做成可停用插件
- ✅ 维护模式：后台"开启维护模式"开关（存 `system_setting`），开启后前台显示维护页、仅管理员可入后台，供升级时使用
- ✅ 环境变量覆盖：`Config` 允许同名环境变量覆盖 `config.php`（容器化友好），作为可选层不强制
- ✅ `storage/data` 用途：队列任务 / 临时备份 / 导出数据等运行时持久化数据，**禁止存放用户上传文件**（上传走 `content/uploads/`）
- ✅ 批量导入预留：`PostService` / `UserService` 等核心 Service 预留批量导入方法，为后续 Z-Blog/WP 数据导入插件提供支持
- ✅ 后台搜索与批量：1.0 提供文章按标题/内容搜索 + 文章核心批量操作（依托 `ids[]`）；用户/评论搜索与完整批量 UI 延至 1.1
- ✅ 测试与兼容矩阵：发布前覆盖单元/集成/性能/安全/兼容测试，兼容矩阵含 PHP 8.0/8.1/8.2/8.3、MySQL 5.7/8.0、Nginx/Apache/IIS
- ✅ 预发布版本号：支持 `-alpha.N` / `-beta.N` / `-rc.N` 预发布标识

---

## 九、审计决策留痕（否决项与冲突定夺）

> 第三轮审计（豆包建议）中**采纳的 1.0 项已直接落实到上文各架构章节**（4.x），**缓期项已并入「七、版本号策略」的版本路线图**，本文不再重复罗列以免与正文产生双份事实来源而跑偏。
> 本节仅保留两类**需要长期备查的决策记录**：①被否决的建议及其理由（防止后续重复提出）；②文档内部冲突的最终定夺。

### 9.1 否决项（建议有技术错误或与既定架构冲突）

- ❌ **组合索引"高基数列前置"**：SQL 理解有误。组合索引应"等值过滤列在前、范围/排序列在后"，`post(type, status, published_at)` 用于 `WHERE type=? AND status=? ORDER BY published_at` 本就正确；改为 `published_at` 前置反而无法走等值过滤。**保留原索引设计**。
- ❌ **DB 层 `ON UPDATE CURRENT_TIMESTAMP` / 时间字段 DB 默认值**：与"应用层统一存 UTC + ORM 维护时间戳"冲突，且 SQLite 不支持该语法。**时间戳统一由 ORM 维护**，不用 DB 默认值，保证 MySQL/SQLite 行为一致。
- ❌ **全字段一刀切避免 NULL**：`deleted_at` / `parent_id` / `published_at` 等语义上必须可空。仅采纳"业务字段尽量非 NULL（字符串默认 `''`、数值默认 `0`）"，保留语义性可空字段。

### 9.2 文档内部冲突定夺

- ⚠️ **`remember_token` 存储方式冲突**：PROJECT_PLAN 用独立表、IMPLEMENTATION_PLAN 用 `user.remember_token` 单字段。要支持"单用户最多 5 个设备 Token"必须用独立表。**已统一定为独立 `remember_token` 表**（含 token 哈希、user_id、设备/UA、IP、过期时间），IMPLEMENTATION_PLAN 已同步修正。

---
*本文档为规划草案，根据用户反馈持续修订。确认后进入逐阶段实施。*
