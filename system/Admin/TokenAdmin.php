<?php

/**
 * Finch\Admin\TokenAdmin - API Token 后台管理
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Model\ApiToken;
use Finch\Model\User;
use Finch\Service\ApiTokenService;

final class TokenAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $lang = $this->app->lang;
        $page = max(1, (int) $this->request->query('page', 1));
        $pageData = ApiToken::query()->orderBy('id', 'DESC')->paginate($page, 20);

        $users = [];
        $userIds = [];
        foreach ($pageData['data'] as $row) {
            if (is_numeric($row['user_id'] ?? null)) {
                $userIds[] = (int) $row['user_id'];
            }
        }
        $userIds = array_values(array_unique($userIds));
        if ($userIds !== []) {
            foreach (User::query()->whereIn('id', $userIds)->get() as $row) {
                $users[(int) $row['id']] = (string) ($row['username'] ?? ('#' . $row['id']));
            }
        }

        return $this->html($this->adminShell($lang->get('admin.token.title'), $this->content($pageData, $users)));
    }

    public function issue(): Response
    {
        $validator = $this->validate([
            'user_id' => 'required|numeric|min:1',
            'name' => 'required|max:100',
            'abilities' => 'required|max:500',
            'expires_days' => 'numeric|min:0|max:3650',
        ]);

        if ($validator->fails()) {
            $page = max(1, (int) $this->request->query('page', 1));
            $pageData = ApiToken::query()->orderBy('id', 'DESC')->paginate($page, 20);

            return $this->html($this->adminShell('API Token 管理', $this->content($pageData, $this->userMap($pageData), $validator->errors())), 422);
        }

        $userId = (int) $this->request->post('user_id', 0);
        $user = User::find($userId);
        if (!$user instanceof User) {
            return $this->redirect('/admin/tokens?issued=0');
        }

        $abilitiesRaw = (string) $this->request->post('abilities', '*');
        $abilities = array_values(array_filter(array_map('trim', explode(',', $abilitiesRaw)), static fn (string $v): bool => $v !== ''));
        if ($abilities === []) {
            $abilities = ['*'];
        }

        $expiresDays = max(0, (int) $this->request->post('expires_days', 0));
        $expiresAt = $expiresDays > 0
            ? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+' . $expiresDays . ' days')
            : null;

        $result = $this->tokenService()->create($user, (string) $this->request->post('name', ''), $abilities, $expiresAt);
        $plainToken = (string) $result['plain_token'];
        $tokenId = (int) ($result['token']->id ?? 0);

        $this->app->session->set('issued_plain_token', $plainToken);
        $this->app->session->set('issued_token_id', $tokenId);

        return $this->redirect('/admin/tokens?issued=1');
    }

    public function revoke(): Response
    {
        $id = (int) $this->request->post('id', 0);
        if ($id > 0) {
            $token = ApiToken::find($id);
            if ($token instanceof ApiToken) {
                $token->delete();
            }
        }

        $page = max(1, (int) $this->request->query('page', 1));

        return $this->redirect('/admin/tokens?page=' . $page . '&revoked=1');
    }

    /**
     * @param array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int} $pageData
     * @param array<int,string> $users
     * @param array<string,list<string>> $errors
     */
    private function content(array $pageData, array $users, array $errors = []): string
    {
        $issued = $this->request->query('issued') === '1';
        $revoked = $this->request->query('revoked') === '1';

        $notice = '';
        if ($issued) {
            $plain = (string) $this->app->session->get('issued_plain_token', '');
            $tokenId = (int) $this->app->session->get('issued_token_id', 0);
            if ($plain !== '' && $tokenId > 0) {
                $notice = '<div class="fp-notice-success">Token 已签发（ID ' . $tokenId . '），明文仅显示一次：</div>'
                    . '<div class="fp-token-plain">' . $this->escape($plain) . '</div>';
            }
            $this->app->session->forget('issued_plain_token');
            $this->app->session->forget('issued_token_id');
        }

        if ($revoked) {
            $notice .= '<div class="fp-notice-success fp-notice-gap-top">Token 已撤销。</div>';
        }

        foreach ($errors as $field => $messages) {
            if (!isset($messages[0])) {
                continue;
            }
            $notice .= '<div class="fp-error-text">' . $this->escape($field . ': ' . $messages[0]) . '</div>';
        }

        $rows = '';
        foreach ($pageData['data'] as $row) {
            $id = (int) $row['id'];
            $userId = (int) ($row['user_id'] ?? 0);
            $rows .= '<tr>'
                . '<td>' . $id . '</td>'
                . '<td>' . $this->escape((string) ($users[$userId] ?? ('用户#' . $userId))) . '</td>'
                . '<td>' . $this->escape((string) ($row['name'] ?? '')) . '</td>'
                . '<td><code>' . $this->escape((string) ($row['abilities'] ?? '[]')) . '</code></td>'
                . '<td>' . $this->escape((string) ($row['expires_at'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($row['last_used_at'] ?? '')) . '</td>'
                . '<td>'
                . '<form method="post" action="/admin/tokens/revoke?page=' . (int) $pageData['page'] . '" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="submit" class="secondary">撤销</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="muted">暂无 Token</td></tr>';
        }

        $userOptions = '';
        foreach (User::query()->orderBy('id')->get() as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            $userOptions .= '<option value="' . $uid . '">' . $this->escape((string) ($u['username'] ?? ('用户#' . $uid))) . '</option>';
        }

        return '<section class="panel"><h1>API Token 管理</h1>'
            . $notice
            . '<form method="post" action="/admin/tokens/issue" class="fp-form-grid fp-form-grid-token">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<label>用户<select name="user_id">' . $userOptions . '</select></label>'
            . '<label>名称<input name="name" placeholder="例如：miniapp"></label>'
            . '<label>能力（逗号分隔）<input name="abilities" value="*" placeholder="posts:read,uploads:create"></label>'
            . '<label>过期天数（0=不过期）<input type="number" name="expires_days" min="0" max="3650" value="0"></label>'
            . '<button type="submit">签发 Token</button>'
            . '</form>'
            . '</section>'
            . '<section class="panel"><table><thead><tr><th>ID</th><th>用户</th><th>名称</th><th>能力</th><th>过期</th><th>最近使用</th><th>操作</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . $this->pager($pageData)
            . '</section>';
    }

    /**
     * @param array{total:int,page:int,last_page:int} $pageData
     */
    private function pager(array $pageData): string
    {
        $page = (int) $pageData['page'];
        $lastPage = max(1, (int) $pageData['last_page']);

        $parts = ['<div class="actions fp-actions-gap-top">'];
        if ($page > 1) {
            $parts[] = '<a href="/admin/tokens?page=' . ($page - 1) . '">上一页</a>';
        }

        $parts[] = '<span class="muted">第 ' . $page . ' / ' . $lastPage . ' 页，共 ' . (int) $pageData['total'] . ' 条</span>';

        if ($page < $lastPage) {
            $parts[] = '<a href="/admin/tokens?page=' . ($page + 1) . '">下一页</a>';
        }

        $parts[] = '</div>';

        return implode('', $parts);
    }

    /**
     * @param array{data:list<array<string,mixed>>} $pageData
     * @return array<int,string>
     */
    private function userMap(array $pageData): array
    {
        $map = [];
        foreach ($pageData['data'] as $row) {
            $uid = is_numeric($row['user_id'] ?? null) ? (int) $row['user_id'] : 0;
            if ($uid <= 0 || isset($map[$uid])) {
                continue;
            }

            $user = User::find($uid);
            $map[$uid] = $user instanceof User ? (string) ($user->username ?? ('用户#' . $uid)) : ('用户#' . $uid);
        }

        return $map;
    }

    private function tokenService(): ApiTokenService
    {
        return new ApiTokenService($this->app->db);
    }
}
