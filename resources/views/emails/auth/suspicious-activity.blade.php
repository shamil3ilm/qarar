@props([
    'name',
    'activityType',
    'details',
    'ipAddress',
    'secureUrl' => null,
])

@php
    $activityLabels = [
        'concurrent_sessions'  => 'Concurrent Sessions Detected',
        'geo_anomaly'          => 'Geographic Anomaly',
        'unusual_time'         => 'Login at Unusual Time',
        'rapid_requests'       => 'Rapid Repeated Requests',
        'privilege_escalation' => 'Privilege Escalation Attempt',
    ];

    $activityLabel = $activityLabels[$activityType] ?? ucwords(str_replace('_', ' ', $activityType));
@endphp

<x-emails.layout
    subject="Security Alert: Suspicious Activity Detected"
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
                    &#9888; Suspicious activity has been detected on your account: <em>{{ $activityLabel }}</em>
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        Our security systems have flagged unusual behaviour on your account. Please review the details
        below and take action if you do not recognise this activity.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px 0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;width:35%;">Activity Type</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#92400e;font-weight:bold;
                        border-bottom:1px solid #e2e8f0;">{{ $activityLabel }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">IP Address</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;">{{ $ipAddress }}</td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;color:#374151;
                        border-bottom:1px solid #e2e8f0;">Detected At</td>
            <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                        border-bottom:1px solid #e2e8f0;">{{ now()->format('M d, Y H:i:s T') }}</td>
        </tr>
        @if (!empty($details))
        <tr>
            <td colspan="2" style="background:#ffffff;padding:0;">
                @foreach ($details as $key => $value)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:bold;
                                    color:#374151;border-top:1px solid #e2e8f0;width:35%;">
                            {{ ucwords(str_replace('_', ' ', $key)) }}
                        </td>
                        <td style="background:#ffffff;padding:10px 16px;font-size:13px;color:#1e293b;
                                    border-top:1px solid #e2e8f0;word-break:break-all;">
                            {{ is_array($value) ? implode(', ', $value) : $value }}
                        </td>
                    </tr>
                </table>
                @endforeach
            </td>
        </tr>
        @endif
    </table>

    <p style="margin:0 0 16px 0;">
        If this activity was not initiated by you, your account may be compromised. We strongly recommend:
    </p>

    <ul style="margin:0 0 24px 0;padding-left:20px;font-size:15px;line-height:1.8;color:#374151;">
        <li>Change your password immediately.</li>
        <li>Review recent activity in your account.</li>
        <li>Enable two-factor authentication if not already active.</li>
        <li>Contact your system administrator.</li>
    </ul>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        If you recognise this activity and it was expected, no action is required.
        You may continue using your account normally.
    </p>
</x-emails.layout>
