@component('mail::message')
# Reset Your Password

Click the button below to reset your password.

@component('mail::button', ['url' => route('password.reset', ['token' => $token])])
Reset Password
@endcomponent

If you did not request a password reset, no further action is required.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
