<?php

namespace App\Modules\Invoicing\Design;

/**
 * The shape of a document design (ported from Clinic Ledger, blueprint 10
 * §9.1): theme, colours, type, page, what prints, the titles, renamed labels
 * and the copy set — per document family.
 *
 * A design comes from the editor or an imported file. Whatever the source it
 * goes through normalize(): every value is checked against the lists and
 * ranges here, anything unknown is dropped and reported in plain words,
 * anything missing takes the theme's default. Users never type HTML: the
 * themes are our Blade layouts, the design only chooses among them. The
 * renderer turns the design into CSS custom properties, so the screen, the
 * print dialog and the server's PDF read the same numbers.
 */
final class DesignSchema
{
    public const VERSION = 1;

    public const FAMILIES = ['invoice' => 'Bills and notes', 'lr' => 'LR / consignment note', 'receipt' => 'Money receipt'];

    /** family => theme => [name, accent, paper] */
    public const THEMES = [
        'invoice' => [
            'gst_classic' => ['GST classic (boxed)', '#111827', 'A4'],
            'modern'      => ['Modern band', '#1F6FEB', 'A4'],
            'minimal'     => ['Minimal', '#0F766E', 'A4'],
        ],
        'lr' => [
            'classic_lr' => ['Classic LR (boxed)', '#111827', 'A4'],
            'compact_a5' => ['Compact A5', '#111827', 'A5'],
        ],
        'receipt' => [
            'receipt_a5' => ['A5 receipt', '#111827', 'A5'],
            'thermal_80' => ['Thermal 80 mm', '#000000', 'thermal_80'],
        ],
    ];

    /** font => [name, CSS stack]. Every stack names the Indian-script and Arabic faces (blueprint 10 §10). */
    public const FONTS = [
        'noto'   => ['Noto Sans', "'Noto Sans', 'Noto Sans Devanagari', 'Noto Sans Malayalam', 'Noto Sans Tamil', 'Noto Sans Arabic', Arial, sans-serif"],
        'system' => ['System sans', "'Segoe UI', Roboto, Arial, 'Noto Sans Devanagari', 'Noto Sans Arabic', sans-serif"],
        'serif'  => ['Serif', "Georgia, 'Noto Serif', 'Noto Serif Devanagari', 'Noto Naskh Arabic', serif"],
        'mono'   => ['Typewriter', "'Courier New', 'Noto Sans Mono', monospace"],
    ];

    /** paper => [label, CSS @page size, sheet width mm]. */
    public const PAPERS = [
        'A4'           => ['A4 portrait', 'A4', 210],
        'A4_landscape' => ['A4 landscape', 'A4 landscape', 297],
        'A5'           => ['A5 portrait', 'A5', 148],
        'Letter'       => ['Letter', 'Letter', 216],
        'thermal_80'   => ['Thermal 80 mm', '80mm 297mm', 80],
    ];

    /** family => field => printed by default. */
    public const FIELDS = [
        'invoice' => [
            'logo' => true, 'address' => true, 'contact' => true, 'tax_registration' => true, 'pan' => true, 'sac' => true,
            'tax_summary' => true, 'words' => true, 'bank' => true, 'qr' => true, 'signature' => true, 'notes' => true, 'terms' => true,
            'annexure' => true, 'annexure_vehicle' => true, 'annexure_driver' => false, 'tds_note' => true, 'computer_generated' => true,
        ],
        'lr' => [
            'logo' => true, 'address' => true, 'tax_registration' => true, 'trip' => true, 'driver' => true, 'ewaybill' => true,
            'consignor_invoice' => true, 'declared_value' => true, 'gst_payable_by' => true, 'risk_banner' => true, 'pod_box' => true, 'carriage_note' => true,
        ],
        'receipt' => ['logo' => true, 'address' => true, 'tax_registration' => true, 'applied' => true, 'words' => true, 'signature' => true],
    ];

    /** Readable names of the switches (the editor and the dropped-value messages). */
    public const FIELD_LABELS = [
        'logo' => 'Logo', 'address' => 'Our address', 'contact' => 'Phone, email, website', 'tax_registration' => 'GSTIN / TRN', 'pan' => 'PAN',
        'sac' => 'SAC / item code column', 'tax_summary' => 'Tax summary table', 'words' => 'Amount in words', 'bank' => 'Bank details',
        'qr' => 'UPI QR for the amount due', 'signature' => 'Signature block', 'notes' => 'Note', 'terms' => 'Terms', 'annexure' => 'LR annexure (consolidated bills)',
        'annexure_vehicle' => 'Vehicle column in the annexure', 'annexure_driver' => 'Driver column in the annexure', 'tds_note' => 'TDS line', 'computer_generated' => '“Computer-generated” line',
        'trip' => 'Trip number', 'driver' => 'Driver name and mobile', 'ewaybill' => 'E-way bills', 'consignor_invoice' => 'Consignor invoice',
        'declared_value' => 'Declared value', 'gst_payable_by' => 'GST payable by', 'risk_banner' => 'Owner’s / carrier’s risk', 'pod_box' => 'Proof of delivery box',
        'carriage_note' => 'Carriage by Road Act line', 'applied' => 'Bills settled',
    ];

    /** family => title key => default wording (empty in a design = this wording, in the document's language). */
    public const TITLES = [
        'invoice' => ['tax_invoice' => 'Tax invoice', 'bill_of_supply' => 'Bill of supply', 'invoice' => 'Invoice', 'supplementary' => 'Supplementary tax invoice',
            'credit_note' => 'Credit note', 'debit_note' => 'Debit note'],
        'lr'      => ['lr' => 'Consignment note'],
        'receipt' => ['receipt' => 'Money receipt'],
    ];

    /** family => label key => default wording. Renaming a label is also how other languages are printed. */
    public const LABELS = [
        'invoice' => ['bill_to' => 'Bill to', 'pay_to' => 'Pay to', 'signatory' => 'Authorised signatory', 'total' => 'Total', 'balance' => 'Balance due',
            'place_of_supply' => 'Place of supply', 'reverse_charge' => 'Reverse charge', 'description' => 'Description', 'in_words' => 'In words'],
        'lr'      => ['consignor' => 'Consignor', 'consignee' => 'Consignee', 'goods' => 'Said to contain', 'pod' => 'Proof of delivery', 'booking' => 'Booking clerk'],
        'receipt' => ['received_from' => 'Received with thanks from', 'signatory' => 'Authorised signatory'],
    ];

    /** family => the copies printed by default: [label, tint, hide freight]. */
    public const COPIES = [
        'invoice' => [['Original for recipient', '#ffffff', false], ['Duplicate for supplier', '#ffffff', false]],
        'lr'      => [['Consignor copy', '#ffffff', false], ['Consignee copy', '#f5faff', false], ['Driver / lorry copy', '#fffbeb', true], ['Office copy', '#f0fdf4', false]],
        'receipt' => [['', '#ffffff', false]],
    ];

    public const MAX_COPIES = 6;

    /** Extra lines printed under our details (an MSME / Udyam number, a transporter ID, a licence): label and value. */
    public const MAX_CUSTOM = 6;

    /** key => [min, max, default] in points or millimetres. */
    public const RANGES = ['base_pt' => [8, 12, 10], 'margin_mm' => [5, 25, 12], 'logo_mm' => [8, 30, 18]];

    public const DENSITIES = ['comfortable', 'compact'];

    public const TEXT_MAX = 60;

    public const SWATCHES = ['#111827' => 'Ink', '#1F6FEB' => 'Blue', '#0F766E' => 'Teal', '#7C3AED' => 'Violet', '#C2410C' => 'Terracotta', '#B91C1C' => 'Red'];

    // ---- Defaults ------------------------------------------------------------

    public static function defaults(string $family, ?string $theme = null): array
    {
        $family = isset(self::THEMES[$family]) ? $family : 'invoice';
        $theme  = isset(self::THEMES[$family][$theme]) ? $theme : array_key_first(self::THEMES[$family]);
        [, $accent, $paper] = self::THEMES[$family][$theme];

        return [
            'schema'    => self::VERSION,
            'family'    => $family,
            'theme'     => $theme,
            'accent'    => $accent,
            'font'      => $theme === 'thermal_80' ? 'mono' : 'noto',
            'base_pt'   => $paper === 'A5' || $paper === 'thermal_80' ? 9 : self::RANGES['base_pt'][2],
            'margin_mm' => $paper === 'thermal_80' ? 5 : self::RANGES['margin_mm'][2],
            'logo_mm'   => self::RANGES['logo_mm'][2],
            'density'   => $paper === 'A5' ? 'compact' : 'comfortable',
            'paper'     => $paper,
            'fields'    => self::FIELDS[$family],
            'titles'    => array_fill_keys(array_keys(self::TITLES[$family]), null),
            'labels'    => array_fill_keys(array_keys(self::LABELS[$family]), null),
            'copies'    => array_map(fn ($c) => ['label' => $c[0], 'tint' => $c[1], 'hide_freight' => $c[2]], self::COPIES[$family]),
            'custom'    => [],
            'footer'    => null,
        ];
    }

    // ---- Checking --------------------------------------------------------------

    /**
     * A design made safe: known values only, the theme's defaults for the
     * rest. $dropped lists, in plain words, what was not kept.
     *
     * @param array<int, string>|null $dropped
     */
    public static function normalize(string $family, array $input, ?array &$dropped = null): array
    {
        $dropped = [];
        $family  = isset(self::THEMES[$family]) ? $family : 'invoice';
        $theme   = is_string($input['theme'] ?? null) && isset(self::THEMES[$family][$input['theme']]) ? $input['theme'] : null;
        if (isset($input['theme']) && ! $theme) {
            $dropped[] = __('Theme: not one of the choices, the default is used');
        }
        if (isset($input['family']) && $input['family'] !== $family) {
            $dropped[] = __('This design is for :other, not :this; only what fits was kept', ['other' => __(self::FAMILIES[$input['family']] ?? (string) $input['family']), 'this' => __(self::FAMILIES[$family])]);
        }
        if (is_numeric($input['schema'] ?? null) && (int) $input['schema'] > self::VERSION) {
            $dropped[] = __('This file comes from a newer version; what this version does not know was left out');
        }

        $out = self::defaults($family, $theme);

        $out['accent']    = self::colour($input['accent'] ?? null, $out['accent'], __('Accent colour'), $dropped);
        $out['font']      = self::choice($input['font'] ?? null, array_keys(self::FONTS), $out['font'], __('Font'), $dropped);
        $out['paper']     = self::choice($input['paper'] ?? null, array_keys(self::PAPERS), $out['paper'], __('Paper'), $dropped);
        $out['density']   = self::choice($input['density'] ?? null, self::DENSITIES, $out['density'], __('Table rows'), $dropped);
        $out['base_pt']   = self::number($input['base_pt'] ?? null, 'base_pt', $out['base_pt'], __('Text size'), $dropped);
        $out['margin_mm'] = self::number($input['margin_mm'] ?? null, 'margin_mm', $out['margin_mm'], __('Margins'), $dropped);
        $out['logo_mm']   = self::number($input['logo_mm'] ?? null, 'logo_mm', $out['logo_mm'], __('Logo size'), $dropped);

        foreach (self::FIELDS[$family] as $field => $default) {
            if (! array_key_exists($field, (array) ($input['fields'] ?? []))) {
                continue;
            }
            $value = $input['fields'][$field];
            $on = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
            if ($on === null) {
                $dropped[] = __('What prints: “:name” is neither on nor off, the default is used', ['name' => __(self::FIELD_LABELS[$field] ?? $field)]);
            }
            $out['fields'][$field] = $on ?? $default;
        }
        foreach (array_keys((array) ($input['fields'] ?? [])) as $field) {
            if (! array_key_exists($field, self::FIELDS[$family])) {
                $dropped[] = __('What prints: “:name” is not one of the switches, left out', ['name' => self::shown($field)]);
            }
        }

        foreach (['titles' => self::TITLES[$family], 'labels' => self::LABELS[$family]] as $part => $keys) {
            foreach ((array) ($input[$part] ?? []) as $key => $value) {
                if (! array_key_exists($key, $keys)) {
                    $dropped[] = __('“:name” is not one of the :part, left out', ['name' => self::shown((string) $key), 'part' => $part === 'titles' ? __('titles') : __('labels')]);
                    continue;
                }
                $out[$part][$key] = self::text($value, $part === 'titles' ? __('Title “:k”', ['k' => $keys[$key]]) : __('Label “:k”', ['k' => $keys[$key]]), $dropped);
            }
        }
        $out['footer'] = array_key_exists('footer', $input) ? self::text($input['footer'], __('Footer text'), $dropped, 200) : null;

        // The copy set: 1 to 6 copies, each with a label, a light tint and (LR) whether freight hides.
        if (isset($input['copies'])) {
            $copies = [];
            foreach (array_slice(array_values((array) $input['copies']), 0, self::MAX_COPIES) as $copy) {
                if (! is_array($copy)) {
                    continue;
                }
                $copies[] = [
                    'label'        => self::text($copy['label'] ?? '', __('Copy label'), $dropped) ?? '',
                    'tint'         => self::colour($copy['tint'] ?? null, '#ffffff', __('Copy tint'), $dropped),
                    'hide_freight' => $family === 'lr' && filter_var($copy['hide_freight'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
            if (count((array) $input['copies']) > self::MAX_COPIES) {
                $dropped[] = __('At most :n copies; the rest were left out', ['n' => self::MAX_COPIES]);
            }
            $out['copies'] = $copies ?: $out['copies'];
        }

        // Custom lines: text only, an empty pair is skipped.
        if (isset($input['custom'])) {
            $custom = [];
            foreach (array_slice(array_values((array) $input['custom']), 0, self::MAX_CUSTOM) as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $label = self::text($line['label'] ?? '', __('Extra line label'), $dropped, 40);
                $value = self::text($line['value'] ?? '', __('Extra line value'), $dropped, 80);
                if ($label !== null || $value !== null) {
                    $custom[] = ['label' => $label ?? '', 'value' => $value ?? ''];
                }
            }
            if (count((array) $input['custom']) > self::MAX_CUSTOM) {
                $dropped[] = __('At most :n extra lines; the rest were left out', ['n' => self::MAX_CUSTOM]);
            }
            $out['custom'] = $custom;
        }

        $known = ['schema', 'family', 'theme', 'accent', 'font', 'paper', 'density', 'base_pt', 'margin_mm', 'logo_mm', 'fields', 'titles', 'labels', 'copies', 'custom', 'footer'];
        foreach (array_keys($input) as $key) {
            if (! in_array($key, $known, true)) {
                $dropped[] = __('“:name” is not part of a design, left out', ['name' => self::shown((string) $key)]);
            }
        }

        return $out;
    }

    /** Contrast ratio of a hex colour against white (WCAG 2), e.g. 4.83 for #6B7280. */
    public static function contrastOnWhite(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channel = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $channel(substr($hex, 0, 2)) + 0.7152 * $channel(substr($hex, 2, 2)) + 0.0722 * $channel(substr($hex, 4, 2));

        return round(1.05 / ($l + 0.05), 2);
    }

    // ---- Rendering -------------------------------------------------------------------

    /**
     * The custom properties a document's stylesheet reads. They are printed
     * raw inside <style>, so each value is checked again here: a colour that
     * is not #hex, or a font not in the list, falls back.
     */
    public static function cssVariables(array $design): string
    {
        $accent = is_string($design['accent'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', $design['accent']) ? $design['accent'] : '#111827';

        return sprintf('--accent: %s; --font: %s; --base: %spt; --margin: %smm; --logo: %smm; --sheet: %smm; --pad: %s;',
            $accent, self::FONTS[$design['font'] ?? ''][1] ?? self::FONTS['noto'][1],
            rtrim(rtrim(number_format((float) $design['base_pt'], 1, '.', ''), '0'), '.'), (int) $design['margin_mm'], (int) $design['logo_mm'],
            self::PAPERS[$design['paper']][2] ?? 210, $design['density'] === 'compact' ? '2px 5px' : '4px 6px');
    }

    /** The @page size of the paper. */
    public static function pageSize(array $design): string
    {
        return self::PAPERS[$design['paper']][1] ?? 'A4';
    }

    /** The printed title of a document kind, as the design words it. */
    public static function title(array $design, string $key): string
    {
        return $design['titles'][$key] ?? __(self::TITLES[$design['family']][$key] ?? $key);
    }

    public static function label(array $design, string $key): string
    {
        return $design['labels'][$key] ?? __(self::LABELS[$design['family']][$key] ?? $key);
    }

    // ---- Helpers ----------------------------------------------------------------------

    private static function colour(mixed $value, string $default, string $what, array &$dropped): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return strtoupper($value);
        }
        $dropped[] = __(':what: “:v” is not a colour like #1F6FEB, the default is used', ['what' => $what, 'v' => self::shown($value)]);

        return $default;
    }

    private static function choice(mixed $value, array $allowed, string $default, string $what, array &$dropped): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_string($value) && in_array($value, $allowed, true)) {
            return $value;
        }
        $dropped[] = __(':what: not one of the choices, the default is used', ['what' => $what]);

        return $default;
    }

    private static function number(mixed $value, string $range, float|int $default, string $what, array &$dropped): float|int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        [$min, $max] = self::RANGES[$range];
        if (! is_numeric($value)) {
            $dropped[] = __(':what: not a number, the default is used', ['what' => $what]);

            return $default;
        }
        $n = (float) $value;
        if ($n < $min || $n > $max) {
            $dropped[] = __(':what: kept between :min and :max', ['what' => $what, 'min' => $min, 'max' => $max]);
        }
        $n = max($min, min($max, $n));

        return $range === 'base_pt' ? round($n * 2) / 2 : (int) round($n);
    }

    /**
     * Plain text only: no markup, no control characters, nothing that turns
     * text around (a right-to-left override); the joiners Indian scripts and
     * Arabic need stay.
     */
    private static function text(mixed $value, string $what, array &$dropped, int $max = self::TEXT_MAX): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            $dropped[] = __(':what: not text, left out', ['what' => $what]);

            return null;
        }
        $clean = trim(preg_replace('/[\p{Cc}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', ' ', strip_tags($value)) ?? '');
        if ($clean !== trim($value)) {
            $dropped[] = __(':what: markup and invisible characters taken out', ['what' => $what]);
        }
        if (mb_strlen($clean) > $max) {
            $dropped[] = __(':what: longer than :max characters, shortened', ['what' => $what, 'max' => $max]);
        }

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }

    private static function shown(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : gettype($value);

        return mb_substr(strip_tags($text), 0, 40);
    }
}
