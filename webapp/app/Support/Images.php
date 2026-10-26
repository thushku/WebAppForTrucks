<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Pictures from phones and scanners, made small (blueprint 01 §5): turned
 * upright, re-saved as JPEG at most N px on the long side and stepped down in
 * quality until under the target size. Re-saving drops the camera's hidden
 * data (location, phone model). Also the "look-alike" fingerprint used to
 * catch the same photo sent twice.
 */
final class Images
{
    /**
     * @return string JPEG bytes
     */
    public static function shrink(string $path, int $longSide = 1600, int $targetBytes = 512000): string
    {
        $image = self::load($path);

        // One white canvas at the final size: shrinks big photos and flattens
        // PNG transparency (JPEG has none).
        $w      = imagesx($image);
        $h      = imagesy($image);
        $scale  = min(1, $longSide / max($w, $h));
        $canvas = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $w, $h);
        imagedestroy($image);

        return self::jpeg($canvas, $targetBytes);
    }

    /**
     * A profile photo (M28): the middle square of the picture, upright, at
     * $size × $size, as JPEG bytes.
     */
    public static function square(string $path, int $size = 512): string
    {
        $image = self::load($path);
        $w     = imagesx($image);
        $h     = imagesy($image);
        $side  = min($w, $h);
        $out   = min($size, $side);
        $canvas = imagecreatetruecolor($out, $out);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $out, $out, $side, $side);
        imagedestroy($image);

        return self::jpeg($canvas, 150000);
    }

    /** The picture, read and turned upright; refused when it is not a JPG, PNG or WebP. */
    private static function load(string $path): \GdImage
    {
        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['photo' => __('That file is not a photo (JPG, PNG or WebP).')]);
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        };
        if (! $image) {
            throw ValidationException::withMessages(['photo' => __('That picture could not be read.')]);
        }

        // Photos from phones are often stored sideways with an orientation flag.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $image = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
        }

        return $image;
    }

    /** JPEG bytes, the quality stepped down until under the target size. */
    private static function jpeg(\GdImage $canvas, int $targetBytes): string
    {
        $bytes = '';
        foreach ([82, 72, 62, 52, 42] as $quality) {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $bytes = (string) ob_get_clean();
            if (strlen($bytes) <= $targetBytes) {
                break;
            }
        }
        imagedestroy($canvas);

        return $bytes;
    }

    /**
     * 64-bit average hash as 16 hex characters: the picture shrunk to 8×8
     * grey, each pixel above or below the mean. Two photos of the same meter
     * slip, re-saved or slightly cropped, come out within a few bits.
     */
    public static function averageHash(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return str_repeat('0', 16);
        }
        $small = imagecreatetruecolor(8, 8);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 8, 8, imagesx($image), imagesy($image));
        imagedestroy($image);

        $grey = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $grey[] = (($rgb >> 16) & 0xFF) * 0.299 + (($rgb >> 8) & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114;
            }
        }
        imagedestroy($small);

        $mean = array_sum($grey) / 64;
        $bits = '';
        foreach ($grey as $g) {
            $bits .= $g >= $mean ? '1' : '0';
        }

        return str_pad(implode('', array_map(fn ($chunk) => dechex(bindec($chunk)), str_split($bits, 4))), 16, '0', STR_PAD_LEFT);
    }

    /** How many of the 64 bits differ between two average hashes. */
    public static function distance(string $a, string $b): int
    {
        $d = 0;
        for ($i = 0; $i < 16; $i++) {
            $d += substr_count(str_pad(decbin(hexdec($a[$i]) ^ hexdec($b[$i])), 4, '0', STR_PAD_LEFT), '1');
        }

        return $d;
    }
}
