@props([
    'name',
    'ipAddress',
    'userAgent',
    'loginTime',
    'location'  => null,
    'secureUrl' => null,
])

<x-emails.layout
    subject="New Device Login Detected"
    :greeting="'Hello, ' . $name . '!'"
    :actionUrl="$secureUrl ?? config('app.url') . '/auth/change-password'"
    actionText="Secure My Account"
    actionColor="#ef4444"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:20px;">
        <tr>
            <td style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 16px;">
                <p style="margin:0;font-size:14px;color:#b91c1c;font-weight:bold;">
                    &#9888; Security Alert: A login was detected from a new device.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        We noticed a sign-in to your account from a device we haven't seen before. Here are the details:
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
                        border-bottom:1px solid #e2e8f0;">Device / Browser</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;word-break:break-all;">{{ $userAgent }}</td>
        </tr>
        @if ($location)
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">Location</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;">{{ $location }}</td>
        </tr>
        @endif
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;">Time</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;">{{ $loginTime }}</td>
        </tr>
    </table>

    <p style="margin:0 0 24px 0;">
        If this was you, no action is needed. If you do not recognize this activity, secure your account
        immediately using the button below.
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        If you believe your account is at risk, also contact your system administrator immediately.
    </p>
</x-emails.layout>
