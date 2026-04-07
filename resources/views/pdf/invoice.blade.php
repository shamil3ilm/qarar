<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            color: #1a1a1a;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            padding: 40px;
        }

        .header {
            border-bottom: 2px solid #2563eb;
            margin-bottom: 30px;
            padding-bottom: 20px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
        }

        .org-name {
            color: #2563eb;
            font-size: 22px;
            font-weight: bold;
        }

        .org-address {
            color: #6b7280;
            font-size: 11px;
            margin-top: 4px;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
            text-align: right;
        }

        .invoice-meta {
            color: #374151;
            font-size: 11px;
            margin-top: 6px;
            text-align: right;
        }

        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .party-block {
            width: 48%;
        }

        .party-label {
            color: #6b7280;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .party-name {
            font-size: 13px;
            font-weight: bold;
        }

        .party-address {
            color: #374151;
            font-size: 11px;
            margin-top: 4px;
            white-space: pre-line;
        }

        table {
            border-collapse: collapse;
            margin-bottom: 20px;
            width: 100%;
        }

        thead th {
            background-color: #2563eb;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            padding: 8px 10px;
            text-align: left;
        }

        thead th.text-right {
            text-align: right;
        }

        tbody tr:nth-child(even) {
            background-color: #f3f4f6;
        }

        tbody td {
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
            padding: 8px 10px;
            vertical-align: top;
        }

        tbody td.text-right {
            text-align: right;
        }

        .totals-section {
            margin-left: auto;
            width: 300px;
        }

        .totals-row {
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
        }

        .totals-row.grand-total {
            border-bottom: none;
            border-top: 2px solid #2563eb;
            font-size: 14px;
            font-weight: bold;
            margin-top: 4px;
            padding-top: 8px;
        }

        .totals-label {
            color: #374151;
        }

        .totals-amount {
            font-weight: 600;
        }

        .notes-section {
            border-top: 1px solid #e5e7eb;
            margin-top: 30px;
            padding-top: 16px;
        }

        .notes-label {
            color: #6b7280;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            bottom: 30px;
            color: #9ca3af;
            font-size: 10px;
            left: 40px;
            margin-top: 40px;
            position: absolute;
            right: 40px;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <div class="header-top">
            <div>
                <div class="org-name">{{ $invoice->organization->name ?? config('app.name') }}</div>
                @if (!empty($invoice->organization->address))
                    <div class="org-address">{{ $invoice->organization->address }}</div>
                @endif
                @if (!empty($invoice->organization->tax_number))
                    <div class="org-address">TRN: {{ $invoice->organization->tax_number }}</div>
                @endif
            </div>
            <div>
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    <strong>Invoice #:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}<br>
                    @if (!empty($invoice->due_date))
                        <strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="parties">
        <div class="party-block">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $invoice->customer_name ?? $invoice->customer?->name }}</div>
            @if (!empty($invoice->billing_address))
                <div class="party-address">{{ $invoice->billing_address }}</div>
            @endif
            @if (!empty($invoice->customer_tax_number))
                <div class="party-address">TRN: {{ $invoice->customer_tax_number }}</div>
            @endif
        </div>
        @if (!empty($invoice->shipping_address))
            <div class="party-block">
                <div class="party-label">Ship To</div>
                <div class="party-address">{{ $invoice->shipping_address }}</div>
            </div>
        @endif
    </div>

    {{-- Line Items --}}
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Tax %</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines ?? [] as $index => $line)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="text-right">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="text-right">
                        {{ isset($line->tax_rate) ? number_format((float) $line->tax_rate, 2) . '%' : '-' }}
                    </td>
                    <td class="text-right">
                        {{ number_format((float) ($line->total ?? ($line->quantity * $line->unit_price)), 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-section">
        <div class="totals-row">
            <span class="totals-label">Subtotal</span>
            <span class="totals-amount">
                {{ $invoice->currency_code ?? 'SAR' }} {{ number_format((float) ($invoice->subtotal ?? 0), 2) }}
            </span>
        </div>
        @if (($invoice->tax_amount ?? 0) > 0)
            <div class="totals-row">
                <span class="totals-label">Tax</span>
                <span class="totals-amount">
                    {{ $invoice->currency_code ?? 'SAR' }} {{ number_format((float) $invoice->tax_amount, 2) }}
                </span>
            </div>
        @endif
        @if (($invoice->discount_amount ?? 0) > 0)
            <div class="totals-row">
                <span class="totals-label">Discount</span>
                <span class="totals-amount">
                    - {{ $invoice->currency_code ?? 'SAR' }} {{ number_format((float) $invoice->discount_amount, 2) }}
                </span>
            </div>
        @endif
        <div class="totals-row grand-total">
            <span class="totals-label">Total</span>
            <span class="totals-amount">
                {{ $invoice->currency_code ?? 'SAR' }} {{ number_format((float) ($invoice->total ?? 0), 2) }}
            </span>
        </div>
    </div>

    {{-- Notes --}}
    @if (!empty($invoice->notes))
        <div class="notes-section">
            <div class="notes-label">Notes</div>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif

    @if (!empty($invoice->terms_and_conditions))
        <div class="notes-section">
            <div class="notes-label">Terms &amp; Conditions</div>
            <div>{{ $invoice->terms_and_conditions }}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Generated by {{ config('app.name') }}
    </div>

</body>
</html>
