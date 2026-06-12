<?php

/**
 * Finch\Core\Thumbnail - 图片规范化与缩略图生成
 */

declare(strict_types=1);

namespace Finch\Core;

final class Thumbnail
{
    public function normalize(string $filePath, string $mimeType): bool
    {
        if (!$this->supports($mimeType)) {
            return false;
        }

        $source = $this->createImageResource($filePath, $mimeType);
        if ($source === null) {
            return false;
        }

        $tmp = $filePath . '.tmp';
        $ok = $this->saveImageResource($source, $tmp, $mimeType);
        imagedestroy($source);

        if (!$ok) {
            @unlink($tmp);

            return false;
        }

        if (!@rename($tmp, $filePath)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    public function create(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight): bool
    {
        $maxWidth = max(1, $maxWidth);
        $maxHeight = max(1, $maxHeight);

        $info = @getimagesize($sourcePath);
        if ($info === false || !isset($info['mime'])) {
            return false;
        }

        $mimeType = (string) $info['mime'];
        if (!$this->supports($mimeType)) {
            return false;
        }

        $source = $this->createImageResource($sourcePath, $mimeType);
        if ($source === null) {
            return false;
        }

        $sourceWidth = (int) ($info[0] ?? 0);
        $sourceHeight = (int) ($info[1] ?? 0);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return false;
        }

        [$targetWidth, $targetHeight] = $this->fitSize($sourceWidth, $sourceHeight, $maxWidth, $maxHeight);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            imagedestroy($source);

            return false;
        }

        if (in_array($mimeType, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        $tmp = $targetPath . '.tmp';
        $ok = $this->saveImageResource($target, $tmp, $mimeType);

        imagedestroy($source);
        imagedestroy($target);

        if (!$ok) {
            @unlink($tmp);

            return false;
        }

        if (!@rename($tmp, $targetPath)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    private function supports(string $mimeType): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        return match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            'image/png'  => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            'image/gif'  => function_exists('imagecreatefromgif') && function_exists('imagegif'),
            'image/webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            default      => false,
        };
    }

    private function createImageResource(string $filePath, string $mimeType): mixed
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png'  => @imagecreatefrompng($filePath),
            'image/gif'  => @imagecreatefromgif($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            default      => null,
        };
    }

    private function saveImageResource(mixed $image, string $path, string $mimeType): bool
    {
        return match ($mimeType) {
            'image/jpeg' => @imagejpeg($image, $path, 90),
            'image/png'  => @imagepng($image, $path, 6),
            'image/gif'  => @imagegif($image, $path),
            'image/webp' => @imagewebp($image, $path, 85),
            default      => false,
        };
    }

    /** @return array{0:int,1:int} */
    private function fitSize(int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): array
    {
        $ratio = min(
            $maxWidth / $sourceWidth,
            $maxHeight / $sourceHeight,
            1,
        );

        return [
            max(1, (int) floor($sourceWidth * $ratio)),
            max(1, (int) floor($sourceHeight * $ratio)),
        ];
    }
}
