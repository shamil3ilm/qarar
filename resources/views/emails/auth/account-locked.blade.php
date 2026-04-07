@props([
    'name',
    'ipAddress',
    'lockoutMinutes',
    'unlockUrl' => null,
])

<x-emails.layout
    subject="Security Alert: Your Account Has Been Locked"
    :greeting="'Hello, ' . $name . '!'"
    :actionUrl="$unlockUrl ?? config('app.url') . '/auth/forgot-password'"
    actionText="Contact Support"
    actionColor="#ef4444"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:20px;">
        <tr>
            <td style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 16px;">
                <p style="margin:0;font-size:14px;color:#b91c1c;font-weight:bold;">
                    &#128274; Your account has been temporarily locked due to too many failed login attempts.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        To protect your account, we have temporarily suspended login access after multiple consecutive
        failed attempts were detected. Here are the details:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px 0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;width:35%;">IP Address</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;">{{ $ipAddress }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">Locked For</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#b91c1c;font-weight:bold;
                        border-bottom:1px solid #e2e8f0;">{{ $lockoutMinutes }} minutes</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;">Locked At</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;">{{ now()->format('M d, Y H:i:s T') }}</td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        <strong>How to regain access:</strong>
    </p>

    <ul style="margin:0 0 24px 0;padding-left:20px;font-size:15px;line-height:1.8;color:#374151;">
        <li>Wait <strong>{{ $lockoutMinutes }} minutes</strong> and try logging in again.</li>
        <li>If you have forgotten your password, use the reset link below.</li>
        <li>If you did not trigger this lockout, contact your system administrator immediately.</li>
    </ul>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        This lockout is a precautionary measure to keep your account safe. No data has been accessed.
    </p>
</x-emails.layout>
