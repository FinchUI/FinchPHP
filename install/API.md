# FinchPHP API 文档（v1）

本文档描述当前 Stage20 可用 API 的认证方式、统一返回结构与主要端点。

## 1. 认证

所有 v1 API 走 Token 认证。

请求头：

```http
Authorization: Bearer <plain_token>
```

Token 签发方式：后台管理员在 /admin/tokens 页面创建。

## 2. 通用返回结构

成功：

```json
{
  "code": 0,
  "message": "success",
  "data": {}
}
```

失败：

```json
{
  "code": 3000,
  "message": "未认证或 token 已失效"
}
```

分页接口返回 meta 字段：

```json
{
  "code": 0,
  "message": "success",
  "data": [],
  "meta": {
    "total": 100,
    "per_page": 20,
    "current_page": 1,
    "last_page": 5
  }
}
```

## 3. 公共响应头（Stage20）

- X-API-Version: v1
- Cache-Control: no-store, no-cache, must-revalidate
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- Referrer-Policy: strict-origin-when-cross-origin

## 4. 端点总览

### 4.1 系统

- GET /api/v1/system
  - ability: system:read

### 4.2 文章

- GET /api/v1/posts
  - ability: posts:read
  - query: page, per_page, type, sort, order

- GET /api/v1/posts/{id}
  - ability: posts:read

### 4.3 评论

- GET /api/v1/posts/{postId}/comments
  - ability: comments:read

- POST /api/v1/posts/{postId}/comments
  - ability: comments:create
  - body: content, parent_id(optional), author_name/author_email(游客场景)

### 4.4 分类/标签

- GET /api/v1/categories
  - ability: categories:read

- GET /api/v1/categories/{id}
  - ability: categories:read

- GET /api/v1/tags
  - ability: tags:read

- GET /api/v1/tags/{id}
  - ability: tags:read

### 4.5 上传

- GET /api/v1/uploads
  - ability: uploads:read

- POST /api/v1/uploads
  - ability: uploads:create
  - content-type: multipart/form-data
  - field: file

### 4.6 用户

- GET /api/v1/users
  - ability: users:read

- GET /api/v1/users/{id}
  - ability: users:read

- GET /api/v1/me
  - ability: users:read

## 5. 错误码示例

- 2001: API 已禁用
- 3000: 未认证或 token 已失效
- 4001: 资源不存在（如文章不存在）
- 600x: 评论相关业务错误
- 7001: 上传业务错误

建议客户端优先根据 HTTP 状态码判断，再读取 code/message 处理业务分支。
