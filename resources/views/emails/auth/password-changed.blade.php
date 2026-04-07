@props([
    'name',
    'resetUrl' => null,
])

<x-emails.layout
    subject="Your Password Has Been Changed"
    :greeting="'Hello, ' . $name . '!'"
    :actionUrl="$resetUrl ?? config('app.url') . '/auth/forgot-password'"
    actionText="Reset Password"
    actionColor="#ef4444"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:20px;">
        <tr>
            <td style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 16px;">
                <p style="margin:0;font-size:14px;color:#b91c1c;font-weight:bold;">
                    &#9888; Security Alert: Your password was recently changed.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;">
        The password for your account was successfully changed. If you made this change, no further action is
        required.
    </p>

    <p style="margin:0 0 24px 0;">
        <strong>If you did not change your password</strong>, your account may have been compromised. Please
        reset your password immediately using the button below and contact your system administrator.
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        For security, all active sessions have been invalidated. You will need to sign in again on all devices.
    </p>
</x-emails.layout>
