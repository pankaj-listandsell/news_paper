<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Downscales and re-encodes generated images so pages stay fast.
 * A 1024x1024 PNG from the image API is ~1.4 MB; as a resized WebP
 * it lands around 60-120 KB with no visible loss at article size.
 */
class ImageOptimizer
{
    /** Longest edge we ever need for an article image. */
    public const MAX_EDGE = 1200;

    public const QUALITY = 82;

    /**
     * @param  string  $bytes  raw source image bytes
     * @return array{bytes:string, extension:string}  optimised bytes (falls back to the original)
     */
    public static function optimize(string $bytes): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['bytes' => $bytes, 'extension' => 'png'];
        }

        try {
            $src = @imagecreatefromstring($bytes);

            if ($src === false) {
                return ['bytes' => $bytes, 'extension' => 'png'];
            }

            $src = self::resize($src);

            $webp = function_exists('imagewebp');

            ob_start();
            $webp
                ? imagewebp($src, null, self::QUALITY)
                : imagejpeg($src, null, self::QUALITY);
            $out = (string) ob_get_clean();

            imagedestroy($src);

            if ($out === '') {
                return ['bytes' => $bytes, 'extension' => 'png'];
            }

            return ['bytes' => $out, 'extension' => $webp ? 'webp' : 'jpg'];
        } catch (\Throwable $e) {
            Log::info('Image optimise failed, keeping original: ' . $e->getMessage());

            return ['bytes' => $bytes, 'extension' => 'png'];
        }
    }

    /**
     * Scale down so the longest edge is at most MAX_EDGE. Smaller images
     * are returned untouched (never upscale).
     *
     * @param  \GdImage  $src
     * @return \GdImage
     */
    private static function resize($src)
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $longest = max($w, $h);

        if ($longest <= self::MAX_EDGE) {
            return $src;
        }

        $scale = self::MAX_EDGE / $longest;
        $newW  = (int) round($w * $scale);
        $newH  = (int) round($h * $scale);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);

        return $dst;
    }
}
