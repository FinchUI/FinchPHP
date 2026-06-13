# **1. 实现模型**

## **1.1 上下文视图**

本设计在现有 Finch PHP 架构中新增一个 `Lang` 核心服务类，作为 i18n 的统一入口。该服务注册到 `App` 单例中，供所有 Admin 控制器通过 `$this->app->lang` 访问。

```
┌─────────────────────────────────────────────────────┐
│                    App 单例                          │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐      │
│  │ db   │ │settings│ │session│ │ lang │ │hooks │      │
│  └──────┘ └──────┘ └──────┘ └──┬───┘ └──────┘      │
└────────────────────────────────┬────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │      Lang 服务           │
                    │  - locale (当前语言)      │
                    │  - items (翻译缓存)       │
                    │  + get(key) 翻译方法      │
                    └────────────┬────────────┘
                                 │
                 ┌───────────────┼───────────────┐
                 │               │               │
          ┌──────┴──────┐ ┌─────┴─────┐ ┌──────┴──────┐
          │ zh-cn.php   │ │ zh-tw.php │ │  en.php     │
          │ 简体中文     │ │ 繁体中文   │ │  英文       │
          └─────────────┘ └───────────┘ └─────────────┘
```

## **1.2 服务/组件总体架构**

### 核心组件

1. **`Finch\Core\Lang`** - 语言服务类（新增）
   - 职责：加载语言包、提供翻译查询、处理回退逻辑
   - 生命周期：随 App 引导创建，单次请求内复用
   - 依赖：`Settings`（读取 `admin_language`）

2. **语言包文件** - `system/Languages/{locale}.php`（扩充）
   - 格式：PHP 原生 return 关联数组
   - 命名规范：`模块.子模块.条目`（如 `admin.menu.dashboard`）
   - 三个文件：`zh-cn.php`、`zh-tw.php`、`en.php`

3. **Admin 控制器改造** - 所有 Admin 控制器中的硬编码中文替换为 `$this->app->lang->get('key')` 调用

4. **SettingAdmin 改造** - `admin_language` 字段从 text 改为 select 控件

### 调用链路

```
Admin 控制器 → $this->app->lang->get('admin.menu.dashboard')
    → Lang::get() 查找当前语言包
    → 命中：返回翻译文本
    → 未命中：回退 zh-cn 语言包
    → 仍未命中：返回语言键本身
```

## **1.3 实现设计文档**

### 1.3.1 Lang 类设计

```php
// system/Core/Lang.php
namespace Finch\Core;

final class Lang
{
    private string $locale;
    private array $items = [];      // 当前语言包
    private array $fallback = [];   // zh-cn 回退语言包

    public function __construct(Settings $settings)
    {
        $this->locale = $this->resolveLocale($settings);
        $this->items = $this->loadFile($this->locale);
        if ($this->locale !== 'zh-cn') {
            $this->fallback = $this->loadFile('zh-cn');
        }
    }

    public function get(string $key, string $default = ''): string
    {
        // 1. 当前语言包查找
        // 2. 回退 zh-cn
        // 3. 返回 $key 本身
    }

    public function locale(): string
    {
        return $this->locale;
    }

    private function resolveLocale(Settings $settings): string
    {
        $locale = (string) $settings->get('admin_language', 'zh-cn');
        return in_array($locale, ['zh-cn', 'zh-tw', 'en'], true) ? $locale : 'zh-cn';
    }

    private function loadFile(string $locale): array
    {
        $file = FP_SYSTEM_DIR . '/Languages/' . $locale . '.php';
        return is_file($file) ? (array) require $file : [];
    }
}
```

### 1.3.2 App 引导注册

在 `App::bootInstalled()` 中，Settings 加载之后注册 Lang 服务：

```php
// App::bootInstalled() 中新增
$this->services['lang'] = new Lang($settings);
```

### 1.3.3 AdminLayout 改造

`AdminLayout` trait 中所有硬编码中文替换为 `$this->app->lang->get()` 调用：

- `adminShell()` 中 `lang="zh-cn"` → `lang="<?php echo $this->app->lang->locale() ?>"`
- `adminMenuItems()` 中 `label` 值改为语言键引用
- `adminTopbar()` 中所有中文文本改为语言包调用

菜单项改造方式：将 `adminMenuItems()` 的 `label` 改为语言键，在渲染时通过 `lang->get()` 翻译：

```php
private function adminMenuItems(): array
{
    return [
        ['path' => '/admin', 'label' => 'admin.menu.dashboard', 'icon' => 'ti ti-layout-dashboard'],
        ['path' => '/admin/posts', 'label' => 'admin.menu.posts', 'icon' => 'ti ti-article'],
        // ...
    ];
}

// 渲染时
$this->app->lang->get($item['label'])
```

### 1.3.4 SettingAdmin 改造

`admin_language` 字段从 `text` 改为 `select` 类型：

```php
// FIELDS 常量中
'admin_language' => [
    'label' => 'admin.settings.admin_language',
    'type' => 'select',
    'options' => [
        'zh-cn' => '简体中文',
        'zh-tw' => '繁體中文',
        'en' => 'English',
    ],
],
```

在 `field()` 方法中新增 `select` 类型的渲染逻辑，options 的标签保持原语言显示（不翻译），确保用户始终能识别语言选项。

### 1.3.5 语言键命名规范

| 模块 | 前缀 | 示例 |
|------|------|------|
| 后台菜单 | `admin.menu.` | `admin.menu.dashboard` |
| 后台顶栏 | `admin.topbar.` | `admin.topbar.welcome` |
| 后台设置 | `admin.settings.` | `admin.settings.site_name` |
| 后台文章 | `admin.post.` | `admin.post.title` |
| 后台页面 | `admin.page.` | `admin.page.title` |
| 后台分类 | `admin.category.` | `admin.category.title` |
| 后台标签 | `admin.tag.` | `admin.tag.title` |
| 后台评论 | `admin.comment.` | `admin.comment.title` |
| 后台用户 | `admin.user.` | `admin.user.title` |
| 后台Token | `admin.token.` | `admin.token.title` |
| 后台扩展 | `admin.extension.` | `admin.extension.title` |
| 后台媒体库 | `admin.upload.` | `admin.upload.title` |
| 后台登录 | `admin.auth.` | `admin.auth.login` |
| 后台仪表盘 | `admin.dashboard.` | `admin.dashboard.title` |
| 通用操作 | `admin.common.` | `admin.common.save` |
| 通用消息 | `admin.message.` | `admin.message.saved` |

# **2. 接口设计**

## **2.1 总体设计**

Lang 类对外暴露两个公共方法：

1. `get(string $key, string $default = ''): string` - 翻译查询
2. `locale(): string` - 获取当前语言标识

## **2.2 接口清单**

### Lang::get()

| 项目 | 说明 |
|------|------|
| 方法签名 | `public function get(string $key, string $default = ''): string` |
| 输入 | `$key` - 语言键（如 `admin.menu.dashboard`）；`$default` - 可选默认值 |
| 输出 | 翻译后的文本字符串 |
| 回退策略 | 当前语言包 → zh-cn 回退 → `$default`（若非空）→ `$key` 本身 |
| 调用方 | 所有 Admin 控制器、AdminLayout trait |

### Lang::locale()

| 项目 | 说明 |
|------|------|
| 方法签名 | `public function locale(): string` |
| 输入 | 无 |
| 输出 | 当前语言标识符（`zh-cn`/`zh-tw`/`en`） |
| 调用方 | AdminLayout（HTML lang 属性） |

# **4. 数据模型**

## **4.1 设计目标**

语言包数据以 PHP 文件形式存储，不引入数据库表。利用 PHP 原生 `return` 数组 + OPcache 实现高性能读取。

## **4.2 模型实现**

### 语言包文件结构

每个语言包文件（如 `system/Languages/zh-cn.php`）返回一个扁平的关联数组：

```php
<?php
declare(strict_types=1);

return [
    // 菜单
    'admin.menu.dashboard' => '仪表盘',
    'admin.menu.posts' => '文章',
    'admin.menu.pages' => '页面',
    // ...

    // 顶栏
    'admin.topbar.welcome' => '欢迎，',
    'admin.topbar.settings' => '设置',
    // ...

    // 设置
    'admin.settings.title' => '系统设置',
    'admin.settings.save' => '保存设置',
    // ...
];
```

### admin_language 设置项

- 存储位置：`system_setting` 表，`name = 'admin_language'`
- 值域：`zh-cn` | `zh-tw` | `en`
- 默认值：`zh-cn`
- 类型：`string`
- autoload：`1`（随 Settings 一次性加载，避免额外查询）
