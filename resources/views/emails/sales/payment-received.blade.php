@props(['name', 'amount', 'currency', 'referenceNumber', 'paymentDate'])

<x-emails.layout
    subject="Payment Received — Thank You!"
    :greeting="'Hello, ' . $name . '!'"
>
    <!-- Green checkmark confirmation -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:24px;">
        <tr>
            <td align="center">
                <div style="display:inline-block;background:#16a34a;width:56px;height:56px;border-radius:50%;
                             text-align:center;line-height:56px;">
                    <span style="color:#ffffff;font-size:28px;font-weight:bold;line-height:56px;">&#10003;</span>
                </div>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-top:12px;">
                <p style="margin:0;font-size:20px;font-weight:bold;color:#15803d;">
                    Payment Confirmed
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        Thank you! We have successfully received your payment. Here are the details for your records:
    </p>

    <!-- Payment details card -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">

        <!-- Amount row -->
        <tr>
            <td style="background:#f0fdf4;padding:20px;text-align:center;border-bottom:1px solid #e2e8f0;">
                <p style="margin:0 0 4px 0;font-size:13px;color:#6b7280;text-transform:uppercase;
                           letter-spacing:0.5px;">
                    Amount Received
                </p>
                <p style="margin:0;font-size:36px;font-weight:bold;color:#15803d;">
                    {{ $currency }} {{ number_format((float) $amount, 2) }}
                </p>
            </td>
        </tr>

        <!-- Reference number row -->
        <tr>
            <td style="background:#f8fafc;padding:10px 20px;font-size:13px;color:#374151;
                        border-bottom:1px solid #e2e8f0;">
                <strong>Reference Number:</strong>&nbsp;{{ $referenceNumber }}
            </td>
        </tr>

        <!-- Payment date row -->
        <tr>
            <td style="background:#ffffff;padding:10px 20px;font-size:13px;color:#374151;">
                <strong>Payment Date:</strong>&nbsp;{{ $paymentDate }}
            </td>
        </tr>

    </table>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        Please keep this email as a record of your payment. If you have any questions, contact us and reference
        your payment reference number above.
    </p>
</x-emails.layout>
