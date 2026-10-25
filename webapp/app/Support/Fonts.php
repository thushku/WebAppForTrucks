<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The typefaces, served from our own server (decision 66): no request to
 * Google on any page, documents that print the same offline, and a first
 * page that preloads exactly the two files it needs.
 *
 * The files and the manifest in public/fonts are produced by
 * tools/fetch-fonts.py; this class only reads the manifest.
 */
class Fonts
{
    private static ?array $manifest = null;

    /** @return array{families: array<string, array<int, array{file: string, subset: string, weight: string, style: string}>>} */
    public static function manifest(): array
    {
        if (self::$manifest === null) {
            $path = public_path('fonts/manifest.json');
            self::$manifest = is_file($path) ? (json_decode(File::get($path), true) ?: ['families' => []]) : ['families' => []];
        }

        return self::$manifest;
    }

    /** Preload tags for the first paint: the body and display faces, Latin only. */
    public static function preloadTags(): string
    {
        $tags = '';
        foreach (['Plus Jakarta Sans', 'Bricolage Grotesque'] as $family) {
            foreach (self::manifest()['families'][$family] ?? [] as $face) {
                if ($face['subset'] === 'latin' && $face['style'] === 'normal') {
                    $tags .= '<link rel="preload" href="' . e(asset('fonts/' . $face['file'])) . '" as="font" type="font/woff2" crossorigin>' . "\n";
                    break;
                }
            }
        }

        return $tags;
    }

    /**
     * The @font-face rules a document needs, with the files embedded as
     * data URIs, so a PDF printed by the headless browser has its fonts
     * without asking the web server (which may be busy serving it).
     *
     * Every subset is carried, whatever the document's language (phase 5):
     * a clinic's name in Devanagari on an English invoice needs its face as
     * much as a Hindi invoice does. Each face keeps its unicode-range, so the
     * browser reads — and the PDF embeds — only the faces the text uses.
     *
     * @param  string[]  $families
     */
    public static function inlineCss(array $families): string
    {
        $css = '';
        foreach ($families as $family) {
            foreach (self::manifest()['families'][$family] ?? [] as $face) {
                $path = public_path('fonts/' . $face['file']);
                if (! is_file($path)) {
                    continue;
                }
                $css .= self::fontFace($family, $face, 'url(data:font/woff2;base64,' . base64_encode(File::get($path)) . ')');
            }
        }

        return $css;
    }

    /** The same rules pointing at the served files (the stylesheet for pages and the public link). */
    public static function linkedCss(array $families): string
    {
        $css = '';
        foreach ($families as $family) {
            foreach (self::manifest()['families'][$family] ?? [] as $face) {
                $css .= self::fontFace($family, $face, 'url(' . asset('fonts/' . $face['file']) . ')');
            }
        }

        return $css;
    }

    private static function fontFace(string $family, array $face, string $src): string
    {
        return sprintf(
            "@font-face{font-family:'%s';font-style:%s;font-weight:%s;font-display:swap;src:%s format('woff2');%s}\n",
            $family, $face['style'], $face['weight'], $src,
            ! empty($face['unicode_range']) ? 'unicode-range:' . $face['unicode_range'] . ';' : ''
        );
    }
}
