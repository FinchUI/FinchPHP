<?php

/**
 * Finch\Service\UploadService - 上传与媒体安全服务
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Asset;
use Finch\Core\Database;
use Finch\Core\Settings;
use Finch\Core\Thumbnail;
use RuntimeException;

final class UploadService
{
    private const DEFAULT_MAX_SIZE = 5242880; // 5 MB

    private const DEFAULT_ALLOWED_EXT = 'jpg,jpeg,png,gif,webp,pdf,txt,zip';

    /** @var array<string, list<string>> */
    private const EXT_MIME = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ?Asset $asset = null,
        private readonly ?Settings $settings = null,
        private readonly ?Thumbnail $thumbnail = null,
    ) {
    }

    /**
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function page(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $pageData = $this->db->table('upload')
            ->orderBy('id', 'DESC')
            ->paginate($page, $perPage);

        $pageData['data'] = array_map(fn (array $row): array => $this->present($row), $pageData['data']);

        return $pageData;
    }

    /** @return array<string,mixed> */
    public function storeFromRequest(mixed $file, ?int $userId = null, ?int $postId = null): array
    {
        $entry = $this->normalizeSingleFile($file);
        $this->ensureUploadOk($entry);

        $tmpName = (string) $entry['tmp_name'];
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('非法上传来源。');
        }

        $size = (int) ($entry['size'] ?? 0);
        if ($size <= 0) {
            throw new RuntimeException('上传文件为空。');
        }

        $maxSize = $this->maxSize();
        if ($size > $maxSize) {
            throw new RuntimeException('文件大小超过限制，最大 ' . $this->formatBytes($maxSize) . '。');
        }

        $originalName = $this->safeOriginalName((string) ($entry['name'] ?? 'upload.bin'));
        $mimeType = $this->detectMimeType($tmpName);
        if ($mimeType === '') {
            throw new RuntimeException('无法识别文件 MIME 类型。');
        }

        $extension = $this->resolveExtension($originalName, $mimeType);
        $this->assertAllowed($extension, $mimeType);

        [$relativePath, $absolutePath, $filename] = $this->allocateDestination($extension);

        if (!@move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('写入上传文件失败。');
        }

        @chmod($absolutePath, 0644);

        $width = null;
        $height = null;
        if ($this->isImageMime($mimeType)) {
            $thumb = $this->thumbnail ?? new Thumbnail();
            $thumb->normalize($absolutePath, $mimeType);
            [$width, $height] = $this->imageSize($absolutePath);
        }

        $id = (int) $this->db->table('upload')->insert([
            'user_id' => $userId,
            'post_id' => $postId,
            'filename' => $filename,
            'original_name' => $originalName,
            'path' => $relativePath,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $row = $this->db->table('upload')->where('id', $id)->first();
        if ($row === null) {
            throw new RuntimeException('上传记录写入失败。');
        }

        return $this->present($row);
    }

    /** @return array<string,mixed> */
    private function normalizeSingleFile(mixed $file): array
    {
        if (!is_array($file)) {
            throw new RuntimeException('缺少上传文件。');
        }

        if (isset($file['name']) && is_array($file['name'])) {
            throw new RuntimeException('暂不支持多文件上传，请单次上传一个文件。');
        }

        return $file;
    }

    /** @param array<string,mixed> $file */
    private function ensureUploadOk(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件超过服务器上传大小限制。',
            UPLOAD_ERR_PARTIAL => '文件上传不完整，请重试。',
            UPLOAD_ERR_NO_FILE => '请选择要上传的文件。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录。',
            UPLOAD_ERR_CANT_WRITE => '服务器写入失败。',
            UPLOAD_ERR_EXTENSION => '上传被扩展中断。',
            default => '上传失败。',
        };

        throw new RuntimeException($message);
    }

    private function detectMimeType(string $tmpName): string
    {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        return is_string($mimeType) ? trim($mimeType) : '';
    }

    private function resolveExtension(string $originalName, string $mimeType): string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return $extension;
        }

        foreach (self::EXT_MIME as $ext => $mimes) {
            if (in_array($mimeType, $mimes, true)) {
                return $ext;
            }
        }

        throw new RuntimeException('无法识别文件扩展名。');
    }

    private function assertAllowed(string $extension, string $mimeType): void
    {
        $allowedExtensions = $this->allowedExtensions();
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('不允许的文件扩展名：' . $extension . '。');
        }

        $allowedMimes = self::EXT_MIME[$extension] ?? [];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new RuntimeException('文件类型与扩展名不匹配。');
        }
    }

    /** @return array{0:string,1:string,2:string} */
    private function allocateDestination(string $extension): array
    {
        $relativeDir = 'content/uploads/' . gmdate('Y') . '/' . gmdate('m');
        $absoluteDir = FP_PATH . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('创建上传目录失败。');
        }

        if (!is_writable($absoluteDir)) {
            throw new RuntimeException('上传目录不可写。');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $relativePath = $relativeDir . '/' . $filename;
        $absolutePath = FP_PATH . '/' . $relativePath;

        return [$relativePath, $absolutePath, $filename];
    }

    private function maxSize(): int
    {
        $value = (int) ($this->settings?->get('upload_max_size', self::DEFAULT_MAX_SIZE) ?? self::DEFAULT_MAX_SIZE);

        return $value > 0 ? $value : self::DEFAULT_MAX_SIZE;
    }

    /** @return list<string> */
    private function allowedExtensions(): array
    {
        $raw = (string) ($this->settings?->get('upload_allowed_ext', self::DEFAULT_ALLOWED_EXT) ?? self::DEFAULT_ALLOWED_EXT);
        $items = array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $raw),
        ), static fn (string $item): bool => $item !== '' && array_key_exists($item, self::EXT_MIME)));

        return $items === []
            ? ['jpg', 'jpeg', 'png', 'gif', 'webp']
            : array_values(array_unique($items));
    }

    /** @return array{0:?int,1:?int} */
    private function imageSize(string $filePath): array
    {
        $info = @getimagesize($filePath);
        if ($info === false) {
            return [null, null];
        }

        return [(int) ($info[0] ?? 0), (int) ($info[1] ?? 0)];
    }

    private function isImageMime(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(trim(str_replace(["\0", "\r", "\n"], '', $name)));
        if ($name === '') {
            $name = 'upload.bin';
        }

        return mb_substr($name, 0, 255);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return round($bytes / (1024 * 1024), 2) . ' MB';
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function present(array $row): array
    {
        $path = '/' . ltrim((string) ($row['path'] ?? ''), '/');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'post_id' => isset($row['post_id']) && $row['post_id'] !== null ? (int) $row['post_id'] : null,
            'filename' => (string) ($row['filename'] ?? ''),
            'original_name' => (string) ($row['original_name'] ?? ''),
            'path' => ltrim($path, '/'),
            'url' => $this->asset?->url($path) ?? $path,
            'mime_type' => (string) ($row['mime_type'] ?? ''),
            'extension' => (string) ($row['extension'] ?? ''),
            'size' => (int) ($row['size'] ?? 0),
            'width' => isset($row['width']) && $row['width'] !== null ? (int) $row['width'] : null,
            'height' => isset($row['height']) && $row['height'] !== null ? (int) $row['height'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
