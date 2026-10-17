<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Gamification tuning stored in the database (edited in Admin →
 * Gamificação). Values overlay config/gamification.php at boot;
 * deleting a row restores the file default. The single source of
 * truth for keys and casting lives in definitions().
 */
class GamificationSetting extends Model
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
                'label' => 'Gamificação ativa',
                'hint' => 'Desligado, nenhuma exp/conquista é gravada.',
            ],
            'level_base' => [
                'type' => 'int',
                'label' => 'Base de XP por nível',
                'hint' => 'Escala da curva de níveis.',
            ],
            'level_curve' => [
                'type' => 'string',
                'label' => 'Curva de níveis',
                'hint' => 'linear, triangular, quadratic ou exponential.',
            ],
            'streak_bonus' => [
                'type' => 'int',
                'label' => 'Bônus por dia de sequência',
                'hint' => 'XP extra = bônus × dias seguidos.',
            ],
            'exp' => [
                'type' => 'json',
                'label' => 'XP por ação',
                'hint' => 'Mapa ação → XP.',
            ],
            'unique_subject_actions' => [
                'type' => 'json',
                'label' => 'Ações que pagam só uma vez',
                'hint' => 'Evita farmar curtindo/descurtindo.',
            ],
            'badges' => [
                'type' => 'json',
                'label' => 'Catálogo de conquistas',
                'hint' => 'Mapa chave → definição.',
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
     * Overlay every stored override onto the running config. Called once
     * at boot; safe to call again after saving from the admin panel.
     */
    public static function applyToConfig(): void
    {
        foreach (static::all() as $row) {
            if (! array_key_exists($row->key, static::definitions())) {
                continue;
            }
            config(['gamification.'.$row->key => static::castValue($row->key, $row->value)]);
        }
    }

    /**
     * Whether a key currently comes from the database rather than the
     * config file (the admin view shows this).
     *
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
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
