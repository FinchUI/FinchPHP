<?php

/**
 * Finch\Admin\DevModeAdmin - 开发模式后台控制器
 *
 * 仅供开发阶段使用：
 *   - 一键重装网站（DROP 所有表 + 重新迁移 + 种子数据）
 *   - 一键导入演示数据
 *   - 一键清空演示数据
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Database;
use Finch\Core\Response;
use Finch\Database\Migrator;
use Finch\Database\Schema\Builder;
use Finch\Model\Role;
use Finch\Model\SystemSetting;
use Finch\Model\User;

final class DevModeAdmin extends BaseController
{
    use AdminLayout;

    /** 一键重装网站 */
    public function reinstall(): Response
    {
        $db = $this->app->db;
        $prefix = $db->prefix();

        // 1. 获取所有表名
        $tables = $this->getAllTables($db);

        // 2. 禁用外键检查，逐表 DROP
        if ($db->driver()->getName() !== 'sqlite') {
            $db->execute('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach ($tables as $table) {
            $db->execute('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
        }

        if ($db->driver()->getName() !== 'sqlite') {
            $db->execute('SET FOREIGN_KEY_CHECKS = 1');
        }

        // 3. 重新运行迁移
        $migrationsPath = FP_SYSTEM_DIR . '/Database/Migrations';
        $migrator = new Migrator($db, $migrationsPath);
        $migrator->run();

        // 4. 重新 seed 系统设置、角色、管理员
        $this->seedCoreData($db);

        // 5. 重新加载当前管理员会话信息
        $admin = User::query()->where('is_admin', 1)->first();
        if ($admin !== null) {
            $this->app->session->put('user_id', (int) $admin['id']);
        }

        return $this->redirect('/admin?dev=reinstall');
    }

    /** 一键导入演示数据 */
    public function seed(): Response
    {
        $db = $this->app->db;

        // 获取管理员 ID
        $admin = User::query()->where('is_admin', 1)->first();
        $authorId = $admin !== null ? (int) $admin['id'] : 1;

        $this->seedDemoData($db, $authorId);

        return $this->redirect('/admin?dev=seed');
    }

    /** 一键清空演示数据 */
    public function clean(): Response
    {
        $db = $this->app->db;

        // 清空内容相关表（保留系统表和用户表）
        $contentTables = [
            'comment', 'post_tag', 'post_category', 'tag',
            'post_meta', 'post', 'category', 'upload', 'link',
        ];

        $prefix = $db->prefix();
        if ($db->driver()->getName() !== 'sqlite') {
            $db->execute('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach ($contentTables as $table) {
            $db->execute('DELETE FROM ' . $this->quoteIdentifier($prefix . $table));
        }

        if ($db->driver()->getName() !== 'sqlite') {
            $db->execute('SET FOREIGN_KEY_CHECKS = 1');
        }

        // 清空缓存
        $this->app->cache?->flushGroups(['plugin', 'settings', 'search', 'home', 'post', 'comment']);

        return $this->redirect('/admin?dev=clean');
    }

    /** @return list<string> */
    private function getAllTables(Database $db): array
    {
        $prefix = $db->prefix();
        $driver = $db->driver();

        if ($driver->getName() === 'sqlite') {
            $rows = $driver->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        } else {
            $rows = $driver->select(
                'SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ?',
                [$prefix . '%'],
            );
        }

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    private function seedCoreData(Database $db): void
    {
        // Seed system settings
        $installer = new \Finch\Core\Installer($db, FP_SYSTEM_DIR . '/Database/Migrations');
        $siteUrl = (string) $this->app->request->getSchemeAndHttpHost();
        $installer->seedSettings(['site_url' => $siteUrl]);
        $installer->seedRoles();
        $installer->createAdmin('admin', 'admin@finch.local', 'admin123');
    }

    private function seedDemoData(Database $db, int $authorId): void
    {
        // ── 标签 ──
        $tagData = [
            ['name' => 'PHP', 'slug' => 'php'],
            ['name' => 'JavaScript', 'slug' => 'javascript'],
            ['name' => 'Python', 'slug' => 'python'],
            ['name' => 'MySQL', 'slug' => 'mysql'],
            ['name' => 'Redis', 'slug' => 'redis'],
            ['name' => 'Docker', 'slug' => 'docker'],
            ['name' => 'Linux', 'slug' => 'linux'],
            ['name' => 'Vue', 'slug' => 'vue'],
            ['name' => 'React', 'slug' => 'react'],
            ['name' => 'Go', 'slug' => 'go'],
            ['name' => 'Rust', 'slug' => 'rust'],
            ['name' => 'TypeScript', 'slug' => 'typescript'],
            ['name' => 'CSS', 'slug' => 'css'],
            ['name' => 'HTML', 'slug' => 'html'],
            ['name' => 'Nginx', 'slug' => 'nginx'],
        ];
        $tagIds = [];
        foreach ($tagData as $td) {
            $existing = $db->table('tag')->where('slug', $td['slug'])->first();
            if ($existing !== null) {
                $tagIds[$td['slug']] = (int) $existing['id'];
                continue;
            }
            $tagIds[$td['slug']] = (int) $db->table('tag')->insert([
                'name' => $td['name'],
                'slug' => $td['slug'],
                'post_count' => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        // ── 分类（4 级） ──
        $catStructure = [
            '技术' => [
                '后端开发' => [
                    'PHP' => ['Laravel', 'ThinkPHP'],
                    'Python' => ['Django', 'Flask'],
                ],
                '前端开发' => [
                    'JavaScript' => ['Vue.js', 'React'],
                    'CSS' => ['Sass', 'Tailwind'],
                ],
                '运维部署' => [
                    'Linux' => ['CentOS', 'Ubuntu'],
                    '容器化' => ['Docker', 'Kubernetes'],
                ],
            ],
            '生活' => [
                '阅读' => [
                    '技术书籍' => [],
                    '人文社科' => [],
                ],
                '旅行' => [
                    '国内' => [],
                    '国外' => [],
                ],
                '美食' => [
                    '中餐' => [],
                    '西餐' => [],
                ],
            ],
            '随笔' => [
                '思考' => [
                    '产品思维' => [],
                    '技术洞察' => [],
                ],
                '日记' => [],
                '转载' => [],
            ],
        ];

        $catIds = [];
        $this->seedCategories($db, $catStructure, null, $catIds);

        // ── 文章 ──
        $articleTitles = [
            '快速入门 Laravel 11：新特性一览',
            'ThinkPHP 8 实战：构建 RESTful API',
            'Django 5.0 升级指南：你需要知道的变化',
            'Flask 微框架实战：从零开始搭建博客',
            'Vue 3 Composition API 深度解析',
            'React Server Components 入门教程',
            '使用 Tailwind CSS 构建现代化界面',
            'Sass 高级技巧：Mixin 与函数的妙用',
            'Docker Compose 部署 LNMP 环境',
            'Kubernetes 入门：Pod 与 Service 详解',
            'CentOS 服务器安全加固指南',
            'Ubuntu 下搭建 PHP 开发环境',
            'MySQL 8.0 索引优化实战',
            'Redis 缓存策略：穿透、击穿与雪崩',
            'Go 语言并发编程：Goroutine 与 Channel',
            'Rust 所有权系统：从入门到理解',
            'TypeScript 高级类型体操指南',
            'Nginx 反向代理与负载均衡配置',
            'PHP 8.3 新特性解读',
            'JavaScript 异步编程演进：从回调到 Async/Await',
            'Python 数据分析：Pandas 入门',
            'CSS Grid 布局完全指南',
            'Docker 多阶段构建优化镜像大小',
            'Linux Shell 脚本编程基础',
            'Vue Router 4 动态路由与权限控制',
            'React Hooks 最佳实践',
            'MySQL 主从复制与读写分离',
            'Redis 数据结构与应用场景',
            'Python 爬虫实战：Scrapy 框架入门',
            'Django REST Framework 快速上手',
        ];

        $postImages = [];
        for ($i = 1; $i <= 30; $i++) {
            $postImages[] = 'https://picsum.photos/400/300?random=' . $i;
        }

        // 获取所有叶子分类（最深层级）
        $leafCats = [];
        $this->collectLeafCategories($db, $catIds, $leafCats);
        $allCats = array_values($catIds);

        $postIndex = 0;
        foreach ($articleTitles as $title) {
            $img = $postImages[$postIndex % count($postImages)];
            $postIndex++;

            // 随机选择 1-2 个分类（优先叶子分类）
            $catCount = random_int(1, 2);
            $postCats = $leafCats !== []
                ? (array) array_rand(array_flip($leafCats), min($catCount, count($leafCats)))
                : [];
            if ($postCats === [] && $allCats !== []) {
                $postCats = (array) array_rand(array_flip($allCats), min($catCount, count($allCats)));
            }
            $primaryCat = !empty($postCats) ? (int) reset($postCats) : null;

            // 随机选 2-4 个标签
            $tagSlugs = array_keys($tagIds);
            $tagCount = random_int(2, min(4, count($tagSlugs)));
            $postTagSlugs = (array) array_rand(array_flip($tagSlugs), $tagCount);

            $slug = 'demo-post-' . $postIndex;
            $content = '<p>这是一篇演示文章，用于测试系统功能。</p>'
                . '<p><img src="' . $img . '" alt="演示图片" loading="lazy"></p>'
                . '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>'
                . '<p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>';

            $daysAgo = random_int(0, 30);
            $publishedAt = gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400);

            $existing = $db->table('post')->where('slug', $slug)->first();
            if ($existing !== null) {
                continue;
            }

            $postId = (int) $db->table('post')->insert([
                'author_id' => $authorId,
                'primary_category_id' => $primaryCat,
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'excerpt' => mb_substr(strip_tags($content), 0, 120),
                'type' => 'article',
                'status' => 'publish',
                'comment_status' => 'open',
                'is_top' => 0,
                'view_count' => random_int(10, 5000),
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            // 关联分类
            foreach ($postCats as $catId) {
                $db->table('post_category')->insert([
                    'post_id' => $postId,
                    'category_id' => (int) $catId,
                ]);
            }

            // 关联标签
            foreach ($postTagSlugs as $tagSlug) {
                $db->table('post_tag')->insert([
                    'post_id' => $postId,
                    'tag_id' => $tagIds[$tagSlug],
                ]);
            }

            // 随机 0-3 条评论
            $commentCount = random_int(0, 3);
            for ($c = 0; $c < $commentCount; $c++) {
                $db->table('comment')->insert([
                    'post_id' => $postId,
                    'author_id' => null,
                    'parent_id' => null,
                    'author_name' => '访客' . random_int(100, 999),
                    'author_email' => 'guest' . random_int(100, 999) . '@example.com',
                    'content' => $this->randomComment(),
                    'status' => random_int(0, 4) > 1 ? 'approved' : 'pending',
                    'ip' => '127.0.0.1',
                    'created_at' => gmdate('Y-m-d H:i:s', time() - random_int(0, $daysAgo * 86400)),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }

        // ── 页面 ──
        $pages = [
            ['title' => '关于我们', 'slug' => 'about', 'content' => '<p>这是关于我们的页面。FinchPHP 是一款轻量级的内容管理系统。</p>'],
            ['title' => '联系方式', 'slug' => 'contact', 'content' => '<p>如有任何问题，请联系我们：hello@finch.local</p>'],
            ['title' => '隐私政策', 'slug' => 'privacy', 'content' => '<p>本站重视用户隐私，我们承诺不会泄露您的个人信息。</p>'],
        ];
        foreach ($pages as $page) {
            $existing = $db->table('post')->where('slug', $page['slug'])->first();
            if ($existing !== null) {
                continue;
            }
            $db->table('post')->insert([
                'author_id' => $authorId,
                'primary_category_id' => null,
                'title' => $page['title'],
                'slug' => $page['slug'],
                'content' => $page['content'],
                'excerpt' => '',
                'type' => 'page',
                'status' => 'publish',
                'comment_status' => 'closed',
                'is_top' => 0,
                'view_count' => random_int(5, 200),
                'published_at' => gmdate('Y-m-d H:i:s'),
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        // ── 友情链接 ──
        $friendLinks = [
            ['title' => 'GitHub', 'url' => 'https://github.com', 'description' => '全球最大的代码托管平台'],
            ['title' => 'Stack Overflow', 'url' => 'https://stackoverflow.com', 'description' => '程序员问答社区'],
            ['title' => 'MDN Web Docs', 'url' => 'https://developer.mozilla.org', 'description' => 'Web 技术文档'],
            ['title' => 'PHP 官网', 'url' => 'https://www.php.net', 'description' => 'PHP 官方网站'],
            ['title' => 'Laravel', 'url' => 'https://laravel.com', 'description' => '优雅的 PHP 框架'],
        ];
        foreach ($friendLinks as $i => $fl) {
            $db->table('link')->insert([
                'group_key' => 'friend',
                'parent_id' => null,
                'title' => $fl['title'],
                'url' => $fl['url'],
                'link_type' => 'custom',
                'ref_id' => null,
                'target' => '_blank',
                'icon' => '',
                'description' => $fl['description'],
                'sort_order' => $i,
                'is_active' => 1,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        // ── 导航栏 ──
        $navItems = [
            ['title' => '首页', 'url' => '/', 'icon' => 'ti ti-home', 'sort_order' => 0],
            ['title' => '关于', 'url' => '/about', 'icon' => 'ti ti-info-circle', 'sort_order' => 1],
            ['title' => '联系', 'url' => '/contact', 'icon' => 'ti ti-mail', 'sort_order' => 2],
        ];
        foreach ($navItems as $nav) {
            $db->table('link')->insert([
                'group_key' => 'navbar',
                'parent_id' => null,
                'title' => $nav['title'],
                'url' => $nav['url'],
                'link_type' => 'custom',
                'ref_id' => null,
                'target' => '_self',
                'icon' => $nav['icon'],
                'description' => '',
                'sort_order' => $nav['sort_order'],
                'is_active' => 1,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        // 更新分类的 post_count
        $this->recountCategories($db);

        // 更新标签的 post_count
        $this->recountTags($db);

        // 清空缓存
        $this->app->cache?->flushGroups(['plugin', 'settings', 'search', 'home', 'post', 'comment']);
    }

    /**
     * 递归创建分类。
     *
     * @param array<string,mixed> $structure
     * @param array<string,int> $catIds
     */
    private function seedCategories(Database $db, array $structure, ?int $parentId, array &$catIds): void
    {
        $sortOrder = 0;
        foreach ($structure as $name => $children) {
            $slug = $this->slugify($name);
            $existing = $db->table('category')->where('slug', $slug)->first();
            if ($existing !== null) {
                $catIds[$name] = (int) $existing['id'];
            } else {
                $catIds[$name] = (int) $db->table('category')->insert([
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => '',
                    'sort_order' => $sortOrder,
                    'post_count' => 0,
                    'status' => 'active',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }

            if (is_array($children) && $children !== []) {
                $this->seedCategories($db, $children, $catIds[$name], $catIds);
            }

            $sortOrder++;
        }
    }

    /** 收集叶子分类（最深层级的分类 ID） */
    private function collectLeafCategories(Database $db, array $catIds, array &$leafCats): void
    {
        $parentIds = array_unique(array_filter(array_map('intval', $catIds)));
        foreach ($parentIds as $id) {
            $hasChildren = $db->table('category')->where('parent_id', $id)->exists();
            if (!$hasChildren) {
                $leafCats[] = $id;
            }
        }
    }

    private function recountCategories(Database $db): void
    {
        $categories = $db->table('category')->get();
        foreach ($categories as $cat) {
            $count = $db->table('post_category')
                ->where('category_id', (int) $cat['id'])
                ->count();
            $db->table('category')->where('id', (int) $cat['id'])->update([
                'post_count' => $count,
            ]);
        }
    }

    private function recountTags(Database $db): void
    {
        $tags = $db->table('tag')->get();
        foreach ($tags as $tag) {
            $count = $db->table('post_tag')
                ->where('tag_id', (int) $tag['id'])
                ->count();
            $db->table('tag')->where('id', (int) $tag['id'])->update([
                'post_count' => $count,
            ]);
        }
    }

    private function randomComment(): string
    {
        $comments = [
            '写得很好，学习了！',
            '感谢分享，很有帮助。',
            '终于找到解决方案了，谢谢！',
            '请问这个方案在生产环境稳定吗？',
            '收藏了，后续慢慢研究。',
            '遇到同样的问题，看了这篇文章解决了。',
            '解释得很清楚，点赞！',
            '有没有更详细的示例代码？',
            '实测有效，感谢博主。',
            '这个思路很新颖，之前没想过。',
            '踩坑了，看到这篇文章才走出来。',
            '补充一下，在 PHP 8.2 上也能跑。',
            '强烈推荐！',
            '不错不错，继续加油。',
            '终于有人把这个问题说清楚了。',
        ];

        return $comments[array_rand($comments)];
    }

    private function slugify(string $text): string
    {
        $map = [
            '技术' => 'tech', '后端开发' => 'backend', '前端开发' => 'frontend', '运维部署' => 'devops',
            'PHP' => 'php', 'Python' => 'python', 'JavaScript' => 'javascript', 'CSS' => 'css',
            'Linux' => 'linux', '容器化' => 'container',
            'Laravel' => 'laravel', 'ThinkPHP' => 'thinkphp', 'Django' => 'django', 'Flask' => 'flask',
            'Vue.js' => 'vuejs', 'React' => 'react',
            'Sass' => 'sass', 'Tailwind' => 'tailwind',
            'CentOS' => 'centos', 'Ubuntu' => 'ubuntu',
            'Docker' => 'docker', 'Kubernetes' => 'kubernetes',
            '生活' => 'life', '阅读' => 'reading', '旅行' => 'travel', '美食' => 'food',
            '技术书籍' => 'tech-books', '人文社科' => 'humanities',
            '国内' => 'domestic', '国外' => 'overseas',
            '中餐' => 'chinese-food', '西餐' => 'western-food',
            '随笔' => 'essay', '思考' => 'thoughts', '日记' => 'diary', '转载' => 'repost',
            '产品思维' => 'product-thinking', '技术洞察' => 'tech-insights',
        ];

        return $map[$text] ?? strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
    }

    private function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '', $name) . '`';
    }
}
