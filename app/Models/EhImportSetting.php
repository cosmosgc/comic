<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Import tuning stored in the database (edited in Admin → Importar).
 * Values overlay config/ehimport.php at boot; deleting a row restores
 * the file default. Single source of truth for keys, labels, limits
 * and casting lives in definitions().
 */
class EhImportSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, array{label: string, hint: string, type: string, min?: int, max?: int, maxlen?: int}>
     */
    public static function definitions(): array
    {
        return [
            'chunk' => [
                'label' => 'Páginas por lote',
                'hint' => 'Quantas páginas cada requisição baixa.',
                'type' => 'int', 'min' => 1, 'max' => 20,
            ],
            'max_per_minute' => [
                'label' => 'Downloads por minuto (teto)',
                'hint' => 'Estourando, a tela espera sozinha (429 + Retry-After).',
                'type' => 'int', 'min' => 1, 'max' => 120,
            ],
            'image_delay_ms' => [
                'label' => 'Pausa antes de cada imagem (ms)',
                'hint' => 'Imagens são o que dispara 509 e firewall.',
                'type' => 'int', 'min' => 0, 'max' => 30000,
            ],
            'delay_ms' => [
                'label' => 'Pausa entre requisições (ms)',
                'hint' => 'Vale para páginas HTML e downloads.',
                'type' => 'int', 'min' => 0, 'max' => 30000,
            ],
            'delay_jitter_ms' => [
                'label' => 'Variação aleatória (ms)',
                'hint' => 'Soma de 0 até esse valor em cada pausa.',
                'type' => 'int', 'min' => 0, 'max' => 10000,
            ],
            'html_timeout' => [
                'label' => 'Timeout páginas HTML (s)',
                'hint' => 'Galeria e páginas /s/.',
                'type' => 'int', 'min' => 5, 'max' => 120,
            ],
            'image_timeout' => [
                'label' => 'Timeout imagens (s)',
                'hint' => 'Cada imagem original.',
                'type' => 'int', 'min' => 10, 'max' => 300,
            ],
            'max_image_mb' => [
                'label' => 'Tamanho máximo por imagem (MB)',
                'hint' => 'Acima disso a página é pulada como falha.',
                'type' => 'int', 'min' => 1, 'max' => 100,
            ],
            'max_attempts' => [
                'label' => 'Tentativas por página',
                'hint' => 'Antes de marcar como falhada.',
                'type' => 'int', 'min' => 1, 'max' => 10,
            ],
            'max_pages' => [
                'label' => 'Máximo de páginas por import',
                'hint' => 'Trava de segurança para galerias gigantes.',
                'type' => 'int', 'min' => 1, 'max' => 2000,
            ],
            'max_index_pages' => [
                'label' => 'Páginas de índice varridas',
                'hint' => 'Quantas ?p=N da galeria o listador percorre.',
                'type' => 'int', 'min' => 1, 'max' => 100,
            ],
            'image_proxy' => [
                'label' => 'Proxy de imagens',
                'hint' => 'Relay porta 443 p/ firewall que bloqueia hath (vazio = direto). Terminar com ?url=',
                'type' => 'string', 'maxlen' => 500,
            ],
            'title_cleanup' => [
                'label' => 'Remover dos títulos',
                'hint' => 'Um texto por linha, sem diferenciar maiúsculas (ex.: [Portuguese-BR]). Vazio = lista padrão do config/ehimport.php.',
                'type' => 'text', 'maxlen' => 2000,
            ],
        ];
    }

    public static function castValue(string $key, mixed $value): mixed
    {
        $def = static::definitions()[$key] ?? null;
        if ($def === null) {
            return $value;
        }

        return ($def['type'] ?? 'string') === 'int' ? (int) $value : (string) $value;
    }
}
