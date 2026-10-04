<?php

namespace Tests\Unit;

use App\Services\ChangelogReader;
use Tests\TestCase;

class ChangelogReaderTest extends TestCase
{
    protected string $dir = '';

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            foreach (glob($this->dir.'/*.json') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    protected function reader(array $files): ChangelogReader
    {
        $this->dir = sys_get_temp_dir().'/changelog-test-'.uniqid();
        mkdir($this->dir, 0777, true);
        foreach ($files as $name => $content) {
            file_put_contents($this->dir.'/'.$name, $content);
        }

        return new ChangelogReader($this->dir);
    }

    protected function entry(array $overrides = []): string
    {
        return json_encode(array_merge([
            'pr' => null,
            'title' => 'Some change',
            'date' => '2026-10-04',
            'category' => 'Added',
            'tags' => ['upload'],
            'summary' => 'Short summary.',
            'body' => 'Long body.',
        ], $overrides));
    }

    public function test_lists_entries_newest_first_and_skips_invalid(): void
    {
        $reader = $this->reader([
            'a.json' => $this->entry(['title' => 'Older', 'date' => '2026-10-01']),
            'b.json' => $this->entry(['title' => 'Newer', 'date' => '2026-10-04']),
            'broken.json' => '{not json',
            'empty.json' => json_encode(['title' => '', 'date' => '2026-10-02']),
            'baddate.json' => $this->entry(['title' => 'Bad', 'date' => 'yesterday']),
        ]);

        $all = $reader->all();

        $this->assertSame(['Newer', 'Older'], $all->pluck('title')->all());
        $this->assertSame('b', $all->first()['id']);
    }

    public function test_find_returns_entry_or_null(): void
    {
        $reader = $this->reader(['a.json' => $this->entry(['title' => 'Hello'])]);

        $this->assertSame('Hello', $reader->find('a')['title']);
        $this->assertNull($reader->find('missing'));
    }

    public function test_search_matches_title_body_tags_and_category(): void
    {
        $reader = $this->reader([
            'a.json' => $this->entry(['title' => 'FTP Deploy panel', 'tags' => ['ftp']]),
            'b.json' => $this->entry(['title' => 'Avatars', 'body' => 'profile pictures', 'tags' => ['profile']]),
            'c.json' => $this->entry(['title' => 'Nginx fix', 'category' => 'Fixed']),
        ]);
        $all = $reader->all();

        $this->assertSame(['a'], $reader->search($all, 'ftp')->pluck('id')->all());
        $this->assertSame(['b'], $reader->search($all, 'PICTURES')->pluck('id')->all());
        $this->assertSame(['c'], $reader->search($all, 'fixed')->pluck('id')->all());
        $this->assertCount(3, $reader->search($all, ''));
        $this->assertCount(0, $reader->search($all, 'zzz-no-match'));
    }

    public function test_paginate_slices_and_preserves_query(): void
    {
        $files = [];
        for ($i = 1; $i <= 5; $i++) {
            $files["e{$i}.json"] = $this->entry([
                'title' => "Entry {$i}",
                'date' => sprintf('2026-10-%02d', $i),
            ]);
        }
        $reader = $this->reader($files);

        $page = $reader->paginate($reader->all(), 2, 2, 'http://test/changelog', ['q' => 'ftp']);

        $this->assertSame(['Entry 3', 'Entry 2'], $page->pluck('title')->all());
        $this->assertSame(5, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertStringContainsString('q=ftp', (string) $page->nextPageUrl());
        $this->assertStringContainsString('page=3', (string) $page->nextPageUrl());
    }
}
