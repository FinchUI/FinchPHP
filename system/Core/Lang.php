<?php

/**
 * Finch\Core\Lang - 国际化语言服务
 *
 * 加载语言包、提供翻译查询、处理回退逻辑。
 * 随 App 引导创建，供所有 Admin 控制器通过 $this->app->lang 访问。
 */

declare(strict_types=1);

namespace Finch\Core;

final class Lang
{
    private string $locale;

    /** @var array<string, string> 当前语言包 */
    private array $items = [];

    /** @var array<string, string> zh-cn 回退语言包 */
    private array $fallback = [];

    public function __construct(Settings $settings)
    {
        $this->locale = $this->resolveLocale($settings);
        $this->items = $this->loadFile($this->locale);

        if ($this->locale !== 'zh-cn') {
            $this->fallback = $this->loadFile('zh-cn');
        }
    }

    /**
     * 翻译查询：当前语言包 → zh-cn 回退 → default → key 本身
     */
    public function get(string $key, string $default = ''): string
    {
        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        if (isset($this->fallback[$key])) {
            return $this->fallback[$key];
        }

        return $default !== '' ? $default : $key;
    }

    /** 获取当前语言标识符 */
    public function locale(): string
    {
        return $this->locale;
    }

    /** 校验语言值合法性，非法值回退 zh-cn */
    private function resolveLocale(Settings $settings): string
    {
        $locale = (string) $settings->get('admin_language', 'zh-cn');

        return in_array($locale, ['zh-cn', 'zh-tw', 'en'], true) ? $locale : 'zh-cn';
    }

    /** 加载语言包文件 */
    private function loadFile(string $locale): array
    {
        $file = FP_SYSTEM_DIR . '/Languages/' . $locale . '.php';

        return is_file($file) ? (array) require $file : [];
    }
}
