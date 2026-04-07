@props([
    'name',
    'resource',
    'ipAddress',
    'userAgent',
    'attemptTime',
    'secureUrl' => null,
])

<x-emails.layout
    subject="Security Warning: Unauthorized Access Attempt"
    :greeting="'Hello, ' . $name . '!'"
    :actionUrl="$secureUrl ?? config('app.url') . '/auth/change-password'"
    actionText="Secure Your Account"
    actionColor="#f59e0b"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:20px;">
        <tr>
            <td style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:14px 16px;">
                <p style="margin:0;font-size:14px;color:#92400e;font-weight:bold;">
                    &#9888; Warning: An attempt to access a restricted resource was blocked.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        We detected an unauthorized attempt to access a resource you do not have permission to view.
        The request was denied. Here are the details of the attempt:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px 0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;width:35%;">Resource Accessed</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#92400e;font-weight:bold;
                        border-bottom:1px solid #e2e8f0;word-break:break-all;">{{ $resource }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">IP Address</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;">{{ $ipAddress }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">Device / Browser</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;word-break:break-all;">{{ $userAgent }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;">Attempt Time</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;">{{ $attemptTime }}</td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        If this was you trying to access something you should have permission for, please contact your
        system administrator to request the appropriate access.
    </p>

    <p style="margin:0 0 24px 0;">
        If you do <strong>not</strong> recognise this activity, your account credentials may have been
        compromised. Secure your account immediately using the button below.
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        Repeated unauthorized access attempts from your account may result in a temporary suspension
        for security reasons.
    </p>
</x-emails.layout>
