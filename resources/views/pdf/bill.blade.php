<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill {{ $bill->bill_number }}</title>
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
            border-bottom: 2px solid #7c3aed;
            margin-bottom: 30px;
            padding-bottom: 20px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
        }

        .org-name {
            color: #7c3aed;
            font-size: 22px;
            font-weight: bold;
        }

        .org-address {
            color: #6b7280;
            font-size: 11px;
            margin-top: 4px;
        }

        .bill-title {
            color: #7c3aed;
            font-size: 28px;
            font-weight: bold;
            text-align: right;
        }

        .bill-meta {
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
            background-color: #7c3aed;
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
            border-top: 2px solid #7c3aed;
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
                <div class="org-name">{{ $bill->organization->name ?? config('app.name') }}</div>
                @if (!empty($bill->organization->address))
                    <div class="org-address">{{ $bill->organization->address }}</div>
                @endif
                @if (!empty($bill->organization->tax_number))
                    <div class="org-address">TRN: {{ $bill->organization->tax_number }}</div>
                @endif
            </div>
            <div>
                <div class="bill-title">BILL</div>
                <div class="bill-meta">
                    <strong>Bill #:</strong> {{ $bill->bill_number }}<br>
                    @if (!empty($bill->supplier_invoice_number))
                        <strong>Supplier Ref #:</strong> {{ $bill->supplier_invoice_number }}<br>
                    @endif
                    <strong>Date:</strong> {{ \Carbon\Carbon::parse($bill->bill_date)->format('d M Y') }}<br>
                    @if (!empty($bill->due_date))
                        <strong>Due Date:</strong> {{ \Carbon\Carbon::parse($bill->due_date)->format('d M Y') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="parties">
        <div class="party-block">
            <div class="party-label">Supplier</div>
            <div class="party-name">{{ $bill->supplier_name ?? $bill->supplier?->name }}</div>
            @if (!empty($bill->supplier_address))
                <div class="party-address">{{ $bill->supplier_address }}</div>
            @endif
            @if (!empty($bill->supplier_tax_number))
                <div class="party-address">TRN: {{ $bill->supplier_tax_number }}</div>
            @endif
        </div>
        <div class="party-block">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $bill->organization->name ?? config('app.name') }}</div>
            @if (!empty($bill->organization->address))
                <div class="party-address">{{ $bill->organization->address }}</div>
            @endif
        </div>
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
            @foreach ($bill->lines ?? [] as $index => $line)
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
                {{ $bill->currency_code ?? 'SAR' }} {{ number_format((float) ($bill->subtotal ?? 0), 2) }}
            </span>
        </div>
        @if (($bill->tax_amount ?? 0) > 0)
            <div class="totals-row">
                <span class="totals-label">Tax</span>
                <span class="totals-amount">
                    {{ $bill->currency_code ?? 'SAR' }} {{ number_format((float) $bill->tax_amount, 2) }}
                </span>
            </div>
        @endif
        @if (($bill->discount_amount ?? 0) > 0)
            <div class="totals-row">
                <span class="totals-label">Discount</span>
                <span class="totals-amount">
                    - {{ $bill->currency_code ?? 'SAR' }} {{ number_format((float) $bill->discount_amount, 2) }}
                </span>
            </div>
        @endif
        <div class="totals-row grand-total">
            <span class="totals-label">Total</span>
            <span class="totals-amount">
                {{ $bill->currency_code ?? 'SAR' }} {{ number_format((float) ($bill->total ?? 0), 2) }}
            </span>
        </div>
    </div>

    {{-- Notes --}}
    @if (!empty($bill->notes))
        <div class="notes-section">
            <div class="notes-label">Notes</div>
            <div>{{ $bill->notes }}</div>
        </div>
    @endif

    @if (!empty($bill->terms_and_conditions))
        <div class="notes-section">
            <div class="notes-label">Terms &amp; Conditions</div>
            <div>{{ $bill->terms_and_conditions }}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Generated by {{ config('app.name') }}
    </div>

</body>
</html>
