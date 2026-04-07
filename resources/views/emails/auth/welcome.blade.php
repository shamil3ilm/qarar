@props(['name', 'organizationName', 'getStartedUrl' => null])

<x-emails.layout
    subject="Welcome to {{ $organizationName }}"
    :greeting="'Welcome, ' . $name . '!'"
    :organizationName="$organizationName"
    :actionUrl="$getStartedUrl ?? config('app.url')"
    actionText="Get Started"
>
    <p style="margin:0 0 16px 0;">
        We're thrilled to have you on board at <strong>{{ $organizationName }}</strong>. Your account has been
        successfully created and you're ready to get started.
    </p>

    <p style="margin:0 0 16px 0;">
        With <strong>{{ config('app.name') }}</strong> you can manage your business operations — from sales and
        inventory to HR and accounting — all in one place.
    </p>

    <p style="margin:0 0 16px 0;">
        Click the button below to log in and explore your dashboard.
    </p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        If you have any questions, please don't hesitate to reach out to your system administrator.
    </p>
</x-emails.layout>
