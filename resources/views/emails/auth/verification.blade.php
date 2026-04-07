@props(['name', 'code'])

<x-emails.layout
    subject="Verify Your Email Address"
    :greeting="'Hello, ' . $name . '!'"
>
    <p style="margin:0 0 16px 0;">
        Please verify your email address to complete your registration. Use the verification code below:
    </p>

    <!-- Code display box -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:24px 0;">
        <tr>
            <td align="center">
                <div style="display:inline-block;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;
                            padding:20px 40px;text-align:center;">
                    <p style="margin:0 0 4px 0;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;">
                        Verification Code
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
        Enter this code on the verification page to confirm your email address.
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        This code will expire in <strong>10 minutes</strong>. If you did not request this, you can safely ignore
        this email.
    </p>
</x-emails.layout>
