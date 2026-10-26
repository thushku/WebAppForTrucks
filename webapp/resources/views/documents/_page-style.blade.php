{{-- The plain A4 page our own documents share (statement, quotation, voucher — M19, M21). --}}
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font: 12px/1.45 "Noto Sans", Arial, sans-serif; color: #0f1a2b; background: #f5f7fa; }
  .page { max-width: 210mm; margin: 16px auto; background: #fff; padding: 14mm 12mm; }
  .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #162f5e; padding-bottom: 10px; }
  .head h1 { margin: 0; font-size: 18px; }
  .muted { color: #5b6b80; }
  .title { text-align: right; }
  .title h2 { margin: 0; font-size: 16px; letter-spacing: .02em; }
  .to { margin: 14px 0; display: flex; justify-content: space-between; gap: 16px; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #5b6b80; border-bottom: 1px solid #c8d1dc; padding: 6px 4px; }
  td { border-bottom: 1px solid #e4e9f0; padding: 5px 4px; vertical-align: top; }
  td:first-child { white-space: nowrap; }
  .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .sum td { font-weight: 700; border-top: 2px solid #162f5e; }
  .foot { margin-top: 18px; font-size: 11px; }
  .signs { display: flex; justify-content: space-between; gap: 24px; margin-top: 48px; }
  .signs div { flex: 1; border-top: 1px solid #8a97a8; padding-top: 6px; font-size: 11px; color: #5b6b80; }
  .scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  /* On a phone (a client opening the link): no paper margins, the header wraps, wide tables scroll inside themselves. */
  @media screen and (max-width: 640px) { .page { margin: 0; padding: 16px; } .head, .to, .signs { flex-wrap: wrap; } .title { text-align: left; } .scroll table { min-width: 560px; } }
  @media print { body { background: #fff; } .page { margin: 0; max-width: none; padding: 0; } @page { size: A4; margin: 12mm; } }
</style>
