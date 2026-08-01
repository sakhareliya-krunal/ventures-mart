<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $document_title }} {{ $order->invoice_number }}</title>
  <style>
    @page { margin: 8mm; }
    * { box-sizing: border-box; }
    body {
      background: #ffffff;
      color: #1F2937;
      font-family: DejaVu Sans, sans-serif;
      font-size: 9.2px;
      line-height: 1.32;
      margin: 0;
      padding: 0;
    }
    table { border-collapse: collapse; }
    .w-full { width: 100%; }
    .muted { color: #6B7280; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .nowrap { white-space: nowrap; }
    .card {
      background: #F8FAFC;
      border: 1px solid #E5E7EB;
      border-radius: 4px;
      padding: 6px 8px;
    }
    .section-title {
      color: #052B68;
      font-size: 8.5px;
      font-weight: bold;
      letter-spacing: 0.04em;
      margin: 0 0 3px;
      text-transform: uppercase;
    }
    .doc-title {
      color: #052B68;
      font-size: 15px;
      font-weight: bold;
      letter-spacing: 0.05em;
      margin: 6px 0 5px;
      text-align: center;
      text-transform: uppercase;
    }
    .badge {
      border-collapse: separate;
      border-radius: 3px;
      font-size: 8px;
      font-weight: bold;
      letter-spacing: 0.03em;
      line-height: 1;
      margin-left: auto;
      text-transform: uppercase;
    }
    .badge td {
      line-height: 1;
      padding: 4px 8px;
      text-align: center;
      vertical-align: middle;
    }
    .items {
      margin: 6px 0 5px;
      width: 100%;
    }
    .items th {
      background: #F1F5F9;
      border: 1px solid #E5E7EB;
      color: #052B68;
      font-size: 8px;
      font-weight: bold;
      letter-spacing: 0.02em;
      padding: 4px 3px;
      text-transform: uppercase;
    }
    .items td {
      border: 1px solid #E5E7EB;
      padding: 4px 3px;
      vertical-align: middle;
    }
    .thumb {
      border: 1px solid #E5E7EB;
      border-radius: 2px;
      display: block;
      height: 32px;
      width: 32px;
    }
    .thumb-placeholder {
      background: #F1F5F9;
      border: 1px solid #E5E7EB;
      border-radius: 2px;
      color: #9CA3AF;
      font-size: 7px;
      height: 32px;
      line-height: 32px;
      text-align: center;
      width: 32px;
    }
    .product-name {
      color: #1F2937;
      font-size: 9px;
      font-weight: bold;
      word-wrap: break-word;
    }
    .summary td {
      padding: 2px 0;
    }
    .summary td:last-child {
      text-align: right;
      white-space: nowrap;
    }
    .grand-bar {
      background: #16A34A;
      border-radius: 3px;
      color: #ffffff;
      font-size: 10.5px;
      font-weight: bold;
      margin-top: 3px;
    }
    .grand-bar td {
      color: #ffffff;
      line-height: 1.2;
      padding: 8px 10px;
      vertical-align: middle;
    }
    .terms {
      border-top: 1px solid #E5E7EB;
      color: #6B7280;
      font-size: 8px;
      line-height: 1.3;
      margin-top: 6px;
      padding-top: 4px;
    }
    .terms ul {
      margin: 2px 0 0;
      padding-left: 12px;
    }
    .terms li { margin-bottom: 1px; }
    .footer-thanks {
      color: #052B68;
      font-size: 10px;
      font-weight: bold;
      margin: 0 0 2px;
    }
    .qr {
      display: block;
      height: 56px;
      margin: 0 auto 2px;
      width: 56px;
    }
  </style>
</head>
<body>
  @include('invoices.partials.header')

  <div class="doc-title">{{ $document_title }}</div>

  @include('invoices.partials.meta')

  @include('invoices.partials.parties')

  @include('invoices.partials.items')

  @include('invoices.partials.summary')

  @if ($has_courier)
    @include('invoices.partials.courier')
  @endif

  @include('invoices.partials.footer')

  <div class="terms">
    <strong style="color:#052B68;">Terms &amp; Conditions</strong>
    <ul>
      <li>Goods once sold will be exchanged or returned as per Ventures Mart return policy.</li>
      <li>Please retain this invoice for warranty and return claims.</li>
      <li>Subject to Rajkot, Gujarat jurisdiction.</li>
      <li>This is a computer-generated invoice{{ $has_gstin ? ' / tax invoice' : '' }}.</li>
    </ul>
  </div>
</body>
</html>
