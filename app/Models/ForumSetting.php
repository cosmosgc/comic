<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Forum tuning stored in the database (edited in Admin → Fórum).
 * Values overlay config/forum.php at boot; deleting a row restores the
 * file default. Mirrors GamificationSetting.
 */
class ForumSetting extends Model
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
                'label' => 'Fórum ativo',
                'hint' => 'Desligado, o fórum fica indisponível para todos.',
            ],
            'approval_mode' => [
                'type' => 'string',
                'label' => 'Aprovação de posts',
                'hint' => 'none, first_post ou all.',
            ],
            'edit_grace_minutes' => [
                'type' => 'int',
                'label' => 'Janela de edição (min)',
                'hint' => 'Tempo que o autor tem para editar o próprio post.',
            ],
            'min_account_age_hours' => [
                'type' => 'int',
                'label' => 'Idade mínima da conta (h)',
                'hint' => 'Horas de conta antes de poder criar tópicos.',
            ],
            'max_attachments' => [
                'type' => 'int',
                'label' => 'Máx. anexos por post',
                'hint' => 'Quantidade de imagens permitidas.',
            ],
            'allowed_mime' => [
                'type' => 'json',
                'label' => 'MIME permitidos',
                'hint' => 'Lista de tipos de arquivo aceitos.',
            ],
            'posts_per_page' => [
                'type' => 'int',
                'label' => 'Posts por página',
                'hint' => 'Paginação da thread.',
            ],
            'hot_window_days' => [
                'type' => 'int',
                'label' => 'Janela "em alta" (dias)',
                'hint' => 'Período usado no ranking de atividade.',
            ],
            'auto_lock_days' => [
                'type' => 'int',
                'label' => 'Trancar inativos (dias)',
                'hint' => '0 desliga o trancamento automático.',
            ],
            'signature_enabled' => [
                'type' => 'bool',
                'label' => 'Assinaturas',
                'hint' => 'Reservado para uma fase futura.',
            ],
            'prohibited_words' => [
                'type' => 'json',
                'label' => 'Palavras proibidas',
                'hint' => 'Lista de termos rejeitados no título/corpo.',
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

    /**
     * Overlay every stored override onto the running config.
     */
    public static function applyToConfig(): void
    {
        foreach (static::all() as $row) {
            if (! array_key_exists($row->key, static::definitions())) {
                continue;
            }
            config(['forum.'.$row->key => static::castValue($row->key, $row->value)]);
        }
    }

    /**
     * @return array<string, bool>
     */
    public static function overridden(): array
    {
        return static::pluck('key')->mapWithKeys(fn ($key) => [$key => true])->all();
    }

    public static function store(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => static::encode($key, $value)]);
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
}
