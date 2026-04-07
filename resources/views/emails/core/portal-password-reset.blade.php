@props(['email', 'token'])

<x-emails.layout
    subject="Reset Your Customer Portal Password"
    :greeting="'Hello!'"
>
    <p style="margin:0 0 16px 0;">
        We received a request to reset the password for the customer portal account associated with
        <strong>{{ $email }}</strong>.
    </p>

    <p style="margin:0 0 16px 0;">
        Use the link below to set a new password. This link will expire in <strong>60 minutes</strong>.
    </p>

    <!-- Reset link button -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:24px 0;">
        <tr>
            <td align="center">
                <a href="{{ config('app.frontend_url') }}/portal/reset-password/{{ $token }}"
                   style="display:inline-block;background:#1d4ed8;color:#ffffff;font-size:15px;
                          font-weight:600;text-decoration:none;padding:14px 32px;border-radius:6px;">
                    Reset Password
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px 0;font-size:13px;color:#6b7280;">
        If the button does not work, copy and paste this URL into your browser:
    </p>
    <p style="margin:0 0 16px 0;font-size:12px;color:#6b7280;word-break:break-all;">
        {{ config('app.frontend_url') }}/portal/reset-password/{{ $token }}
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;">
        If you did not request a password reset, you can safely ignore this email. Your password will
        not change until you follow the link above and create a new one.
    </p>
</x-emails.layout>
