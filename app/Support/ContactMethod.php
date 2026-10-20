<?php

namespace App\Support;

use App\Models\UserContactMethod;

/**
 * Contact-method catalogue and link normalisation. Values are stored in a
 * canonical form so links keep working after users paste a profile URL or
 * write @username instead of a plain handle.
 */
class ContactMethod
{
    /**
     * @return array<string, array{label: string, icon: string, placeholder: string, hint: string}>
     */
    public static function types(): array
    {
        return [
            'whatsapp' => [
                'label' => 'WhatsApp',
                'icon' => '💬',
                'placeholder' => '+55 11 99999-9999',
                'hint' => 'Informe o número com DDI e DDD.',
            ],
            'telegram' => [
                'label' => 'Telegram',
                'icon' => '✈️',
                'placeholder' => '@usuario',
                'hint' => 'Só o @ do perfil, sem link.',
            ],
            'discord' => [
                'label' => 'Discord',
                'icon' => '🎮',
                'placeholder' => 'usuario ou ID',
                'hint' => 'ID numérico vira link; nome de usuário é só exibido.',
            ],
            'email' => [
                'label' => 'E-mail',
                'icon' => '✉️',
                'placeholder' => 'voce@exemplo.com',
                'hint' => 'Abre o aplicativo de e-mail do visitante.',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'icon' => '📷',
                'placeholder' => '@usuario',
                'hint' => 'Só o @ do perfil.',
            ],
            'twitter' => [
                'label' => 'X / Twitter',
                'icon' => '𝕏',
                'placeholder' => '@usuario',
                'hint' => 'Só o @ do perfil.',
            ],
            'github' => [
                'label' => 'GitHub',
                'icon' => '🐙',
                'placeholder' => '@usuario',
                'hint' => 'Só o nome de usuário.',
            ],
            'other' => [
                'label' => 'Outro',
                'icon' => '🔗',
                'placeholder' => 'Site, telefone ou texto',
                'hint' => 'Se for um endereço válido, vira link.',
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, static::types());
    }

    public static function label(string $type): string
    {
        return static::types()[$type]['label'] ?? ucfirst($type);
    }

    public static function icon(string $type): string
    {
        return static::types()[$type]['icon'] ?? '🔗';
    }

    /**
     * Canonical stored value (digits for WhatsApp, bare handles, lowercase email).
     */
    public static function normalize(string $type, string $value): string
    {
        $value = trim($value);

        return match ($type) {
            'whatsapp' => preg_replace('/\D+/', '', $value) ?: '',
            'telegram', 'instagram', 'twitter', 'github' => static::normalizeHandle($value),
            'discord' => static::normalizeDiscord($value),
            'email' => mb_strtolower($value),
            default => $value,
        };
    }

    /**
     * Human-friendly value for profile/listing chips.
     */
    public static function display(string $type, string $value): string
    {
        $value = trim($value);

        return match ($type) {
            'whatsapp' => $value !== '' ? '+'.$value : '',
            'telegram', 'instagram', 'twitter', 'github' => $value !== '' ? '@'.ltrim($value, '@') : '',
            default => $value,
        };
    }

    /**
     * Public URL for a method. WhatsApp/email can prefill a listing subject.
     */
    public static function url(string $type, string $value, ?string $subject = null): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return match ($type) {
            'whatsapp' => $value !== ''
                ? 'https://wa.me/'.$value.($subject !== null ? '?text='.rawurlencode('Olá! Vi seu anúncio "'.$subject.'" no Mercado.') : '')
                : null,
            'telegram' => 'https://t.me/'.ltrim($value, '@'),
            'instagram' => 'https://instagram.com/'.ltrim($value, '@'),
            'twitter' => 'https://x.com/'.ltrim($value, '@'),
            'github' => 'https://github.com/'.ltrim($value, '@'),
            'email' => 'mailto:'.$value.($subject !== null ? '?subject='.rawurlencode('Contato pelo Mercado: '.$subject) : ''),
            'discord' => ctype_digit($value) ? 'https://discord.com/users/'.$value : null,
            'other' => static::urlFromText($value),
            default => null,
        };
    }

    /**
     * Per-type value rules used by both the profile form and listing forms.
     *
     * @return array<int, string>
     */
    public static function valueRules(string $type): array
    {
        return match ($type) {
            'whatsapp' => ['string', 'min:8', 'max:25', 'regex:/^[0-9\s()+\-\.]+$/'],
            'telegram', 'instagram', 'twitter', 'github' => ['string', 'min:2', 'max:50', 'regex:/^@?[A-Za-z0-9_.\-]+$/'],
            'discord' => ['string', 'min:2', 'max:100', 'regex:/^@?[A-Za-z0-9_.\-#]+$/'],
            'email' => ['string', 'email', 'max:190'],
            default => ['string', 'min:2', 'max:190'],
        };
    }

    public static function isExternalUrl(?string $url): bool
    {
        return $url !== null && preg_match('/^https?:\/\//i', $url) === 1;
    }

    /**
     * @return array{label: string, value: string, url: string|null}
     */
    public static function present(UserContactMethod $method): array
    {
        return [
            'label' => $method->label ?: static::label($method->type),
            'value' => static::display($method->type, $method->value),
            'url' => static::url($method->type, $method->value),
        ];
    }

    protected static function normalizeHandle(string $value): string
    {
        $value = trim($value);

        if (preg_match('~^(?:https?://)?(?:www\.)?(?:twitter\.com|x\.com|t\.me|telegram\.me|instagram\.com|github\.com)/([^/?#]+)~i', $value, $matches)) {
            $value = $matches[1];
        }

        return ltrim(trim($value), '@');
    }

    protected static function normalizeDiscord(string $value): string
    {
        $value = trim($value);

        if (preg_match('~^https?://(?:www\.)?discord\.com/users/([0-9]+)~i', $value, $matches)) {
            return $matches[1];
        }

        return ltrim($value, '@');
    }

    protected static function urlFromText(string $value): ?string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (preg_match('/^www\./i', $value)) {
            return 'https://'.$value;
        }

        return null;
    }
}
