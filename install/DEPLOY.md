# FinchPHP 安装与部署说明（Stage20）

本文档给出 FinchPHP 在开发与生产环境的最小部署流程，并说明 Stage20 新增的安全头与缓存策略。

## 1. 运行环境

- PHP 8.0+
- 必需扩展：pdo、json、mbstring、session、openssl、fileinfo、gd、curl
- 数据库：SQLite（pdo_sqlite）或 MySQL/MariaDB（pdo_mysql）
- Web 目录可写：
  - storage/data
  - storage/logs
  - content/uploads

## 2. 首次安装

1. 打开安装向导：/install/
2. 选择数据库驱动（推荐先用 SQLite 快速启动）
3. 完成管理员账号创建
4. 安装成功后会写入：
   - config.php
   - storage/data/install.lock

说明：若需要重装，请先备份数据，再移除 install.lock 与旧配置。

## 3. 生产部署建议

- 启用 HTTPS，并在反向代理层强制 80 -> 443。
- 保持 config.php 不可被 Web 直接下载。
- 为 PHP-FPM/HTTP 用户授予最小可写目录权限，仅限上传与运行时目录。
- 建议定时执行缓存过期清理（可在业务代码中调用 Cache::pruneExpired()，或加入运维脚本）。

## 4. Stage20 默认安全与缓存头

默认由响应层统一注入（可被业务显式覆盖）：

- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera=(), microphone=(), geolocation=()
- Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'
- Strict-Transport-Security: 仅在 HTTPS 请求下发送

缓存控制策略：

- API / Admin / Auth / 写请求：Cache-Control: no-store, no-cache, must-revalidate
- HTML 读请求：Cache-Control: no-cache, private
- API 统一附加：X-API-Version: v1

## 5. Stage20 缓存分组

核心缓存管理器：system/Core/Cache.php

推荐分组：

- home：首页与列表
- post：文章详情/列表
- taxonomy：分类标签相关
- search：搜索结果
- comment：评论相关
- settings：系统设置
- theme：主题相关
- module：模块开关相关

已接入失效的写操作包括：文章写入、分类标签管理、评论提交与审核、系统设置更新、主题切换、模块启停。

## 6. 一键回归验证

执行：

```bash
bash install/stage20_verify.sh
```

可选环境变量：

- PHP_BIN=/path/to/php
- PORT=19880

脚本会在临时目录中执行隔离验证，并在通过时输出：

- STAGE20_HTTP_VERIFY_PASS ...

覆盖项：安装、登录、发布、上传、API 读、评论、响应头校验、搜索缓存失效、主题回退。
