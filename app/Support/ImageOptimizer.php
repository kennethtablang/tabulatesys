<?php

namespace App\Support;

/**
 * Shrinks oversized uploads (logos, banners) so pages, Excel and PDF exports stay light.
 * Keeps the format and PNG transparency. Needs PHP's GD extension; without it files are left as they are.
 */
class ImageOptimizer
{
    /** Resize so neither side exceeds $maxSide pixels. Returns true if the file was changed. */
    public static function shrink(string $path, int $maxSide = 800): bool
    {
        if (! extension_loaded('gd') || ! is_file($path)) {
            return false;
        }
        $info = @getimagesize($path);
        if (! $info || max($info[0], $info[1]) <= $maxSide) {
            return false;
        }

        $src = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (! $src) {
            return false;
        }

        $ratio = $maxSide / max($info[0], $info[1]);
        $w = max(1, (int) round($info[0] * $ratio));
        $h = max(1, (int) round($info[1] * $ratio));
        $dst = imagecreatetruecolor($w, $h);
        if (in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);

        $ok = match ($info[2]) {
            IMAGETYPE_PNG => imagepng($dst, $path, 9),
            IMAGETYPE_JPEG => imagejpeg($dst, $path, 88),
            IMAGETYPE_GIF => imagegif($dst, $path),
            IMAGETYPE_WEBP => imagewebp($dst, $path, 88),
            default => false,
        };
        imagedestroy($src);
        imagedestroy($dst);

        return (bool) $ok;
    }
}
