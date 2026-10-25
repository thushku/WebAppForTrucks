<?php

namespace App\Platform\Website;

use App\Platform\Settings\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * The website's long texts — About us and the legal pages. Each ships as a
 * plain-text default in resources/site/ and the super-admin may replace it
 * in the console (decision D20: set there, not in code).
 *
 * The format is deliberately small: a line starting "## " is a heading, lines
 * starting "- " make a list, a blank line starts a new paragraph, ":brand"
 * is the product's name. Everything is escaped, so no HTML gets through.
 */
class SiteText
{
    public const PAGES = ['about' => 'About us', 'terms' => 'Terms of service', 'privacy' => 'Privacy policy', 'refund' => 'Refund policy'];

    public function __construct(private PlatformSettings $settings) {}

    public function raw(string $page): string
    {
        $custom = $this->settings->get('site_' . $page);

        return filled($custom) ? (string) $custom : $this->shipped($page);
    }

    /** The text that comes with the product. */
    public function shipped(string $page): string
    {
        return (string) file_get_contents(resource_path("site/{$page}.txt"));
    }

    /** Whether a text is the shipped one (ignoring line endings and spaces at the ends). */
    public function isShipped(string $page, string $text): bool
    {
        $norm = fn (string $t) => trim(str_replace("\r\n", "\n", $t));

        return $norm($text) === $norm($this->shipped($page));
    }

    public function isCustom(string $page): bool
    {
        return filled($this->settings->get('site_' . $page));
    }

    /** When the super-admin last changed the page (null: the shipped text). */
    public function updatedOn(string $page): ?string
    {
        $at = DB::table('platform_settings')->where('key', 'site_' . $page)->value('updated_at');

        return $at && $this->isCustom($page) ? (string) $at : null;
    }

    public function html(string $page): HtmlString
    {
        $brand = e($this->settings->brandName());
        $blocks = preg_split("/\n\s*\n/", str_replace("\r\n", "\n", trim($this->raw($page))));
        $out = [];
        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('rtrim', explode("\n", $block)), fn ($l) => $l !== ''));
            $buffer = [];
            $list = [];
            $flush = function () use (&$buffer, &$list, &$out) {
                if ($buffer) {
                    $out[] = '<p>' . implode(' ', $buffer) . '</p>';
                    $buffer = [];
                }
                if ($list) {
                    $out[] = '<ul>' . implode('', array_map(fn ($i) => "<li>{$i}</li>", $list)) . '</ul>';
                    $list = [];
                }
            };
            foreach ($lines as $line) {
                if (str_starts_with($line, '## ')) {
                    $flush();
                    $out[] = '<h2>' . e(substr($line, 3)) . '</h2>';
                } elseif (str_starts_with(ltrim($line), '- ')) {
                    if ($buffer) {
                        $out[] = '<p>' . implode(' ', $buffer) . '</p>';
                        $buffer = [];
                    }
                    $list[] = e(substr(ltrim($line), 2));
                } else {
                    if ($list) {
                        $out[] = '<ul>' . implode('', array_map(fn ($i) => "<li>{$i}</li>", $list)) . '</ul>';
                        $list = [];
                    }
                    $buffer[] = e($line);
                }
            }
            $flush();
        }

        return new HtmlString(str_replace(':brand', $brand, implode("\n", $out)));
    }
}
