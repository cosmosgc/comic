<?php

namespace Tests\Unit;

use App\Services\EhTitleCleaner;
use Tests\TestCase;

class EhTitleCleanerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The cleaner only reads config; never touch the dev database.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_removes_configured_snippets_ignoring_case(): void
    {
        config(['ehimport.title_cleanup' => ['[Portuguese-BR]', '[PT-BR]']]);

        $cleaner = new EhTitleCleaner;

        $this->assertSame('My Comic Vol. 1', $cleaner->clean('My Comic [Portuguese-BR] Vol. 1'));
        $this->assertSame('My Comic Vol. 1', $cleaner->clean('My Comic [portuguese-br] Vol. 1'));
        $this->assertSame('My Comic', $cleaner->clean('My Comic [PT-BR]'));
    }

    public function test_accepts_a_newline_separated_setting(): void
    {
        config(['ehimport.title_cleanup' => "[Portuguese-BR]\n[Português]\n\n"]);

        $this->assertSame(
            'Comic Name',
            (new EhTitleCleaner)->clean('Comic Name [Português] [Portuguese-BR]')
        );
    }

    public function test_collapses_spaces_and_dangling_separators(): void
    {
        config(['ehimport.title_cleanup' => ['[PT-BR]']]);

        $cleaner = new EhTitleCleaner;

        $this->assertSame('Long Title', $cleaner->clean('  Long   Title  [PT-BR]  '));
        $this->assertSame('Title', $cleaner->clean('Title - [PT-BR]'));
        $this->assertSame('Title', $cleaner->clean('[PT-BR] | Title'));
    }

    public function test_keeps_the_title_when_cleaning_would_erase_it(): void
    {
        config(['ehimport.title_cleanup' => ['Title']]);

        $this->assertSame('Title', (new EhTitleCleaner)->clean('Title'));
    }

    public function test_does_nothing_without_rules(): void
    {
        config(['ehimport.title_cleanup' => []]);

        $this->assertSame('Unchanged [PT-BR]', (new EhTitleCleaner)->clean('Unchanged [PT-BR]'));
    }
}
