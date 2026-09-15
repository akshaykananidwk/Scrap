<?php

use App\Core\View;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Document') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Print-first styling: "Save as PDF" in the browser produces the PDF,
           so no PDF library is required on shared hosting. */
        body { background: #fff; font-size: 13px; color: #111; }
        .sheet { max-width: 820px; margin: 0 auto; padding: 24px; }
        table { width: 100%; }
        .no-print { margin-bottom: 16px; }
        @page { size: A4; margin: 12mm; }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .sheet { padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print d-flex gap-2 justify-content-end">
        <button class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Print / Save as PDF
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.close()">Close</button>
    </div>
    <?= View::yieldSection('content') ?>
</div>
</body>
</html>
