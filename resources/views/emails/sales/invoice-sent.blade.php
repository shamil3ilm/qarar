@props(['name', 'invoiceNumber', 'total', 'currency', 'dueDate' => null])

<x-emails.layout
    subject="Invoice {{ $invoiceNumber }} from {{ config('app.name') }}"
    :greeting="'Hello, ' . $name . '!'"
    actionUrl="#"
    actionText="View Invoice"
>
    <p style="margin:0 0 16px 0;">
        Please find your invoice details below. You can view and download the full invoice by clicking the
        button at the bottom of this email.
    </p>

    <!-- Invoice summary card -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">

        <!-- Invoice number row -->
        <tr>
            <td style="background:#1e293b;padding:14px 20px;">
                <p style="margin:0;font-size:13px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;">
                    Invoice Number
                </p>
                <p style="margin:4px 0 0 0;font-size:18px;font-weight:bold;color:#ffffff;">
                    {{ $invoiceNumber }}
                </p>
            </td>
        </tr>

        <!-- Amount due row -->
        <tr>
            <td style="background:#f0fdf4;padding:20px;text-align:center;border-bottom:1px solid #e2e8f0;">
                <p style="margin:0 0 4px 0;font-size:13px;color:#6b7280;text-transform:uppercase;
                           letter-spacing:0.5px;">
                    Amount Due
                </p>
                <p style="margin:0;font-size:36px;font-weight:bold;color:#15803d;">
                    {{ $currency }} {{ number_format((float) $total, 2) }}
                </p>
            </td>
        </tr>

        @if ($dueDate)
            <!-- Due date row -->
            <tr>
                <td style="background:#ffffff;padding:14px 20px;text-align:center;">
                    <p style="margin:0;font-size:13px;color:#6b7280;">
                        Payment due by
                        <strong style="color:#1e293b;">{{ $dueDate }}</strong>
                    </p>
                </td>
            </tr>
        @endif

    </table>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        If you have any questions about this invoice, please contact us and reference the invoice number above.
    </p>
</x-emails.layout>
