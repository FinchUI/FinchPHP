<?php

/**
 * Finch\Core\HtmlCleaner - HTML 清洗器
 *
 * 按 PROJECT_PLAN §4.8 定义的标签白名单过滤 Quill 输出，去除 XSS 风险。
 * 支持多 profile（post_content / comment_content / excerpt / user_bio）。
 * 基于 DOMDocument 递归遍历，标签不在白名单则剥离标签但保留文本。
 */

declare(strict_types=1);

namespace Finch\Core;

final class HtmlCleaner
{
    /**
     * 清洗 HTML 内容。
     *
     * @param string $html 待清洗的 HTML
     * @param string $profile 清洗规则集：post_content / comment_content / excerpt / user_bio
     */
    public static function clean(string $html, string $profile = 'post_content'): string
    {
        if (trim($html) === '') {
            return '';
        }

        $config = self::profileConfig($profile);

        // 使用 DOMDocument 解析
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div>' . $html . '</div>';
        $wrapped = mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8');

        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOXMLDECL);
        libxml_clear_errors();

        if (!$loaded) {
            return strip_tags($html, '<' . implode('><', array_keys($config['tags'])) . '>');
        }

        $root = $dom->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }

        self::cleanNode($root, $config);

        $result = $dom->saveHTML($root);
        if (!is_string($result)) {
            return '';
        }

        // 移除包裹的 <div> 标签
        $result = preg_replace('~^<div>~i', '', $result);
        $result = preg_replace('~</div>$~i', '', $result);

        return trim((string) $result);
    }

    /**
     * 从 HTML 提取纯文本（用于自动摘要）。
     */
    public static function plainText(string $html): string
    {
        $cleaned = self::clean($html, 'excerpt');

        return trim(strip_tags($cleaned));
    }

    /**
     * @return array{tags:array<string,list<string>>,iframe_domains:list<string>,css_properties:list<string>}
     */
    private static function profileConfig(string $profile): array
    {
        // 全量白名单标签和允许属性
        $fullTags = [
            'p' => ['class', 'style'],
            'br' => [],
            'strong' => [],
            'em' => [],
            'u' => [],
            's' => [],
            'sub' => [],
            'sup' => [],
            'blockquote' => ['class', 'style'],
            'pre' => ['class', 'style'],
            'code' => ['class'],
            'h1' => ['class', 'style'],
            'h2' => ['class', 'style'],
            'h3' => ['class', 'style'],
            'h4' => ['class', 'style'],
            'h5' => ['class', 'style'],
            'h6' => ['class', 'style'],
            'ul' => ['class'],
            'ol' => ['class'],
            'li' => ['class'],
            'a' => ['href', 'title', 'target', 'rel', 'class'],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'class', 'loading'],
            'figure' => ['class', 'style'],
            'figcaption' => ['class'],
            'table' => ['class', 'style'],
            'thead' => ['class'],
            'tbody' => ['class'],
            'tfoot' => ['class'],
            'tr' => ['class', 'style'],
            'th' => ['colspan', 'rowspan', 'scope', 'class', 'style'],
            'td' => ['colspan', 'rowspan', 'scope', 'class', 'style'],
            'caption' => ['class'],
            'colgroup' => ['class'],
            'col' => ['class', 'style'],
            'hr' => ['class', 'style'],
            'div' => ['class', 'style'],
            'span' => ['class', 'style'],
            'video' => ['src', 'controls', 'width', 'height', 'poster', 'preload', 'class'],
            'source' => ['src', 'type'],
            'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'class'],
        ];

        $iframeDomains = [
            'youtube.com', 'youtu.be', 'bilibili.com', 'player.bilibili.com',
            'v.qq.com', 'youku.com', 'vimeo.com',
        ];

        $cssProperties = [
            'text-align', 'font-weight', 'font-style', 'text-decoration',
            'color', 'background-color', 'margin', 'padding',
            'width', 'height', 'max-width', 'max-height',
            'border', 'border-radius', 'float',
        ];

        return match ($profile) {
            'post_content' => [
                'tags' => $fullTags,
                'iframe_domains' => $iframeDomains,
                'css_properties' => $cssProperties,
            ],
            'comment_content' => [
                'tags' => [
                    'p' => ['class'],
                    'br' => [],
                    'strong' => [],
                    'em' => [],
                    'code' => ['class'],
                    'a' => ['href', 'title', 'target', 'rel'],
                    'img' => ['src', 'alt', 'title', 'width', 'height', 'class', 'loading'],
                ],
                'iframe_domains' => [],
                'css_properties' => [],
            ],
            'excerpt' => [
                'tags' => [
                    'p' => [],
                    'br' => [],
                    'strong' => [],
                    'em' => [],
                    'code' => [],
                ],
                'iframe_domains' => [],
                'css_properties' => [],
            ],
            'user_bio' => [
                'tags' => [
                    'p' => [],
                    'br' => [],
                    'strong' => [],
                    'em' => [],
                    'a' => ['href', 'title', 'target', 'rel'],
                    'img' => ['src', 'alt', 'title', 'class', 'loading'],
                ],
                'iframe_domains' => [],
                'css_properties' => [],
            ],
            default => [
                'tags' => $fullTags,
                'iframe_domains' => $iframeDomains,
                'css_properties' => $cssProperties,
            ],
        };
    }

    /**
     * @param array{tags:array<string,list<string>>,iframe_domains:list<string>,css_properties:list<string>} $config
     */
    private static function cleanNode(\DOMNode $node, array $config): void
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            // 非元素节点（注释等）直接移除
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }

            return;
        }

        /** @var \DOMElement $node */
        $tagName = strtolower($node->tagName);

        // <script> / <style> 等危险标签整标签移除（含子节点）
        if (in_array($tagName, ['script', 'style', 'base', 'meta', 'link', 'object', 'embed', 'applet'], true)) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }

            return;
        }

        // 标签不在白名单：剥离标签但保留子节点
        if (!isset($config['tags'][$tagName])) {
            self::unwrapNode($node);

            return;
        }

        // 清理属性
        $allowedAttrs = $config['tags'][$tagName];
        self::cleanAttributes($node, $tagName, $allowedAttrs, $config);

        // 递归处理子节点（需要复制集合，因为迭代中会修改）
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::cleanNode($child, $config);
        }
    }

    /** 剥离标签但保留子节点文本 */
    private static function unwrapNode(\DOMElement $node): void
    {
        if ($node->parentNode === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $child = $node->firstChild;
            $node->removeChild($child);
            $node->parentNode->insertBefore($child, $node);
        }

        $node->parentNode->removeChild($node);
    }

    /**
     * @param list<string> $allowedAttrs
     * @param array{tags:array<string,list<string>>,iframe_domains:list<string>,css_properties:list<string>} $config
     */
    private static function cleanAttributes(\DOMElement $node, string $tagName, array $allowedAttrs, array $config): void
    {
        $attrsToRemove = [];

        foreach ($node->attributes as $attr) {
            $attrName = strtolower($attr->name);
            $attrValue = $attr->value;

            // 移除所有 on* 事件属性
            if (str_starts_with($attrName, 'on')) {
                $attrsToRemove[] = $attr;
                continue;
            }

            // 不在白名单中的属性
            if (!in_array($attrName, $allowedAttrs, true)) {
                $attrsToRemove[] = $attr;
                continue;
            }

            // href 属性：仅允许 http/https/mailto/锚点
            if ($attrName === 'href') {
                $lower = strtolower(trim($attrValue));
                if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
                    $attrsToRemove[] = $attr;
                    continue;
                }
            }

            // src 属性：仅允许 http/https/本站相对路径
            if ($attrName === 'src') {
                $lower = strtolower(trim($attrValue));
                if (str_starts_with($lower, 'data:') && !self::isAllowedDataImage($lower)) {
                    $attrsToRemove[] = $attr;
                    continue;
                }
                if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:')) {
                    $attrsToRemove[] = $attr;
                    continue;
                }
            }

            // iframe src：仅保留白名单域名
            if ($tagName === 'iframe' && $attrName === 'src') {
                if (!self::isAllowedIframeSrc($attrValue, $config['iframe_domains'])) {
                    // 非白名单域名的 iframe 整标签移除
                    if ($node->parentNode !== null) {
                        $node->parentNode->removeChild($node);
                    }

                    return;
                }
            }

            // style 属性：过滤 CSS 属性白名单
            if ($attrName === 'style' && $config['css_properties'] !== []) {
                $filtered = self::filterStyle($attrValue, $config['css_properties']);
                $attr->value = $filtered;
            }

            // target="_blank" 强制补全 rel="noopener noreferrer"
            if ($attrName === 'target' && $attrValue === '_blank') {
                $existingRel = $node->getAttribute('rel');
                if ($existingRel === '') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                } elseif (!str_contains($existingRel, 'noopener')) {
                    $node->setAttribute('rel', $existingRel . ' noopener noreferrer');
                }
            }
        }

        foreach ($attrsToRemove as $attr) {
            $node->removeAttributeNode($attr);
        }
    }

    /** 判断 data: URL 是否为允许的图片类型 */
    private static function isAllowedDataImage(string $value): bool
    {
        return (bool) preg_match('~^data:image/(png|jpeg|jpg|gif|webp);base64,~i', $value);
    }

    /**
     * @param list<string> $allowedDomains
     */
    private static function isAllowedIframeSrc(string $src, array $allowedDomains): bool
    {
        $lower = strtolower(trim($src));
        if (!str_starts_with($lower, 'http://') && !str_starts_with($lower, 'https://')) {
            return false;
        }

        $parsed = parse_url($lower);
        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return false;
        }

        foreach ($allowedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $allowedProps
     */
    private static function filterStyle(string $style, array $allowedProps): string
    {
        $declarations = explode(';', $style);
        $filtered = [];

        foreach ($declarations as $decl) {
            $decl = trim($decl);
            if ($decl === '') {
                continue;
            }

            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $prop = strtolower(trim($parts[0]));
            if (in_array($prop, $allowedProps, true)) {
                $filtered[] = $decl;
            }
        }

        return implode('; ', $filtered);
    }
}
