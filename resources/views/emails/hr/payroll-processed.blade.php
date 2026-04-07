@props(['name', 'periodStart', 'periodEnd', 'netPay', 'currency'])

<x-emails.layout
    subject="Your Payslip Is Ready"
    :greeting="'Hello, ' . $name . '!'"
    actionUrl="#"
    actionText="View Payslip"
>
    <p style="margin:0 0 16px 0;">
        Your payslip for the period below has been processed and is ready for review. You can download it by
        clicking the button at the bottom of this email.
    </p>

    <!-- Payroll summary card -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">

        <!-- Pay period header -->
        <tr>
            <td style="background:#1e293b;padding:14px 20px;">
                <p style="margin:0;font-size:13px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;">
                    Pay Period
                </p>
                <p style="margin:4px 0 0 0;font-size:16px;font-weight:bold;color:#ffffff;">
                    {{ $periodStart }} &mdash; {{ $periodEnd }}
                </p>
            </td>
        </tr>

        <!-- Net pay row -->
        <tr>
            <td style="background:#f0f9ff;padding:20px;text-align:center;border-bottom:1px solid #e2e8f0;">
                <p style="margin:0 0 4px 0;font-size:13px;color:#6b7280;text-transform:uppercase;
                           letter-spacing:0.5px;">
                    Net Pay
                </p>
                <p style="margin:0;font-size:36px;font-weight:bold;color:#0369a1;">
                    {{ $currency }} {{ number_format((float) $netPay, 2) }}
                </p>
            </td>
        </tr>

        <!-- Status row -->
        <tr>
            <td style="background:#ffffff;padding:14px 20px;text-align:center;">
                <p style="margin:0;font-size:13px;color:#6b7280;">
                    Status:&nbsp;
                    <span style="display:inline-block;background:#dcfce7;color:#15803d;font-size:12px;
                                  font-weight:bold;padding:2px 10px;border-radius:12px;">
                        Processed
                    </span>
                </p>
            </td>
        </tr>

    </table>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        If you have any questions or concerns about your payslip, please contact your HR department.
    </p>
</x-emails.layout>
