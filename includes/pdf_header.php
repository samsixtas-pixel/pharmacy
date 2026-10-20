<?php
/**
 * Shared print header for PDF-friendly documents.
 */
$pdfTitle = $pdfTitle ?? 'Document';
$pdfSubtitle = $pdfSubtitle ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pdfTitle) ?> | <?= APP_NAME ?></title>
  <style>
    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      padding: 0;
      font-family: Helvetica, Arial, sans-serif;
      color: #111;
      background: #f5f5f5;
      font-size: 13px;
    }
    .page {
      max-width: 900px;
      margin: 20px auto;
      padding: 30px 36px;
      background: #fff;
      box-shadow: 0 3px 18px rgba(0, 0, 0, .08);
    }
    .doc-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 3px solid #0D6E5D;
    }
    .doc-brand h1 {
      margin: 0;
      color: #0D6E5D;
      font-size: 22px;
      letter-spacing: -.5px;
    }
    .doc-brand .tagline {
      margin-top: 2px;
      color: #555;
      font-size: 11.5px;
    }
    .doc-meta {
      color: #333;
      font-size: 11.5px;
      line-height: 1.55;
      text-align: right;
    }
    .doc-meta .doc-type {
      color: #0D6E5D;
      font-size: 13px;
      font-weight: 700;
    }
    .section-title {
      margin: 22px 0 10px;
      padding-bottom: 4px;
      color: #0D6E5D;
      border-bottom: 1px solid #d8e6e0;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
    }
    table.tbl {
      width: 100%;
      border-collapse: collapse;
      font-size: 12.5px;
    }
    table.tbl th {
      padding: 8px 10px;
      color: #fff;
      background: #0D6E5D;
      font-size: 11px;
      letter-spacing: .05em;
      text-align: left;
      text-transform: uppercase;
    }
    table.tbl td {
      padding: 8px 10px;
      vertical-align: top;
      border-bottom: 1px solid #e5eee9;
    }
    table.tbl tr:nth-child(even) td { background: #fafcfb; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .muted { color: #666; }
    .bold { font-weight: 700; }
    .totals {
      width: 340px;
      margin: 16px 0 0 auto;
      font-size: 13px;
    }
    .totals .row {
      display: flex;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px solid #eee;
    }
    .totals .row.grand {
      padding: 9px 0;
      color: #0D6E5D;
      border-top: 2px solid #0D6E5D;
      border-bottom: 2px solid #0D6E5D;
      font-size: 14px;
      font-weight: 700;
    }
    .totals .row.loss { color: #b91c1c; }
    .badge {
      display: inline-block;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .badge-paid { background: #d6f2e2; color: #0D6E5D; }
    .badge-partial { background: #fff3d6; color: #a8680a; }
    .badge-unpaid { background: #fce0e0; color: #b91c1c; }
    .signatures {
      display: flex;
      justify-content: space-between;
      margin-top: 60px;
      font-size: 12px;
    }
    .signatures .sig {
      width: 42%;
      padding-top: 6px;
      color: #333;
      border-top: 1px solid #333;
      text-align: center;
    }
    .doc-footer {
      margin-top: 26px;
      padding-top: 12px;
      color: #777;
      border-top: 1px dashed #ccc;
      font-size: 11px;
      text-align: center;
    }
    .print-toolbar {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      max-width: 900px;
      margin: 16px auto 0;
    }
    .print-toolbar button, .print-toolbar a {
      padding: 8px 16px;
      border: 1px solid transparent;
      border-radius: 6px;
      color: #333;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
    }
    .btn-print { color: #fff; background: #0D6E5D; }
    .btn-back { border-color: #ccc; background: #fff; }
    @media print {
      body { background: #fff; }
      .page {
        max-width: none;
        margin: 0;
        padding: 12mm 10mm;
        box-shadow: none;
      }
      .print-toolbar { display: none; }
      table.tbl thead { display: table-header-group; }
      table.tbl tr { page-break-inside: avoid; }
      @page { size: A4; margin: 10mm; }
    }
  </style>
</head>
<body>
  <div class="print-toolbar no-print">
    <button class="btn-print" type="button" onclick="window.print()">🖨 Save as PDF / Print</button>
    <a class="btn-back" href="javascript:history.back()">Back</a>
  </div>
  <div class="page">
    <div class="doc-head">
      <div class="doc-brand">
        <h1><?= e(APP_NAME) ?></h1>
        <div class="tagline">Beverages • Drinks Products — Retail &amp; System Managed</div>
      </div>
      <div class="doc-meta">
        <div class="doc-type"><?= e($pdfTitle) ?></div>
        <?php if ($pdfSubtitle): ?><div><?= e($pdfSubtitle) ?></div><?php endif; ?>
        <div>Generated: <?= date('d M Y, H:i') ?></div>
        <div>By: <?= e($_SESSION['full_name'] ?? '') ?></div>
      </div>
    </div>
