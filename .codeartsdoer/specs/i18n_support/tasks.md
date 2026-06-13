# i18n_support 实现任务清单

## 任务 1：创建 Lang 核心服务类

**文件**：`system/Core/Lang.php`（新建）

**描述**：创建 `Finch\Core\Lang` 类，实现语言包加载、翻译查询和回退逻辑。

**具体步骤**：
1. 创建 `system/Core/Lang.php` 文件
2. 实现 `__construct(Settings $settings)`：从 Settings 读取 `admin_language`，加载当前语言包和 zh-cn 回退语言包
3. 实现 `get(string $key, string $default = ''): string`：按 当前语言包 → zh-cn 回退 → default → key 本身 的顺序查找
4. 实现 `locale(): string`：返回当前语言标识符
5. 实现 `resolveLocale(Settings $settings): string`：校验语言值合法性，非法值回退 zh-cn
6. 实现 `loadFile(string $locale): array`：加载 `system/Languages/{locale}.php` 文件

**验收标准**：
- `Lang::get('admin.menu.dashboard')` 在 zh-cn 下返回"仪表盘"
- `Lang::get('nonexistent.key')` 返回 "nonexistent.key"
- `Lang::locale()` 返回当前语言标识符

---

## 任务 2：在 App 引导中注册 Lang 服务

**文件**：`system/App.php`（修改）

**描述**：在 `App::bootInstalled()` 方法中，Settings 加载之后注册 Lang 服务到服务容器。

**具体步骤**：
1. 在文件顶部 `use` 区域添加 `use Finch\Core\Lang;`
2. 在 `bootInstalled()` 方法中，`$this->services['asset']` 之前，添加 `$this->services['lang'] = new Lang($settings);`

**验收标准**：
- `$app->lang` 可正常访问 `Lang` 实例
- Lang 服务在 Settings 之后初始化，确保 `admin_language` 可读取

---

## 任务 3：编写简体中文语言包（zh-cn.php）

**文件**：`system/Languages/zh-cn.php`（重写）

**描述**：将现有的 2 条语言包扩充为覆盖所有后台硬编码中文的完整语言包。

**具体步骤**：
1. 梳理所有 Admin 控制器中的硬编码中文文本
2. 按 `admin.menu.`、`admin.topbar.`、`admin.settings.`、`admin.post.`、`admin.page.`、`admin.category.`、`admin.tag.`、`admin.comment.`、`admin.user.`、`admin.token.`、`admin.extension.`、`admin.upload.`、`admin.auth.`、`admin.dashboard.`、`admin.common.`、`admin.message.` 等前缀组织语言键
3. 编写完整的 zh-cn.php 语言包文件

**验收标准**：
- 语言包覆盖所有 Admin 控制器中出现的硬编码中文
- 语言键命名遵循 `模块.子模块.条目` 规范
- 文件格式为 PHP 原生 return 关联数组

---

## 任务 4：编写繁体中文语言包（zh-tw.php）

**文件**：`system/Languages/zh-tw.php`（重写）

**描述**：基于 zh-cn.php 的语言键结构，编写繁体中文翻译。

**具体步骤**：
1. 复制 zh-cn.php 的键结构
2. 将所有简体中文值翻译为繁体中文

**验收标准**：
- 语言键与 zh-cn.php 完全一致
- 翻译文本为规范的繁体中文

---

## 任务 5：编写英文语言包（en.php）

**文件**：`system/Languages/en.php`（重写）

**描述**：基于 zh-cn.php 的语言键结构，编写英文翻译。

**具体步骤**：
1. 复制 zh-cn.php 的键结构
2. 将所有中文值翻译为英文

**验收标准**：
- 语言键与 zh-cn.php 完全一致
- 翻译文本为规范的英文

---

## 任务 6：改造 AdminLayout trait

**文件**：`system/Admin/AdminLayout.php`（修改）

**描述**：将 AdminLayout 中所有硬编码中文替换为 `$this->app->lang->get()` 调用。

**具体步骤**：
1. `adminShell()` 方法：将 `lang="zh-cn"` 改为 `lang="' . $this->app->lang->locale() . '"`
2. `adminSidebar()` 方法：将品牌文字 "Finch Admin" 改为语言包调用；将 `aria-label="后台主菜单"` 改为语言包调用
3. `adminMenuItems()` 方法：将所有 `label` 值从中文改为语言键（如 `'仪表盘'` → `'admin.menu.dashboard'`）
4. `adminSidebar()` 渲染循环中：将 `$this->escape($item['label'])` 改为 `$this->escape($this->app->lang->get($item['label']))`
5. `adminTopbar()` 方法：将"欢迎，"、"设置"、"前台"、"退出登录"改为语言包调用
6. `currentAdminName()` 方法：将默认名"管理员"改为语言包调用

**验收标准**：
- AdminLayout 中不再有任何硬编码中文
- 切换语言后，菜单和顶栏文本正确显示对应语言
- HTML lang 属性随语言设置动态变化

---

## 任务 7：改造 AuthAdmin（登录页面）

**文件**：`system/Admin/AuthAdmin.php`（修改）

**描述**：将登录页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有中文文本（"后台登录"、"欢迎回来，请登录后继续管理站点内容"、"用户名"、"密码"、"记住我"、"登录"、"返回前台"、"请输入用户名和密码"、"用户名或密码错误"等）替换为 `$this->app->lang->get()` 调用
2. 将 `lang="zh-cn"` 改为动态输出

**验收标准**：
- 登录页面无硬编码中文
- 切换语言后登录页面正确显示对应语言

---

## 任务 8：改造 DashboardAdmin（仪表盘）

**文件**：`system/Admin/DashboardAdmin.php`（修改）

**描述**：将仪表盘页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将快捷入口标签改为语言包调用
2. 将"站点概览"、"站点名称"、"当前登录"、"时区"、"常用入口"、"仪表盘"等文本改为语言包调用

**验收标准**：
- 仪表盘页面无硬编码中文

---

## 任务 9：改造 SettingAdmin（系统设置）

**文件**：`system/Admin/SettingAdmin.php`（修改）

**描述**：将设置页面所有硬编码中文替换为语言包调用，并将 `admin_language` 字段改为 select 下拉选择框。

**具体步骤**：
1. GROUPS 常量中的 `label` 值从中文改为语言键
2. FIELDS 常量中的 `label`、`placeholder`、`help` 值从中文改为语言键
3. `admin_language` 字段定义：`type` 从 `text` 改为 `select`，新增 `options` 数组（zh-cn → 简体中文，zh-tw → 繁體中文，en → English）
4. `content()` 方法中的"系统设置"、"设置已保存"、"保存设置"改为语言包调用
5. `tabs()` 方法中的分组标签改为语言包调用
6. `field()` 方法中新增 `select` 类型渲染逻辑
7. `save()` 方法中的错误消息"时区不存在"改为语言包调用

**验收标准**：
- 设置页面无硬编码中文
- `admin_language` 字段显示为下拉选择框，包含三个语言选项
- 选项标签保持原语言显示（简体中文/繁體中文/English）
- 保存语言设置后页面刷新即以新语言显示

---

## 任务 10：改造 PostAdmin（文章管理）

**文件**：`system/Admin/PostAdmin.php`（修改）

**描述**：将文章管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将页面标题"文章管理"、"新建文章"、"编辑文章"改为语言包调用
2. 将列表表头"标题"、"状态"、"更新时间"、"操作"改为语言包调用
3. 将状态选项"草稿"、"发布"、"待审核"、"私密"改为语言包调用
4. 将表单标签"评论"、"开启"、"关闭"、"是否置顶"、"发布时间"、"摘要"、"内容"、"分类"、"标签"等改为语言包调用
5. 将按钮"保存"、"返回列表"、"新建文章"、"查看回收站"改为语言包调用
6. 将消息"文章不存在"、"保存成功"改为语言包调用
7. 将"暂无文章"改为语言包调用

**验收标准**：
- 文章管理页面无硬编码中文

---

## 任务 11：改造 PageAdmin（页面管理）

**文件**：`system/Admin/PageAdmin.php`（修改）

**描述**：将页面管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有硬编码中文（"页面管理"、"新建页面"、"编辑页面"、"页面不存在"、"暂无页面"等）替换为语言包调用

**验收标准**：
- 页面管理页面无硬编码中文

---

## 任务 12：改造 CategoryAdmin（分类管理）

**文件**：`system/Admin/CategoryAdmin.php`（修改）

**描述**：将分类管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有硬编码中文（"分类管理"、"保存成功"、"删除"、"暂无分类"、"名称"、"描述"、"排序"、"状态"、"启用"、"停用"等）替换为语言包调用

**验收标准**：
- 分类管理页面无硬编码中文

---

## 任务 13：改造 TagAdmin（标签管理）

**文件**：`system/Admin/TagAdmin.php`（修改）

**描述**：将标签管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有硬编码中文（"标签管理"、"保存成功"、"删除"、"暂无标签"、"名称"、"保存标签"等）替换为语言包调用

**验收标准**：
- 标签管理页面无硬编码中文

---

## 任务 14：改造 CommentAdmin（评论管理）

**文件**：`system/Admin/CommentAdmin.php`（修改）

**描述**：将评论管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有硬编码中文（"评论管理"、"通过"、"驳回"、"删除"、"暂无评论"、"全部"、"待审核"、"已通过"、"已驳回"等）替换为语言包调用

**验收标准**：
- 评论管理页面无硬编码中文

---

## 任务 15：改造 UserAdmin（用户管理）

**文件**：`system/Admin/UserAdmin.php`（修改）

**描述**：将用户管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有硬编码中文（"用户管理"、"显示名称"、"邮箱"、"保存"、"未知角色"、"暂无用户"、"搜索"、"用户名"、"编辑"、"角色"、"状态"、"上次登录"等）替换为语言包调用
2. 将分页文本（"上一页"、"下一页"、"第 X / Y 页，共 Z 条"）改为语言包调用

**验收标准**：
- 用户管理页面无硬编码中文

---

## 任务 16：改造 TokenAdmin（API Token 管理）

**文件**：`system/Admin/TokenAdmin.php`（修改）

**描述**：将 Token 管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有硬编码中文（"API Token 管理"、"Token 已签发"、"明文仅显示一次"、"Token 已撤销"、"暂无 Token"、"撤销"、"签发 Token"等）替换为语言包调用
2. 将分页文本改为语言包调用

**验收标准**：
- Token 管理页面无硬编码中文

---

## 任务 17：改造 ExtensionAdmin（扩展管理）

**文件**：`system/Admin/ExtensionAdmin.php`（修改）

**描述**：将扩展管理页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将 BUILTIN_PLUGIN_CONFIGS 常量中的 title、description、binding label/help、fields label 等中文改为语言键
2. 将页面文本（"扩展管理"、"主题已切换"、"启用"、"停用"、"配置"、"保存配置"等）改为语言包调用
3. 将通用文本（"是"、"否"、"有"、"无"、"(空)"等）改为语言包调用

**验收标准**：
- 扩展管理页面无硬编码中文

---

## 任务 18：改造 UploadAdmin（媒体库）

**文件**：`system/Admin/UploadAdmin.php`（修改）

**描述**：将媒体库页面所有硬编码中文替换为语言包调用。

**具体步骤**：
1. 将所有硬编码中文（"媒体库"、"上传成功"、"暂无上传文件"、"上传文件"、"预览"、"尺寸"、"上传时间"等）替换为语言包调用
2. 将分页文本改为语言包调用

**验收标准**：
- 媒体库页面无硬编码中文
