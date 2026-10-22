<?php

namespace App\Services;

/**
 * Removes configured snippets (e.g. "[Portuguese-BR]") from imported
 * gallery titles. Rules are plain strings (not regex), compared without
 * case sensitivity, and can be edited in Admin → Importar (stored in
 * eh_import_settings) or in config/ehimport.php as the default list.
 */
class EhTitleCleaner
{
    /**
     * Active rules: the config array, or the newline-separated setting
     * that overlays it when someone saves the admin form.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        $configured = config('ehimport.title_cleanup', []);
        $lines = is_array($configured)
            ? $configured
            : (preg_split('/\r\n|\r|\n/', (string) $configured) ?: []);

        return array_values(array_filter(
            array_map(fn ($rule): string => trim((string) $rule), $lines),
            fn (string $rule): bool => $rule !== ''
        ));
    }

    public function clean(string $title): string
    {
        $original = trim($title);
        $rules = $this->rules();

        if ($original === '' || $rules === []) {
            return $original;
        }

        $clean = $original;
        foreach ($rules as $rule) {
            $replaced = preg_replace(
                '/'.preg_quote($rule, '/').'/iu',
                ' ',
                $clean
            );
            if ($replaced !== null) {
                $clean = $replaced;
            }
        }

        // Removing "[PT-BR]" next to other words leaves double spaces and
        // dangling separators ("Title - [PT-BR]" → "Title -").
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/^[\s\-–—|:]+|[\s\-–—|:]+$/u', '', $clean) ?? $clean;

        // Never let a rule that matches the whole title erase it.
        return $clean !== '' ? $clean : $original;
    }
}
