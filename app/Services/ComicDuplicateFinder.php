<?php

namespace App\Services;

use App\Models\Comic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Looks for comics that may already contain the gallery being imported.
 *
 * The gallery id is a hard match (the same gallery was imported before).
 * Titles are matched with a tolerant score so a comic whose title was
 * edited, translated or stripped of `[artist]`/`[language]` noise still
 * shows up as a probable duplicate.
 */
class ComicDuplicateFinder
{
    /** Minimum score (0–1) to show a title as a probable match. */
    private const PROBABLE_SCORE = 0.72;

    /** How many probable title matches the import screen shows. */
    private const MATCH_LIMIT = 5;

    /** Cap on title candidates scored in PHP. */
    private const CANDIDATE_LIMIT = 500;

    /**
     * Common language/format/function words that should not make two
     * different comics look alike on their own.
     *
     * @var array<int, string>
     */
    private const STOPWORDS = [
        'a', 'an', 'and', 'the', 'of', 'to', 'in', 'on', 'at', 'for', 'with', 'from', 'by', 'or',
        'o', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na', 'nos', 'nas', 'para', 'por', 'com', 'sem', 'ao', 'aos',
        'vol', 'volume', 'chapter', 'chapters', 'capitulo', 'capitulos', 'capítulo', 'capítulos', 'part', 'parte', 'partes',
        'edition', 'edicao', 'edição', 'translated', 'traduzido', 'traduzida', 'english', 'portuguese', 'japanese',
        'japonês', 'português', 'inglês', 'ingles', 'digital', 'decensored', 'uncensored', 'colorized', 'colour',
        'complete', 'completo', 'oneshot', 'br', 'pt', 'en', 'jp', 'eng', 'jpn',
    ];

    /**
     * @return array{exact: Comic|null, matches: array<int, array{comic: Comic, score: int, label: string}>}
     */
    public function find(string $title, ?int $gid = null): array
    {
        $exact = $gid !== null ? $this->findByGallery($gid) : null;

        return [
            'exact' => $exact,
            'matches' => $this->titleMatches($title, $exact?->id),
        ];
    }

    /**
     * Find every comic that has at least one likely title duplicate.
     *
     * @param  Collection<int, Comic>  $comics
     * @return array<int, int>
     */
    public function potentialDuplicateIds(Collection $comics): array
    {
        $titles = [];
        $comicIdsByKey = [];

        foreach ($comics as $comic) {
            $titles[$comic->id] = $comic->title;

            foreach ($this->candidateKeys($comic->title) as $key) {
                $comicIdsByKey[$key][] = $comic->id;
            }
        }

        $duplicateIds = [];

        foreach ($titles as $comicId => $title) {
            $candidateIds = [];

            foreach ($this->candidateKeys($title) as $key) {
                foreach ($comicIdsByKey[$key] ?? [] as $candidateId) {
                    if ($candidateId > $comicId) {
                        $candidateIds[$candidateId] = true;
                    }
                }
            }

            foreach (array_keys($candidateIds) as $candidateId) {
                if ($this->score($title, $titles[$candidateId]) >= self::PROBABLE_SCORE) {
                    $duplicateIds[$comicId] = true;
                    $duplicateIds[$candidateId] = true;
                }
            }
        }

        $ids = array_map('intval', array_keys($duplicateIds));
        sort($ids);

        return $ids;
    }

    /**
     * The comic that already imported this exact E-Hentai gallery.
     */
    public function findByGallery(int $gid): ?Comic
    {
        return Comic::query()
            ->whereHas('ehImport', fn (Builder $query) => $query->where('gid', $gid))
            ->with('ehImport:id,comic_id,gid')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, array{comic: Comic, score: int, label: string}>
     */
    protected function titleMatches(string $title, ?int $excludeComicId): array
    {
        $normalized = $this->normalize($title);
        if ($normalized === '') {
            return [];
        }

        $query = Comic::query()->with('ehImport:id,comic_id,gid');

        if ($excludeComicId !== null) {
            $query->where('comics.id', '!=', $excludeComicId);
        }

        $this->narrowByTokens($query, $this->tokens($normalized));

        $matches = [];
        foreach ($query->latest('id')->limit(self::CANDIDATE_LIMIT)->get(['id', 'title', 'author', 'published_at', 'slug']) as $comic) {
            $score = $this->score($title, $comic->title);

            if ($score < self::PROBABLE_SCORE) {
                continue;
            }

            $matches[] = [
                'comic' => $comic,
                'score' => (int) round($score * 100),
                'label' => $this->label($score),
            ];
        }

        usort($matches, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['comic']->id <=> $b['comic']->id);

        return array_slice($matches, 0, self::MATCH_LIMIT);
    }

    /**
     * Narrow the table scan with an OR of the longest title tokens. The
     * final decision is still made by the tolerant scorer below.
     *
     * @param  array<int, string>  $tokens
     */
    protected function narrowByTokens(Builder $query, array $tokens): void
    {
        $needles = array_slice($tokens, 0, 4);

        if ($needles === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $inner) use ($needles): void {
            foreach ($needles as $needle) {
                $inner->orWhere('title', 'like', '%'.$needle.'%');
            }
        });
    }

    /**
     * Best score across the full title and the title with bracketed
     * groups removed (`[Circle]`, `(Event)`, `[English]`, …).
     */
    protected function score(string $needleTitle, string $candidateTitle): float
    {
        $best = 0.0;

        foreach ($this->variants($needleTitle) as $needle) {
            foreach ($this->variants($candidateTitle) as $candidate) {
                $best = max($best, $this->scorePair($needle, $candidate));
            }
        }

        return $best;
    }

    protected function scorePair(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $aTokens = $this->tokens($a);
        $bTokens = $this->tokens($b);

        if ($aTokens === [] || $bTokens === []) {
            return 0.0;
        }

        $shared = array_values(array_intersect($aTokens, $bTokens));

        if ($shared === []) {
            return 0.0;
        }

        similar_text($a, $b, $percent);
        $similarity = $percent / 100;
        $containment = count($shared) / min(count($aTokens), count($bTokens));

        // A single short shared word (e.g. "comic") is too weak to call a
        // duplicate unless the strings themselves are nearly identical.
        $longestShared = max(array_map(fn (string $token): int => mb_strlen($token), $shared));
        if (count($shared) < 2 && $longestShared < 8 && $similarity < 0.82) {
            return 0.0;
        }

        // One title being a prefix of the other is a strong signal, but
        // "Title" and "Title 2" should not score as an exact match.
        $lengthRatio = min(count($aTokens), count($bTokens)) / max(count($aTokens), count($bTokens));
        $containment *= 0.7 + (0.3 * $lengthRatio);

        return max($similarity, $containment);
    }

    /**
     * @return array<int, string>
     */
    protected function variants(string $value): array
    {
        $sources = [$value];
        $withoutBrackets = preg_replace('/[\[\(][^\]\)]*[\]\)]/u', ' ', $value);

        if (is_string($withoutBrackets) && trim($withoutBrackets) !== '') {
            $sources[] = $withoutBrackets;
        }

        $variants = [];
        foreach ($sources as $source) {
            $normalized = $this->normalize($source);
            if ($normalized !== '') {
                $variants[] = $normalized;
            }

            $ascii = $this->normalize(Str::ascii($source));
            if ($ascii !== '') {
                $variants[] = $ascii;
            }
        }

        return array_values(array_unique($variants));
    }

    protected function normalize(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace('&', ' and ', $value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * @return array<int, string> longest first, stopwords removed
     */
    protected function tokens(string $normalized): array
    {
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $token): bool => ! in_array($token, self::STOPWORDS, true),
        ));
        $tokens = array_values(array_unique($tokens));

        usort($tokens, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $tokens;
    }

    /**
     * @return array<int, string>
     */
    private function candidateKeys(string $title): array
    {
        $keys = [];

        foreach ($this->variants($title) as $variant) {
            $keys[] = 'title:'.$variant;

            foreach ($this->tokens($variant) as $token) {
                $keys[] = 'token:'.$token;
            }
        }

        return array_values(array_unique($keys));
    }

    protected function label(float $score): string
    {
        if ($score >= 0.95) {
            return 'Título praticamente igual';
        }

        if ($score >= 0.82) {
            return 'Título muito parecido';
        }

        return 'Título parecido';
    }
}
