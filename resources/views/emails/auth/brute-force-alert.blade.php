@props([
    'name',
    'ipAddress',
    'attemptCount',
    'email',
    'lockoutMinutes' => 30,
    'secureUrl'      => null,
])

<x-emails.layout
    subject="Security Alert: Multiple Failed Login Attempts"
    :greeting="'Hello, ' . $name . '!'"
    :actionUrl="$secureUrl ?? config('app.url') . '/auth/change-password'"
    actionText="Secure Your Account"
    actionColor="#ef4444"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:20px;">
        <tr>
            <td style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 16px;">
                <p style="margin:0;font-size:14px;color:#b91c1c;font-weight:bold;">
                    &#128274; Security Alert: Multiple failed login attempts detected on your account.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        We have detected <strong>{{ $attemptCount }} failed login attempt{{ $attemptCount !== 1 ? 's' : '' }}</strong>
        on the account associated with <strong>{{ $email }}</strong>. Here are the details:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px 0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;width:35%;">Failed Attempts</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#b91c1c;font-weight:bold;
                        border-bottom:1px solid #e2e8f0;">{{ $attemptCount }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">IP Address</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;">{{ $ipAddress }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">Target Account</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;">{{ $email }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;">Lockout Duration</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;">{{ $lockoutMinutes }} minutes</td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        If this was you, please note that your account has been temporarily locked for
        <strong>{{ $lockoutMinutes }} minutes</strong> to protect your security.
        You may try again after the lockout period expires.
    </p>

    <p style="margin:0 0 24px 0;">
        If you do <strong>not</strong> recognise this activity, your account may be under attack.
        We strongly recommend securing your account immediately using the button below.
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        If you believe your account is at risk, also contact your system administrator immediately.
    </p>
</x-emails.layout>
