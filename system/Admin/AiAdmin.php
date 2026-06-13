<?php

/**
 * Finch\Admin\AiAdmin - AI 大模型设置独立页面
 *
 * 后台可配置多个 OpenAI 协议兼容模型（见 PROJECT_PLAN §4.17）。
 * 从 SettingAdmin 中独立出来，提供更丰富的 AI 模型管理功能。
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Model\AiModel;

final class AiAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $lang = $this->app->lang;
        $models = $this->listModels();
        $token = $this->escape($this->app->session->csrfToken());

        $notice = $this->noticeHtml();

        $rows = '';
        foreach ($models as $model) {
            $id = (int) ($model['id'] ?? 0);
            $platform = (string) ($model['platform'] ?? '');
            $modelName = (string) ($model['model'] ?? '');
            $protocol = (string) ($model['protocol'] ?? 'openai');
            $isDefault = (bool) ($model['is_default'] ?? false);
            $status = (string) ($model['status'] ?? 'disabled');
            $maskedKey = $this->maskKey((string) ($model['api_key_encrypted'] ?? ''));

            $statusLabel = $status === 'enabled'
                ? '<strong>' . $this->escape($lang->get('admin.common.enabled')) . '</strong>'
                : $this->escape($lang->get('admin.common.disable'));

            $defaultBadge = $isDefault
                ? ' <span class="fp-badge-active">' . $this->escape($lang->get('admin.ai.default')) . '</span>'
                : '';

            $rows .= '<tr>'
                . '<td>' . $this->escape($platform) . '</td>'
                . '<td>' . $this->escape($modelName) . $defaultBadge . '</td>'
                . '<td>' . $this->escape($protocol) . '</td>'
                . '<td>' . $maskedKey . '</td>'
                . '<td>' . $statusLabel . '</td>'
                . '<td>'
                . '<a href="/admin/ai/edit?id=' . $id . '">' . $this->escape($lang->get('admin.common.edit')) . '</a> '
                . '<form method="post" action="/admin/ai/delete" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="submit" class="secondary" onclick="return confirm(\'' . $this->escape($lang->get('admin.ai.confirm_delete')) . '\')">' . $this->escape($lang->get('admin.common.delete')) . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted">' . $this->escape($lang->get('admin.ai.no_models')) . '</td></tr>';
        }

        $body = '<section class="panel"><h1>' . $this->escape($lang->get('admin.ai.title')) . '</h1>'
            . $notice
            . '<div class="actions"><a href="/admin/ai/create" class="fp-btn-wide">' . $this->escape($lang->get('admin.ai.add_model')) . '</a></div>'
            . '<table><thead><tr>'
            . '<th>' . $this->escape($lang->get('admin.ai.th_platform')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.ai.th_model')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.ai.th_protocol')) . '</th>'
            . '<th>API Key</th>'
            . '<th>' . $this->escape($lang->get('admin.common.status')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.common.actions')) . '</th>'
            . '</tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '</section>';

        return $this->html($this->adminShell($lang->get('admin.ai.title'), $body));
    }

    public function create(): Response
    {
        return $this->form(new AiModel());
    }

    public function edit(): Response
    {
        $id = (int) $this->request->query('id', 0);
        $model = AiModel::find($id);

        if ($model === null) {
            return $this->redirect('/admin/ai');
        }

        return $this->form($model);
    }

    public function save(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $data = $this->collectInput();

        if ($id > 0) {
            $this->app->db->table('ai_model')->where('id', $id)->update($data);
        } else {
            $this->app->db->table('ai_model')->insert($data);
        }

        $this->app->cache?->flushGroups(['settings', 'ai']);

        return $this->redirect('/admin/ai?saved=1');
    }

    public function delete(): Response
    {
        $id = (int) $this->request->post('id', 0);

        if ($id > 0) {
            $this->app->db->table('ai_model')->where('id', $id)->delete();
            $this->app->cache?->flushGroups(['settings', 'ai']);
        }

        return $this->redirect('/admin/ai?deleted=1');
    }

    private function form(AiModel $model): Response
    {
        $lang = $this->app->lang;
        $isNew = !$model->exists();
        $token = $this->escape($this->app->session->csrfToken());

        $platforms = $this->registeredProviders();

        $protocolOptions = '';
        $currentProtocol = (string) ($model->protocol ?? 'openai');
        foreach ($platforms as $protocolId => $protocolName) {
            $selected = $currentProtocol === $protocolId ? ' selected' : '';
            $protocolOptions .= '<option value="' . $this->escape($protocolId) . '"' . $selected . '>' . $this->escape($protocolName) . '</option>';
        }

        $statusOptions = '';
        $currentStatus = (string) ($model->status ?? 'disabled');
        foreach (['enabled' => $lang->get('admin.common.enabled'), 'disabled' => $lang->get('admin.common.disable')] as $val => $label) {
            $selected = $currentStatus === $val ? ' selected' : '';
            $statusOptions .= '<option value="' . $this->escape($val) . '"' . $selected . '>' . $this->escape($label) . '</option>';
        }

        $body = '<section class="panel"><h1>' . $this->escape($isNew ? $lang->get('admin.ai.add_model') : $lang->get('admin.ai.edit_model')) . '</h1>'
            . '<form method="post" action="/admin/ai/save" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . '<input type="hidden" name="id" value="' . (int) ($model->id ?? 0) . '">'
            . '<label>' . $this->escape($lang->get('admin.ai.field_platform'))
            . '<input type="text" name="platform" value="' . $this->escape((string) ($model->platform ?? '')) . '" required></label>'
            . '<label>' . $this->escape($lang->get('admin.ai.field_protocol'))
            . '<select name="protocol">' . $protocolOptions . '</select></label>'
            . '<label>' . $this->escape($lang->get('admin.ai.field_base_url'))
            . '<input type="text" name="base_url" value="' . $this->escape((string) ($model->base_url ?? 'https://api.openai.com/v1')) . '" placeholder="https://api.openai.com/v1"></label>'
            . '<label>' . $this->escape($lang->get('admin.ai.field_api_key'))
            . '<input type="password" name="api_key" placeholder="' . $this->escape($lang->get('admin.ai.api_key_placeholder')) . '"></label>'
            . '<label>' . $this->escape($lang->get('admin.ai.field_model'))
            . '<input type="text" name="model" value="' . $this->escape((string) ($model->model ?? 'gpt-4o-mini')) . '" required></label>'
            . '<label>' . $this->escape($lang->get('admin.ai.field_params'))
            . '<textarea name="params_json" rows="3" placeholder=\'{"temperature":0.7,"max_tokens":2048}\'>' . $this->escape((string) ($model->params_json ?? '{}')) . '</textarea></label>'
            . '<label class="fp-check-row"><input type="checkbox" name="is_default" value="1"' . ((bool) ($model->is_default ?? false) ? ' checked' : '') . '>' . $this->escape($lang->get('admin.ai.field_default')) . '</label>'
            . '<label>' . $this->escape($lang->get('admin.common.status'))
            . '<select name="status">' . $statusOptions . '</select></label>'
            . '<div class="actions"><button type="submit">' . $this->escape($lang->get('admin.common.save')) . '</button><a href="/admin/ai">' . $this->escape($lang->get('admin.common.cancel')) . '</a></div>'
            . '</form>'
            . '</section>';

        return $this->html($this->adminShell($lang->get('admin.ai.title'), $body));
    }

    /** @return list<array<string,mixed>> */
    private function listModels(): array
    {
        $rows = $this->app->db->table('ai_model')->orderBy('id', 'ASC')->get();

        return array_map(function (array $row): array {
            $row['api_key_encrypted'] = $this->maskKey((string) ($row['api_key_encrypted'] ?? ''));

            return $row;
        }, $rows);
    }

    /** @return array<string, string> */
    private function registeredProviders(): array
    {
        $providers = ['openai' => 'OpenAI'];

        $result = $this->app->hooks->filter('fp_register_ai_provider', [], []);
        if (is_array($result)) {
            foreach ($result as $item) {
                if (is_array($item) && isset($item['protocol'], $item['name']) && is_string($item['protocol']) && is_string($item['name'])) {
                    $providers[$item['protocol']] = $item['name'];
                }
            }
        }

        return $providers;
    }

    /** @return array<string,mixed> */
    private function collectInput(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $apiKey = trim((string) $this->request->post('api_key', ''));
        $paramsJson = trim((string) $this->request->post('params_json', '{}'));

        // 验证 JSON 格式
        $decoded = json_decode($paramsJson, true);
        if (!is_array($decoded)) {
            $paramsJson = '{}';
        }

        $data = [
            'platform' => trim((string) $this->request->post('platform', '')),
            'protocol' => trim((string) $this->request->post('protocol', 'openai')),
            'base_url' => trim((string) $this->request->post('base_url', '')),
            'model' => trim((string) $this->request->post('model', '')),
            'params_json' => $paramsJson,
            'is_default' => $this->request->post('is_default') === '1' ? 1 : 0,
            'status' => $this->request->post('status') === 'enabled' ? 'enabled' : 'disabled',
            'updated_at' => $now,
        ];

        // API Key 加密存储（如果提供了新值）
        if ($apiKey !== '') {
            $appKey = (string) ($this->app->config['app']['key'] ?? 'finch-secret-key');
            $data['api_key_encrypted'] = base64_encode(openssl_encrypt($apiKey, 'AES-256-CBC', $appKey, 0, substr(md5($appKey), 0, 16)));
        }

        return $data;
    }

    private function maskKey(string $key): string
    {
        if ($key === '') {
            return '-';
        }

        // 尝试解密后脱敏
        try {
            $appKey = (string) ($this->app->config['app']['key'] ?? 'finch-secret-key');
            $decrypted = openssl_decrypt(base64_decode($key), 'AES-256-CBC', $appKey, 0, substr(md5($appKey), 0, 16));
            if (is_string($decrypted)) {
                $len = mb_strlen($decrypted);
                if ($len <= 8) {
                    return str_repeat('*', $len);
                }

                return mb_substr($decrypted, 0, 4) . str_repeat('*', $len - 8) . mb_substr($decrypted, -4);
            }
        } catch (\Throwable) {
            // 解密失败，显示原始加密值的脱敏版
        }

        $len = mb_strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return mb_substr($key, 0, 4) . '...' . mb_substr($key, -4);
    }

    private function noticeHtml(): string
    {
        $lang = $this->app->lang;
        $saved = (string) $this->request->query('saved', '');
        $deleted = (string) $this->request->query('deleted', '');

        if ($saved === '1') {
            return '<div class="fp-notice-success">' . $this->escape($lang->get('admin.ai.saved')) . '</div>';
        }
        if ($deleted === '1') {
            return '<div class="fp-notice-success">' . $this->escape($lang->get('admin.ai.deleted')) . '</div>';
        }

        return '';
    }
}
