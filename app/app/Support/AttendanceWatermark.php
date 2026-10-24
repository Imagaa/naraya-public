<?php

namespace App\Support;

use InvalidArgumentException;

/** Server-side stamp on every attendance photo. Only the stamped JPEG is stored. */
final class AttendanceWatermark
{
    // Ships with dompdf (already a dependency) — no font file of our own to maintain.
    private const FONT = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';

    /** @param list<string> $lines */
    public static function apply(string $imageBytes, array $lines): string
    {
        $src = @imagecreatefromstring($imageBytes);
        if ($src === false) {
            throw new InvalidArgumentException('Foto tidak bisa dibaca.');
        }

        $scale = min(1, 1280 / max(imagesx($src), imagesy($src)));
        $img = $scale < 1 ? imagescale($src, (int) round(imagesx($src) * $scale), (int) round(imagesy($src) * $scale)) : $src;
        $w = imagesx($img);
        $h = imagesy($img);

        $size = max(10, (int) round($w / 50));
        $lineHeight = (int) round($size * 1.7);
        $band = $lineHeight * count($lines) + $size;

        imagealphablending($img, true);
        imagefilledrectangle($img, 0, $h - $band, $w, $h, imagecolorallocatealpha($img, 0, 0, 0, 45));
        $white = imagecolorallocate($img, 255, 255, 255);
        foreach (array_values($lines) as $i => $line) {
            imagettftext($img, $size, 0, (int) round($size * 0.9), $h - $band + $lineHeight * ($i + 1), $white, base_path(self::FONT), $line);
        }

        ob_start();
        imagejpeg($img, null, 82);

        return (string) ob_get_clean();
    }
}
