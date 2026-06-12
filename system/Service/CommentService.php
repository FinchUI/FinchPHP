<?php

/**
 * Finch\Service\CommentService - 评论读取与提交服务
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Core\Database;
use Finch\Core\Request;
use Finch\Core\Settings;
use Finch\Model\Comment;
use Finch\Model\User;

final class CommentService
{
    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
    ) {
    }

    /**
     * 已审核评论分页。
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function approvedPage(int $postId, int $page = 1, int $perPage = 20): array
    {
        $query = $this->db->table('comment')
            ->where('post_id', $postId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC');

        $pageData = $query->paginate($page, $perPage);
        $pageData['data'] = array_map(fn (array $row): array => $this->resource($row), $pageData['data']);

        return $pageData;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedPost(int $postId): ?array
    {
        return $this->db->table('post')
            ->where('id', $postId)
            ->where('status', 'publish')
            ->whereNull('deleted_at')
            ->where('published_at', '<=', gmdate('Y-m-d H:i:s'))
            ->first();
    }

    /** @return array{ok:bool,code:int,message:string,status:int,comment?:array<string,mixed>} */
    public function submit(int $postId, array $data, ?User $user, Request $request): array
    {
        $post = $this->findPublishedPost($postId);
        if ($post === null) {
            return $this->failure('文章不存在', 4001, 404);
        }

        if ($this->settings->get('comment_enabled', true) !== true) {
            return $this->failure('评论已禁用', 6001, 403);
        }

        if ((string) ($post['comment_status'] ?? 'open') !== 'open') {
            return $this->failure('文章评论已关闭', 6002, 403);
        }

        if ($user === null && $this->settings->get('comment_login_required', false) === true) {
            return $this->failure('评论需要登录', 6003, 401);
        }

        if ($user === null && $this->settings->get('comment_guest_allowed', true) !== true) {
            return $this->failure('不允许游客评论', 6004, 403);
        }

        $parentId = isset($data['parent_id']) && is_numeric($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId !== null && !$this->validParent($postId, $parentId)) {
            return $this->failure('父评论不存在', 6005, 422);
        }

        $status = $this->settings->get('comment_need_audit', true) === true ? 'pending' : 'approved';
        $content = $this->sanitizeContent((string) ($data['content'] ?? ''));
        $authorName = $user instanceof User ? (string) ($user->display_name ?? $user->username) : trim((string) ($data['author_name'] ?? ''));
        $authorEmail = $user instanceof User ? (string) ($user->email ?? '') : trim((string) ($data['author_email'] ?? ''));

        $comment = Comment::create([
            'post_id'      => $postId,
            'author_id'    => $user instanceof User ? (int) $user->id : null,
            'parent_id'    => $parentId,
            'author_name'  => $authorName,
            'author_email' => $authorEmail,
            'content'      => $content,
            'status'       => $status,
            'ip'           => $request->ip(),
            'user_agent'   => mb_substr($request->userAgent(), 0, 1000),
        ]);

        $this->invalidateCommentCache();

        $message = $status === 'pending' ? '评论已提交，等待审核' : '评论已发布';

        return [
            'ok'      => true,
            'code'    => 0,
            'message' => $message,
            'status'  => $status === 'pending' ? 202 : 201,
            'comment' => $this->resource($comment->toArray(), includeEmail: true),
        ];
    }

    /** @return array<string, mixed> */
    private function resource(array $row, bool $includeEmail = false): array
    {
        $data = [
            'id'          => (int) $row['id'],
            'post_id'     => (int) $row['post_id'],
            'author_id'   => isset($row['author_id']) ? (int) $row['author_id'] : null,
            'parent_id'   => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            'author_name' => (string) ($row['author_name'] ?? ''),
            'content'     => (string) $row['content'],
            'status'      => (string) $row['status'],
            'created_at'  => $row['created_at'],
            'updated_at'  => $row['updated_at'],
        ];

        if ($includeEmail) {
            $data['author_email'] = (string) ($row['author_email'] ?? '');
        }

        return $data;
    }

    private function validParent(int $postId, int $parentId): bool
    {
        return $this->db->table('comment')
            ->where('id', $parentId)
            ->where('post_id', $postId)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function sanitizeContent(string $content): string
    {
        return trim(strip_tags($content));
    }

    private function invalidateCommentCache(): void
    {
        (new Cache($this->db))->flushGroups(['comment', 'post', 'home']);
    }

    /** @return array{ok:bool,code:int,message:string,status:int} */
    private function failure(string $message, int $code, int $status): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message, 'status' => $status];
    }
}
