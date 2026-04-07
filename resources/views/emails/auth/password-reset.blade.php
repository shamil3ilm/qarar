@props(['name', 'code'])

<x-emails.layout
    subject="Reset Your Password"
    :greeting="'Hello, ' . $name . '!'"
>
    <p style="margin:0 0 16px 0;">
        We received a request to reset the password for your account. Use the code below to complete the process:
    </p>

    <!-- Code display box -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:24px 0;">
        <tr>
            <td align="center">
                <div style="display:inline-block;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;
                            padding:20px 40px;text-align:center;">
                    <p style="margin:0 0 4px 0;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;">
                        Password Reset Code
                    </p>
                    <p style="margin:0;font-size:32px;font-weight:bold;color:#1e293b;letter-spacing:8px;
                               font-family:'Courier New',Courier,monospace;">
                        {{ $code }}
                    </p>
                </div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        Enter this code on the password reset page to set a new password for your account.
    </p>

    <p style="margin:0 0 8px 0;font-size:13px;color:#6b7280;">
        This code will expire in <strong>15 minutes</strong>.
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        If you did not request a password reset, please ignore this email or contact your administrator if you
        have concerns about your account security.
    </p>
</x-emails.layout>
