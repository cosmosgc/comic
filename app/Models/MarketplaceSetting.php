<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marketplace tuning stored in the database. Values overlay
 * config/marketplace.php at boot; deleting a row restores the file
 * default. Mirrors ForumSetting / GamificationSetting.
 */
class MarketplaceSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, array{type: string, label: string, hint: string}>
     */
    public static function definitions(): array
    {
        return [
            'enabled' => [
                'type' => 'bool',
                'label' => 'Mercado ativo',
                'hint' => 'Desligado, o mercado fica indisponível para todos.',
            ],
            'require_approval' => [
                'type' => 'bool',
                'label' => 'Aprovar anúncios',
                'hint' => 'Novos anúncios só aparecem depois da aprovação do admin.',
            ],
            'default_currency' => [
                'type' => 'string',
                'label' => 'Moeda (rótulo)',
                'hint' => 'Apenas exibição: não há conversão nem pagamento no site.',
            ],
            'show_prices' => [
                'type' => 'bool',
                'label' => 'Mostrar preços',
                'hint' => 'Desligado, os anúncios mostram apenas "A combinar".',
            ],
            'default_expiry_days' => [
                'type' => 'int',
                'label' => 'Validade padrão (dias)',
                'hint' => 'Tempo até um anúncio ativo expirar.',
            ],
            'bump_cooldown_hours' => [
                'type' => 'int',
                'label' => 'Intervalo entre impulsos (h)',
                'hint' => 'Tempo mínimo para o vendedor subir o anúncio de novo.',
            ],
            'max_active_listings_per_user' => [
                'type' => 'int',
                'label' => 'Máx. anúncios ativos por pessoa',
                'hint' => 'Conta rascunhos, ativos e reservados.',
            ],
            'max_images' => [
                'type' => 'int',
                'label' => 'Máx. imagens por anúncio',
                'hint' => 'Limite de fotos no formulário.',
            ],
            'prohibited_words' => [
                'type' => 'json',
                'label' => 'Palavras proibidas',
                'hint' => 'Lista de termos rejeitados no título/descrição.',
            ],
        ];
    }

    public static function castValue(string $key, mixed $value): mixed
    {
        $type = static::definitions()[$key]['type'] ?? 'string';

        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => is_array($value) ? $value : (json_decode((string) $value, true) ?: []),
            default => (string) $value,
        };
    }

    public static function encode(string $key, mixed $value): string
    {
        $type = static::definitions()[$key]['type'] ?? 'string';

        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    public static function store(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => static::encode($key, $value)]);
    }
}
