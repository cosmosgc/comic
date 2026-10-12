@component('mail::message')
# Redefinir sua senha

Clique no botão abaixo para redefinir sua senha.

@component('mail::button', ['url' => route('password.reset', ['token' => $token])])
Redefinir senha
@endcomponent

Se você não pediu isso, ignore este e-mail.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent
