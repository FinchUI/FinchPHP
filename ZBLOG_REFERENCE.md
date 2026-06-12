# Z-BlogPHP 项目自述（v1.7.5.3540 Optimus）

## 一、项目概要

Z-BlogPHP 是由 Z-Blog 社区提供的开源博客/CMS 程序，始于 2005 年（ASP 版），PHP 版自 2013 年起发布。定位为"简单易用、体积小、速度快、支持数据量大"的博客系统，同时具备强大的可定制性、丰富的插件接口和独立的主题模板系统。

- **官网**: https://www.zblogcn.com
- **GitHub**: https://github.com/zblogcn/zblogphp
- **运行环境**: PHP 5.2 ~ 8.4, MySQL 5+ / MariaDB 10+ / SQLite 3 / PostgreSQL
- **Web Server**: IIS / Apache / nginx / Lighttpd / Kangle / Tengine / Caddy

## 二、目录结构

```
/                          # 站点根目录
├── index.php              # 主入口（博客首页）
├── feed.php               # RSS/Atom Feed 入口
├── search.php             # 搜索页入口
├── zb_install/            # 安装向导
│   ├── index.php
│   └── language/          # 安装界面语言包
├── zb_system/             # 系统核心目录
│   ├── api.php            # REST API 入口
│   ├── cmd.php            # 命令路由（action 分发中枢）
│   ├── login.php          # 登录页入口
│   ├── function/          # 核心函数与类库
│   │   ├── c_system_base.php       # 系统初始化（入口引导）
│   │   ├── c_system_common.php     # 通用辅助函数（HTTP/系统检测等）
│   │   ├── c_system_compat.php     # PHP 版本兼容层
│   │   ├── c_system_defined.php    # 常量/全局定义
│   │   ├── c_system_plugin.php     # 插件系统核心
│   │   ├── c_system_debug.php      # 调试/错误处理
│   │   ├── c_system_function.php   # 功能性函数（GetPost, GetList 等高层 API）
│   │   ├── c_system_route.php      # 路由/控制器（ViewAuto, ViewIndex, ViewPost 等）
│   │   ├── c_system_event.php      # 事件操作（登录、发表、评论等核心业务逻辑）
│   │   ├── c_system_api.php        # API 层函数（验证、加载 Mods）
│   │   ├── c_system_admin.php      # 后台管理入口函数
│   │   ├── c_system_admin_function.php  # 后台管理功能函数
│   │   ├── c_system_misc.php       # 杂项操作（统计、更新检测等 AJAX 操作）
│   │   ├── c_system_version.php    # 版本号定义
│   │   └── lib/                    # 类库
│   │       ├── zblogphp.php        # ZBlogPHP 全局单例类（$zbp）
│   │       ├── config.php          # Config 类（系统配置迭代器）
│   │       ├── post.php            # Post 类（文章/页面/自定义类型）
│   │       ├── category.php        # Category 类
│   │       ├── comment.php         # Comment 类
│   │       ├── member.php          # Member 类（用户）
│   │       ├── module.php          # Module 类（侧栏模块/组件）
│   │       ├── tag.php             # Tag 类
│   │       ├── upload.php          # Upload 类
│   │       ├── template.php        # Template 模板引擎类
│   │       ├── urlrule.php         # URL 规则类
│   │       ├── pagebar.php         # 分页条类
│   │       ├── thumb.php           # 缩略图类
│   │       ├── validatecode.php    # 验证码类
│   │       ├── rss2.php            # RSS 2.0 生成类
│   │       ├── xsshtml.php         # XSS 过滤类
│   │       ├── zbpdate.php         # 日期处理类
│   │       ├── zbpencrypt.php      # 加密类（密码哈希、Token）
│   │       ├── zbpenv.php          # 环境检测类
│   │       ├── zbpform.php         # 表单生成类
│   │       ├── zbplangs.php        # 语言包类
│   │       ├── metas.php           # 元数据类
│   │       ├── modulebuilder.php   # 模块构建器
│   │       ├── dbsql.php           # SQL 构建器
│   │       ├── punycode.php        # Punycode 编码
│   │       ├── base/               # 自动生成的 ORM 基类
│   │       │   ├── post.php
│   │       │   ├── category.php
│   │       │   ├── comment.php
│   │       │   ├── member.php
│   │       │   ├── module.php
│   │       │   ├── tag.php
│   │       │   └── upload.php
│   │       ├── database/           # 数据库驱动
│   │       │   ├── interface.php   # Database__Interface 接口
│   │       │   ├── mysql.php
│   │       │   ├── mysqli.php
│   │       │   ├── pdo_mysql.php
│   │       │   ├── sqlite.php
│   │       │   ├── sqlite3.php
│   │       │   ├── pdo_sqlite.php
│   │       │   ├── postgresql.php
│   │       │   └── pdo_postgresql.php
│   │       └── network/            # HTTP 请求驱动
│   │           ├── interface.php
│   │           ├── curl.php
│   │           ├── filegetcontents.php
│   │           └── fsockopen.php
│   ├── admin/              # 后台管理页面
│   │   ├── index.php       # 仪表盘
│   │   ├── edit.php        # 文章/页面编辑器
│   │   ├── category_edit.php
│   │   ├── member_edit.php
│   │   ├── module_edit.php
│   │   ├── tag_edit.php
│   │   ├── updatedb.php    # 数据库升级
│   │   ├── admin_header.php / admin_footer.php / admin_left.php / admin_top.php
│   ├── api/                # API 模块实现（对外 JSON 接口）
│   │   ├── app.php
│   │   ├── category.php
│   │   ├── comment.php
│   │   ├── member.php
│   │   ├── module.php
│   │   ├── post.php
│   │   ├── system.php
│   │   ├── tag.php
│   │   └── upload.php
│   ├── defend/             # 系统定义文件（静态配置/数据结构/路由规则）
│   │   ├── option.php      # 默认配置项
│   │   ├── datainfo.php    # 数据结构定义（字段名→类型→长度→默认值）
│   │   ├── table.php       # 数据表名映射
│   │   ├── actions.php     # 操作权限定义
│   │   ├── posttype.php    # 文章类型定义（0=article, 1=page, 2=tweet...）
│   │   ├── routes_post_article.php  # 文章路由
│   │   ├── routes_post_page.php     # 页面路由
│   │   ├── createtable/    # 建表 SQL
│   │   │   ├── mysql.sql
│   │   │   ├── pgsql.sql
│   │   │   └── sqlite.sql
│   │   ├── default/        # 默认模板文件
│   │   │   ├── index.php, single.php, header.php, footer.php
│   │   │   ├── post-single.php, post-multi.php, post-istop.php
│   │   │   ├── comments.php, comment.php, commentpost.php
│   │   │   ├── sidebar.php, module.php
│   │   │   ├── pagebar.php
│   │   │   └── module-*.php（各种侧栏模块）
│   │   └── error.php       # 错误码定义
│   ├── css/                # 后台 CSS
│   ├── script/             # 后台 JS
│   └── image/              # 系统图片
└── zb_users/               # 用户数据目录
    ├── cache/              # 编译模板缓存
    ├── data/               # 用户数据
    ├── language/           # 前台语言包
    ├── plugin/             # 插件目录
    ├── theme/              # 主题目录
    ├── upload/             # 上传文件
    ├── avatar/             # 用户头像
    ├── emotion/            # 表情包
    └── logs/               # 日志文件
```

## 三、启动流程

```
1. 入口文件 require 'zb_system/function/c_system_base.php'
2. c_system_base.php 定义 ZBP_PATH 常量
3. 顺序加载所有核心函数文件：
   version → common → compat → defined → plugin → debug → function → route → event → api
4. 注册 spl_autoload_register('AutoloadClass') 自动加载类
5. 加载 posttype / actions / table / datainfo 定义
6. 初始化全局 $zbp = ZBlogPHP::GetInstance()
7. $zbp->Load() 加载配置、数据库连接、当前用户会话、语言包
8. 通过 ViewAuto() 路由匹配当前 URL，分发到对应处理函数
9. 处理函数执行业务逻辑，最终渲染模板输出
10. 插件钩子在各个阶段插入，实现扩展
```

## 四、核心架构设计

### 4.1 全局单例 `$zbp`（ZBlogPHP 类）

整个系统的中枢对象，持有所有核心状态：
- `$zbp->db` — 数据库连接
- `$zbp->option` — 系统配置数组
- `$zbp->lang` — 语言包
- `$zbp->user` — 当前登录用户
- `$zbp->template` — 模板引擎实例
- `$zbp->routes` — 路由表
- `$zbp->cache` — 缓存对象
- `$zbp->path/host/cookiespath/currenturl` — 环境信息
- `$zbp->islogin/ismanage` — 状态标志位

### 4.2 数据库层

- **接口**: `Database__Interface`（Open/Close/Query/Insert/Update/Delete/EscapeString/CreateTable/ExistTable/Transaction）
- **8 种驱动**: mysql, mysqli, pdo_mysql, sqlite, sqlite3, pdo_sqlite, postgresql, pdo_postgresql
- **SQL 构建器**: `DbSql` 类提供统一 SQL 生成
- **表前缀**: `%pre%` 在运行时替换为实际前缀
- **ORM 模式**: Base 类自动映射数据库字段 → 对象属性（通过 `datainfo.php` 定义）

### 4.3 数据模型（8 张核心表）

| 表名 | 用途 | 关键字段 |
|------|------|----------|
| `post` | 文章/页面/自定义类型 | ID, CateID, AuthorID, Title, Content, Type, Status, IsTop, Alias, Meta |
| `category` | 分类目录 | ID, Name, ParentID, RootID, Order, Type, Alias, Meta |
| `comment` | 评论 | ID, LogID, RootID, ParentID, AuthorID, Content, IsChecking, IP |
| `member` | 用户/会员 | ID, Guid, Name, Password, Level, Status, Email, Alias, Meta |
| `module` | 侧栏模块/组件 | ID, Name, FileName, Content, Type, MaxLi, Source |
| `tag` | 标签 | ID, Name, Order, Type, Count, Alias, Meta |
| `upload` | 上传文件 | ID, AuthorID, Name, Size, Type, Ext, Path |
| `config` | 系统配置 | ID, Name, Key, Value（键值对存储） |

### 4.4 插件/钩子系统

**核心机制**：在系统关键节点插入钩子（Hook），插件注册回调函数到钩子上。

- `$GLOBALS['hooks']['Filter_Plugin_{Context}_{Action}']` — 钩子点
- `RegisterPlugin($id, $activeFunc)` — 注册插件
- `InstallPlugin($id)` / `UninstallPlugin($id)` — 插件生命周期

**钩子分类**（部分）：
- `Filter_Plugin_Zbp_Load` — 系统加载
- `Filter_Plugin_Zbp_ShowError` — 错误展示
- `Filter_Plugin_Index_Begin/End` — 首页渲染前后
- `Filter_Plugin_PostArticle_Core/Succeed` — 发布文章
- `Filter_Plugin_PostComment_Core/Succeed` — 发表评论
- `Filter_Plugin_VerifyLogin_Succeed/Failed` — 登录验证
- `Filter_Plugin_ViewAuto_Begin/End` — 路由分发
- `Filter_Plugin_Admin_*` — 后台各页面
- `Filter_Plugin_API_Extend_Mods` — API 扩展

**插件中断信号**：
- `PLUGIN_EXITSIGNAL_NONE` — 继续
- `PLUGIN_EXITSIGNAL_RETURN` — 返回插件结果
- `PLUGIN_EXITSIGNAL_BREAK` — 中断后续

### 4.5 路由系统

**三种路由模式**：
1. **Active（动态）**: `?id=1` 或 `?cate=2&page=1`
2. **Rewrite（伪静态）**: `/post/1.html`, `/category/tech/`
3. **Default（默认）**: 不匹配时走默认路由

**主要视图函数**：
- `ViewIndex()` — 首页/列表
- `ViewPost()` — 文章详情页
- `ViewList()` — 分类/作者/日期列表
- `ViewAuto()` — 自动路由分发
- `ViewSearch()` — 搜索页

### 4.6 模板引擎

- **编译型模板**：模板文件 → 编译为 PHP 文件 → 缓存在 `zb_users/cache/`
- **模板标签**：`{$title}`, `{$content}`, `{foreach}`, `{if}` 等
- **侧栏系统**：支持多达 9 个侧栏（sidebar ~ sidebar9）
- **主题目录**：`zb_users/theme/{主题名}/template/`
- **默认模板**：`zb_system/defend/default/`

### 4.7 API 系统

**入口**: `/zb_system/api.php?mod=post&act=get&id=1`

- Token 认证（Bearer Token 或请求参数 token）
- JSON 格式响应
- 9 个内置 API 模块：app, category, comment, member, module, post, system, tag, upload
- 可扩展：插件通过钩子注册自定义 Mod

### 4.8 后台管理

- **权限系统**：6 级权限（1=最高管理员, 6=访客），每个操作定义所需权限
- **仪表盘**：网站统计（文章数、评论数、数据库大小等）
- **内容管理**：文章/页面/分类/标签/评论/上传/用户 CRUD
- **设置管理**：网站基本设置、数据库配置、评论设置等
- **主题管理**：主题切换、侧栏配置
- **插件管理**：插件启用/禁用/安装/卸载
- **模块管理**：侧栏模块的增删改

### 4.9 关键特性清单

| 特性 | 说明 |
|------|------|
| 多数据库 | MySQL / MariaDB / SQLite / PostgreSQL |
| 多语言 | 前后台语言包分离，支持任意语言 |
| SEO URL | 伪静态 URL 重写，自定义 URL 规则 |
| RSS/Atom | 自动生成 Feed |
| 评论系统 | 嵌套回复、审核机制、验证码 |
| 用户系统 | 多级权限、Token 认证、Cookie 登录 |
| 验证码 | GD 库生成，自定义字符集/尺寸/角度 |
| CSRF 防护 | Token 验证 |
| XSS 过滤 | 内置 XSS 过滤器 |
| 缩略图 | 自动生成缩略图 |
| 缓存 | 模板编译缓存、统计数据缓存 |
| 插件扩展 | 全系统钩子覆盖 |
| 主题独立 | 主题与系统分离，可自定义模板 |
| 自定义文章类型 | 支持最多 256 种类型（系统预留 0-8） |
| 调试模式 | Debug 日志、严格模式 |
| 安装向导 | 图形化安装流程 |

## 五、主要函数 API

### 内容获取
- `GetPost($idorname, $option)` — 获取单篇文章
- `GetList($count, $cate, $auth, $date, $tags, $search, $option)` — 获取文章列表
- `GetCategory($id, ...)` — 获取分类
- `GetMember($id, ...)` — 获取用户
- `GetTag($id, ...)` — 获取标签

### 内容操作
- `PostArticle()` — 发布文章
- `DelArticle()` — 删除文章
- `PostPage()` — 发布页面
- `PostComment()` — 发表评论
- `CheckComment()` — 审核评论

### 用户操作
- `VerifyLogin()` — 验证登录
- `SetLoginCookie()` — 设置登录 Cookie
- `Logout()` — 注销登录
- `AddMember()` — 添加用户

### 系统工具
- `GetVars($name, $type)` — 获取请求参数
- `GetHttpContent($url)` — HTTP 请求
- `TransferHTML($s, $type)` — HTML 过滤
- `BuildSafeCmdURL($param)` — 构建安全 URL
- `CheckIsRefererValid()` — 验证 Referer
- `GetFileExt($filename)` — 获取文件扩展名

### 插件管理
- `RegisterPlugin($name, $activeFunc)` — 注册插件
- `InstallPlugin($name)` — 安装插件
- `UninstallPlugin($name)` — 卸载插件
- `HookFilterPlugin($hookName)` — 触发插件钩子

## 六、安装流程

1. 上传程序到网站目录（755 权限）
2. 访问网站首页，自动跳转安装界面
3. 选择数据库类型（MySQL / SQLite / PostgreSQL）
4. 填写数据库连接信息
5. 设置管理员账号密码
6. 自动创建数据表，写入初始配置
7. 安装完成，进入网站

## 七、版本发展

| 版本 | 代号 | 年份 |
|------|------|------|
| 1.0 Beta | - | 2013 |
| 1.1 | Taichi | 2013 |
| 1.2 | Hippo | 2014 |
| 1.3 | Wonce | 2014 |
| 1.4 | Deeplue | 2015 |
| 1.5 | Zero | 2016 |
| 1.6 | - | 2018 |
| 1.7.5 | Optimus | 2024 |

---
*本文档基于对 Z-BlogPHP 1.7.5.3540 (Optimus) 源码的分析整理，用于参考和规划自有 CMS 系统。*
