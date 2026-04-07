@props([
    'subject',
    'greeting' => 'Hello,',
    'actionUrl' => null,
    'actionText' => null,
    'actionColor' => '#3b82f6',   // override for danger (#ef4444), warning (#f59e0b), etc.
    'organizationName' => config('app.name'),
])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">

    <!-- Outer wrapper -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background:#f4f4f4;padding:32px 16px;">
        <tr>
            <td align="center">

                <!-- Email container -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px;width:100%;">

                    <!-- Header -->
                    <tr>
                        <td style="background:#1e293b;padding:24px 32px;border-radius:8px 8px 0 0;">
                            <p style="margin:0;font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:0.5px;">
                                {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Content area -->
                    <tr>
                        <td style="background:#ffffff;padding:32px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">

                            <!-- Greeting -->
                            <p style="margin:0 0 20px 0;font-size:18px;font-weight:bold;color:#1e293b;">
                                {{ $greeting }}
                            </p>

                            <!-- Main slot content -->
                            <div style="font-size:15px;line-height:1.7;color:#374151;">
                                {{ $slot }}
                            </div>

                            <!-- Optional action button -->
                            @if ($actionUrl && $actionText)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                       style="margin-top:32px;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $actionUrl }}"
                                               style="display:inline-block;background:{{ $actionColor }};color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;padding:12px 24px;border-radius:6px;">
                                                {{ $actionText }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc;padding:20px 32px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;text-align:center;">
                            <p style="margin:0 0 6px 0;font-size:13px;color:#6b7280;">
                                {{ $organizationName }}
                            </p>
                            <p style="margin:0;font-size:12px;color:#9ca3af;">
                                You received this email because you're a user of {{ config('app.name') }}.
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- /Email container -->

            </td>
        </tr>
    </table>

</body>
</html>
