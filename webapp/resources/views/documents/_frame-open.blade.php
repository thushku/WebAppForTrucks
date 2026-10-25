{{-- One self-contained page: the preview, the browser print, the public link and the PDF are this same HTML. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $pageTitle }}</title>
<style>
  @page { size: {{ $paper ?? 'A4' }}; margin: 12mm 12mm 14mm; }
  * { box-sizing: border-box; }
  html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { margin: 0; color: #111827; font: 10pt/1.4 "Noto Sans", "Segoe UI", Arial, "Noto Sans Devanagari", "Noto Sans Malayalam", "Noto Sans Tamil", "Noto Sans Arabic", sans-serif; background: #eef1f5; }
  .sheet { position: relative; background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 12mm; box-shadow: 0 2px 12px rgba(15, 26, 43, .12); }
  .sheet.a5 { width: 148mm; min-height: 210mm; }
  @media print { body { background: #fff; } .sheet { margin: 0; width: auto; min-height: 0; padding: 0; box-shadow: none; } .sheet + .sheet { break-before: page; } .no-print { display: none !important; } }
  h1 { margin: 0; font-size: 15pt; letter-spacing: .04em; text-transform: uppercase; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 4px 6px; vertical-align: top; text-align: left; }
  .box { border: 1px solid #374151; }
  .grid th, .grid td { border: 1px solid #9ca3af; }
  .grid thead th { background: #f3f4f6; font-size: 8pt; text-transform: uppercase; letter-spacing: .03em; }
  .r { text-align: right; } .c { text-align: center; }
  .nowrap, .num { white-space: nowrap; } .num { font-variant-numeric: tabular-nums; }
  .muted { color: #6b7280; } .small { font-size: 8.5pt; } .strong { font-weight: 700; }
  .label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
  .copy { display: inline-block; border: 1px solid #111827; padding: 1px 8px; font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
  .logo { max-height: 18mm; max-width: 40mm; object-fit: contain; }
  .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; font-size: 80pt; font-weight: 800; color: rgba(220, 38, 38, .12); transform: rotate(-24deg); letter-spacing: .1em; }
  .split { display: flex; gap: 10px; } .split > * { flex: 1; }
  .qr svg { width: 26mm; height: 26mm; }
  .toolbar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 8px; background: #0f1a2b; }
  .toolbar a, .toolbar button { font: 600 10pt sans-serif; color: #fff; background: #1f6feb; border: 0; border-radius: 8px; padding: 6px 12px; text-decoration: none; cursor: pointer; }
</style>
</head>
<body>
