<?php

namespace Tests\Unit;

use App\Services\MigrationInspector;
use Tests\TestCase;

class MigrationInspectorTest extends TestCase
{
    protected function filter(array $rows, ?string $schemaArg, string $database): array
    {
        $inspector = new MigrationInspector;
        $method = new \ReflectionMethod($inspector, 'filterTableRows');
        $method->setAccessible(true);

        return $method->invoke($inspector, $rows, $schemaArg, $database);
    }

    public function test_filter_keeps_scoped_rows_with_schema(): void
    {
        // Laravel 12 shape: rows carry their schema.
        $rows = [
            ['name' => 'users', 'schema' => 'comics_db'],
            ['name' => 'other', 'schema' => 'someone_else'],
        ];

        $this->assertSame(['users'], $this->filter($rows, 'comics_db', 'comics_db'));
    }

    public function test_filter_passes_rows_without_schema_column(): void
    {
        // Laravel 11 shape: no schema key even though the grammar already
        // scoped the query — filtering on it would wrongly drop everything.
        $rows = [
            ['name' => 'users'],
            ['name' => 'comics'],
        ];

        $this->assertSame(
            ['users', 'comics'],
            $this->filter($rows, 'yiffbr85_comics', 'yiffbr85_comics')
        );
    }

    public function test_filter_without_schema_arg_keeps_everything(): void
    {
        $rows = [
            ['name' => 'users', 'schema' => 'whatever'],
        ];

        $this->assertSame(['users'], $this->filter($rows, null, 'comics_db'));
    }
}
