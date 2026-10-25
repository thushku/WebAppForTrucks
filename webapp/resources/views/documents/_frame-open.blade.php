{{-- One self-contained page: the preview, the browser print, the public link and the PDF are this same HTML.
     The design (DesignSchema) arrives as CSS custom properties and a theme class; sizes are in rem so the
     text size of the design scales the whole page. --}}
@php
  $design ??= $doc['design'] ?? \App\Modules\Invoicing\Design\DesignSchema::defaults('invoice');
  $fields = $design['fields'];
  $sheetHeight = ['A4' => '297mm', 'A4_landscape' => '210mm', 'A5' => '210mm', 'Letter' => '279mm'][$design['paper']] ?? 'auto';
  $rtl = in_array(app()->getLocale(), ['ar', 'ur'], true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($rtl) dir="rtl" @endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $pageTitle }}</title>
<style>
  {{-- Raw on purpose: <style> does not decode entities, and every value here comes from the schema's lists or is a checked number or #hex. --}}
  @page { size: {!! \App\Modules\Invoicing\Design\DesignSchema::pageSize($design) !!}; margin: {{ (int) $design['margin_mm'] }}mm; }
  :root { {!! \App\Modules\Invoicing\Design\DesignSchema::cssVariables($design) !!} --line: #374151; --soft: #9ca3af; --head: #f3f4f6; }
  * { box-sizing: border-box; }
  html { font-size: var(--base); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { margin: 0; color: #111827; font-family: var(--font); font-size: 1rem; line-height: 1.4; background: #eef1f5; }
  .sheet { position: relative; background: #fff; width: var(--sheet); min-height: {{ $sheetHeight }}; margin: 12px auto; padding: var(--margin); box-shadow: 0 2px 12px rgba(15, 26, 43, .12); }
  @media print { body { background: #fff; } .sheet { margin: 0; width: auto; min-height: 0; padding: 0; box-shadow: none; } .sheet + .sheet { break-before: page; } .no-print { display: none !important; } }
  h1 { margin: 0; font-size: 1.5rem; letter-spacing: .04em; text-transform: uppercase; color: var(--accent); }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: var(--pad); vertical-align: top; text-align: start; }
  .box { border: 1px solid var(--line); }
  .sep-r { border-inline-end: 1px solid var(--line); } .sep-l { border-inline-start: 1px solid var(--line); } .sep-t { border-top: 1px solid var(--line); }
  .grid th, .grid td { border: 1px solid var(--soft); }
  .grid thead th { background: var(--head); font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
  .r { text-align: end; } .c { text-align: center; }
  .nowrap, .num { white-space: nowrap; } .num { font-variant-numeric: tabular-nums; }
  .muted { color: #6b7280; } .small { font-size: .85rem; } .strong { font-weight: 700; } .big { font-size: 1.3rem; } .bigger { font-size: 1.4rem; } .mid { font-size: 1.2rem; }
  .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
  .copy { display: inline-block; border: 1px solid currentColor; padding: 1px 8px; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
  .logo { max-height: var(--logo); max-width: calc(var(--logo) * 2.2); object-fit: contain; }
  .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; font-size: 80pt; font-weight: 800; color: rgba(220, 38, 38, .12); transform: rotate(-24deg); letter-spacing: .1em; }
  .split { display: flex; gap: 10px; } .split > * { flex: 1; }
  .qr svg { width: 26mm; height: 26mm; }
  .footer { margin-top: 6px; text-align: center; }

  /* Modern band: the header on the accent colour, light rules. */
  .t-modern { --line: #e5e7eb; --soft: #e5e7eb; }
  .t-modern .head { background: var(--accent); color: #fff; border-color: var(--accent); }
  .t-modern .head h1, .t-modern .head .small, .t-modern .head .label, .t-modern .head .muted { color: #fff; }
  .t-modern .head .sep-r { border-color: rgba(255, 255, 255, .35); }
  .t-modern .grid thead th { background: var(--accent); color: #fff; border-color: var(--accent); }
  .t-modern .total-row td { background: color-mix(in srgb, var(--accent) 10%, #fff); }

  /* Minimal: no boxes, hairlines only, the accent on the title and the column heads. */
  .t-minimal { --line: transparent; --soft: #e5e7eb; }
  .t-minimal .box { border: 0; border-bottom: 1px solid #e5e7eb; }
  .t-minimal .grid th, .t-minimal .grid td { border: 0; border-bottom: 1px solid #e5e7eb; }
  .t-minimal .grid thead th { background: none; color: var(--accent); border-bottom: 2px solid var(--accent); }
  .t-minimal h1 { font-weight: 400; letter-spacing: .12em; }
  .t-minimal .total-row td { border-top: 2px solid var(--accent); }

  /* Thermal 80 mm: one narrow column, no shadows or tints. */
  .t-thermal_80 .sheet { padding: 3mm; }
  .t-thermal_80 h1 { font-size: 1.1rem; }
  .t-thermal_80 .rule { border-top: 1px dashed #000; margin: 4px 0; }
</style>
</head>
<body class="t-{{ $design['theme'] }} d-{{ $design['density'] }}">
