<?php

return [
    /*
    | PDFs are the document's own HTML printed by a headless Chromium on the
    | server (blueprint 10 §10, no PDF package).
    |   'auto'     — use a Chromium-based browser when one is found, else the browser's print dialog;
    |   'chromium' — insist on the server-side browser (DOCUMENTS_CHROME_PATH when it is not in a usual place);
    |   'browser'  — never on the server: the person prints to PDF from their browser.
    */
    'pdf_driver'  => env('DOCUMENTS_PDF_DRIVER', 'auto'),
    'chrome_path' => env('DOCUMENTS_CHROME_PATH'),
    'pdf_timeout' => env('DOCUMENTS_PDF_TIMEOUT', 45),
];
