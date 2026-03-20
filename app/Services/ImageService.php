<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageService
{
    public static function resizePublic(string $relativePath, int $maxWidth = 1920, int $maxHeight = 1080, int $quality = 85): void
    {
        $filePath = Storage::disk('public')->path($relativePath);
        self::resize($filePath, $maxWidth, $maxHeight, $quality);
    }

    public static function resize(string $filePath, int $maxWidth = 1920, int $maxHeight = 1080, int $quality = 85): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $info = @getimagesize($filePath);
        if (!$info) {
            return;
        }

        [$width, $height, $type] = $info;

        if ($width <= $maxWidth && $height <= $maxHeight) {
            if ($type === IMAGETYPE_JPEG) {
                $image = @imagecreatefromjpeg($filePath);
                if ($image) {
                    imagejpeg($image, $filePath, $quality);
                    imagedestroy($image);
                }
            }
            return;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($filePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($filePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($filePath),
            IMAGETYPE_GIF  => @imagecreatefromgif($filePath),
            default        => null,
        };

        if (! $source) {
            return;
        }

        $dest = imagecreatetruecolor($newWidth, $newHeight);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
            imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($dest, $filePath, $quality),
            IMAGETYPE_PNG  => imagepng($dest, $filePath, 9),
            IMAGETYPE_WEBP => imagewebp($dest, $filePath, $quality),
            IMAGETYPE_GIF  => imagegif($dest, $filePath),
            default        => null,
        };

        imagedestroy($source);
        imagedestroy($dest);
    }
}
