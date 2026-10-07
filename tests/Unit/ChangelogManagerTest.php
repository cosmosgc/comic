<?php

namespace Tests\Unit;

use App\Services\ChangelogReader;
use App\Services\ChangelogWriter;
use App\Services\GithubPullRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChangelogManagerTest extends TestCase
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

    protected function writer(): ChangelogWriter
    {
        $this->dir = sys_get_temp_dir().'/changelog-mgr-'.uniqid();
        mkdir($this->dir, 0777, true);

        return new ChangelogWriter($this->dir);
    }

    public function test_validate_accepts_good_input(): void
    {
        [$data, $errors] = $this->writer()->validate([
            'title' => 'Some change',
            'date' => '2026-10-04',
            'category' => 'Fixed',
            'pr' => '12',
            'tags' => 'upload, FTP',
            'summary' => 'Short.',
            'body' => 'Long.',
        ], ['Added', 'Fixed']);

        $this->assertSame([], $errors);
        $this->assertSame(12, $data['pr']);
        $this->assertSame(['upload', 'ftp'], $data['tags']);
    }

    public function test_validate_rejects_bad_input(): void
    {
        [, $errors] = $this->writer()->validate([
            'title' => '',
            'date' => 'yesterday',
            'category' => 'Whatever',
            'pr' => 'abc',
            'summary' => str_repeat('x', 501),
        ], ['Added', 'Fixed']);

        foreach (['title', 'date', 'category', 'pr', 'summary'] as $field) {
            $this->assertArrayHasKey($field, $errors);
        }
    }

    public function test_save_roundtrips_through_reader(): void
    {
        $writer = $this->writer();
        $id = $writer->save([
            'pr' => 7,
            'title' => 'Roundtrip entry',
            'date' => '2026-10-04',
            'category' => 'Added',
            'tags' => ['a'],
            'summary' => 'S.',
            'body' => 'B.',
        ]);

        $reader = new ChangelogReader($this->dir);
        $found = $reader->find($id);

        $this->assertSame('Roundtrip entry', $found['title']);
        $this->assertSame([7], $reader->prsUsed());
        $this->assertStringStartsWith('2026-10-04-roundtrip-entry', $id);
    }

    public function test_suggest_id_avoids_collisions(): void
    {
        $writer = $this->writer();
        file_put_contents($this->dir.'/2026-10-04-dup.json', '{}');

        $this->assertSame('2026-10-04-dup-2', $writer->suggestId('2026-10-04', 'Dup'));
    }

    public function test_delete_removes_entry(): void
    {
        $writer = $this->writer();
        $id = $writer->save([
            'pr' => null,
            'title' => 'Goner',
            'date' => '2026-10-04',
            'category' => 'Added',
            'tags' => [],
            'summary' => '',
            'body' => '',
        ]);

        $this->assertTrue($writer->delete($id));
        $this->assertFalse($writer->delete($id));
        $this->assertNull((new ChangelogReader($this->dir))->find($id));
    }

    public function test_fetch_normalizes_pull_requests(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                [
                    'number' => 12,
                    'title' => 'Cool PR',
                    'body' => 'Does things.',
                    'state' => 'open',
                    'merged_at' => null,
                    'user' => ['login' => 'octo'],
                    'html_url' => 'https://github.com/o/r/pull/12',
                    'created_at' => '2026-10-01T10:00:00Z',
                ],
            ], 200),
        ]);

        $prs = (new GithubPullRequests)->fetch('o/r');

        $this->assertCount(1, $prs);
        $this->assertSame([
            'number' => 12,
            'title' => 'Cool PR',
            'body' => 'Does things.',
            'state' => 'open',
            'merged' => false,
            'author' => 'octo',
            'url' => 'https://github.com/o/r/pull/12',
            'created_at' => '2026-10-01',
        ], $prs[0]);
    }

    public function test_fetch_failure_mentions_token_on_rate_limit(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'API rate limit exceeded'], 403),
        ]);

        try {
            (new GithubPullRequests)->fetch('o/r');
            $this->fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('CHANGELOG_GITHUB_TOKEN', $e->getMessage());
        }
    }
}
