<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inspects migration files on disk vs. what actually exists in the database.
 *
 * Designed for shared hosting without console access: the admin panel can
 * show pending migrations, missing/extra tables, and missing columns,
 * then run `migrate --force` through the web UI.
 */
class MigrationInspector
{
    /**
     * Build the full comparison report.
     *
     * @return array{
     *   connection: string,
     *   database: string,
     *   driver: string,
     *   files: array<int, array{name: string, path: string, ran: bool, batch: int|null, tablesCreated: list<string>, tablesAltered: list<string>, tablesDropped: list<string>, columnsTouched: list<string>, columnsByTable: array<string, list<string>>}>,
     *   ran: array<string, int|null>,
     *   pending: list<string>,
     *   ranWithoutFile: list<string>,
     *   expectedTables: list<string>,
     *   actualTables: list<string>,
     *   missingTables: list<string>,
     *   extraTables: list<string>,
     *   tables: array<string, array{exists: bool, expected: bool, columns: list<array{name: string, type: string, nullable: bool}>, columnNames: list<string>, expectedColumns: list<string>, missingColumns: list<string>, rows: int|null}>,
     *   healthy: bool,
     * }
     */
    public function report(): array
    {
        $connection = $this->connectionName();
        $driver = config("database.connections.{$connection}.driver", $connection);
        $database = $this->resolveDatabaseName($connection);

        $files = $this->migrationFiles();
        $ran = $this->ranMigrations();

        foreach ($files as &$file) {
            $file['ran'] = array_key_exists($file['name'], $ran);
            $file['batch'] = $ran[$file['name']] ?? null;
        }
        unset($file);

        $pending = array_values(array_map(
            fn ($f) => $f['name'],
            array_filter($files, fn ($f) => ! $f['ran'])
        ));

        $fileNames = array_column($files, 'name');
        $ranWithoutFile = array_values(array_diff(array_keys($ran), $fileNames));

        $expectedTables = [];
        foreach ($files as $file) {
            foreach ($file['tablesCreated'] as $table) {
                if (! in_array($table, $expectedTables, true)) {
                    $expectedTables[] = $table;
                }
            }
        }
        sort($expectedTables);

        $actualTables = $this->actualTables();

        $missingTables = array_values(array_diff($expectedTables, $actualTables));
        $extraTables = array_values(array_diff($actualTables, $expectedTables, [$this->migrationsTable()]));

        // Map table => columns mentioned in migration files (best effort via regex).
        $expectedColumnsByTable = [];
        foreach ($files as $file) {
            foreach ($file['columnsByTable'] as $table => $columns) {
                foreach ($columns as $column) {
                    $expectedColumnsByTable[$table][] = $column;
                }
            }
        }
        foreach ($expectedColumnsByTable as $table => $columns) {
            $expectedColumnsByTable[$table] = array_values(array_unique($columns));
        }

        $tables = [];
        foreach (array_unique(array_merge($expectedTables, $actualTables)) as $table) {
            if ($table === $this->migrationsTable()) {
                continue;
            }
            $exists = in_array($table, $actualTables, true);
            $columns = $exists ? $this->tableColumns($table) : [];
            $columnNames = array_column($columns, 'name');
            $expectedColumns = $expectedColumnsByTable[$table] ?? [];
            $missingColumns = array_values(array_diff($expectedColumns, $columnNames));

            // Only flag missing columns for tables that exist; a missing table
            // is already reported via $missingTables (avoids double noise).
            if (! $exists) {
                $missingColumns = [];
            }

            $tables[$table] = [
                'exists' => $exists,
                'expected' => in_array($table, $expectedTables, true),
                'columns' => $columns,
                'columnNames' => $columnNames,
                'expectedColumns' => $expectedColumns,
                'missingColumns' => $missingColumns,
                'rows' => $exists ? $this->tableRowCount($table) : null,
            ];
        }
        ksort($tables);

        // Note: $extraTables is informational only (shared DBs often contain
        // unrelated tables) and does not affect $healthy.
        $healthy = $pending === []
            && $missingTables === []
            && $ranWithoutFile === []
            && collect($tables)->every(fn ($t) => $t['missingColumns'] === []);

        return [
            'connection' => $connection,
            'database' => $database,
            'driver' => $driver,
            'files' => $files,
            'ran' => $ran,
            'pending' => $pending,
            'ranWithoutFile' => $ranWithoutFile,
            'expectedTables' => $expectedTables,
            'actualTables' => $actualTables,
            'missingTables' => $missingTables,
            'extraTables' => $extraTables,
            'tables' => $tables,
            'healthy' => $healthy,
        ];
    }

    /**
     * @return list<array{name: string, path: string, ran: bool, batch: int|null, tablesCreated: list<string>, tablesAltered: list<string>, tablesDropped: list<string>, columnsTouched: list<string>, columnsByTable: array<string, list<string>>}>
     */
    public function migrationFiles(): array
    {
        $paths = glob(database_path('migrations/*.php')) ?: [];
        sort($paths);

        return array_map(function (string $path) {
            $parsed = $this->parseMigrationFile($path);

            return [
                'name' => basename($path, '.php'),
                'path' => $path,
                'ran' => false,
                'batch' => null,
                'tablesCreated' => $parsed['created'],
                'tablesAltered' => $parsed['altered'],
                'tablesDropped' => $parsed['dropped'],
                'columnsTouched' => $parsed['columns'],
                'columnsByTable' => $parsed['columnsByTable'],
            ];
        }, $paths);
    }

    /**
     * @return array<string, int|null> migration name => batch
     */
    public function ranMigrations(): array
    {
        try {
            $rows = DB::connection($this->connectionName())->table($this->migrationsTable())->orderBy('migration')->get(['migration', 'batch']);
        } catch (\Throwable) {
            // Fresh database without the migrations table yet.
            return [];
        }

        $ran = [];
        foreach ($rows as $row) {
            $ran[$row->migration] = (int) $row->batch;
        }

        return $ran;
    }

    /** @return list<string> */
    public function actualTables(): array
    {
        $connection = $this->connectionName();
        $database = $this->resolveDatabaseName($connection);
        $driver = config("database.connections.{$connection}.driver", $connection);

        // IMPORTANT: with no $schema argument MySQL compiles
        // `table_schema NOT IN (system schemas)`, i.e. every user database
        // on the whole server. Scope it to this project's database.
        $schemaArg = in_array($driver, ['mysql', 'mariadb'], true) && $database !== ''
            ? $database
            : null;

        try {
            $rows = Schema::connection($connection)->getTables($schemaArg);
            $tables = $this->filterTableRows($rows, $schemaArg, $database);
            if ($tables !== []) {
                return $this->finalizeTables($tables);
            }
            // An empty result may mean a genuinely empty database — or an
            // older framework whose query shape differs (e.g. Laravel 11
            // omits the schema column). Confirm with a portable raw query
            // before believing "empty".
        } catch (\Throwable) {
            // Fall through to the raw query below.
        }

        return $this->finalizeTables($this->rawTableListing($connection, $driver, $database));
    }

    /**
     * Keep rows belonging to this project's database. Older frameworks
     * omit the schema column even though their grammar already scopes the
     * query — so only filter when the row actually carries a schema value.
     *
     * @param list<array{name: string, schema?: string|null}> $rows
     * @return list<string>
     */
    protected function filterTableRows(array $rows, ?string $schemaArg, string $database): array
    {
        $tables = [];
        foreach ($rows as $row) {
            $rowSchema = $row['schema'] ?? null;
            if ($schemaArg !== null && $rowSchema !== null && $rowSchema !== $database) {
                continue;
            }
            $tables[] = $row['name'];
        }

        return array_values(array_unique($tables));
    }

    /**
     * Portable fallback listing that does not depend on the framework's
     * schema-inspection API shape (verified against MySQL 5.7 / Laravel 11).
     *
     * @return list<string>
     */
    protected function rawTableListing(string $connection, string $driver, string $database): array
    {
        try {
            $db = DB::connection($connection);
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $rows = $db->select(
                    "select table_name as name from information_schema.tables ".
                    "where table_schema = ? and table_type in ('BASE TABLE', 'SYSTEM VERSIONED') ".
                    'order by table_name',
                    [$database]
                );
                $tables = array_map(fn ($r) => is_array($r) ? $r['name'] : $r->name, $rows);
            } elseif ($driver === 'sqlite') {
                $rows = $db->select(
                    "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
                );
                $tables = array_map(fn ($r) => is_array($r) ? $r['name'] : $r->name, $rows);
            } elseif ($driver === 'pgsql') {
                $rows = $db->select(
                    "select tablename as name from pg_tables where schemaname not in ('pg_catalog', 'information_schema') order by tablename"
                );
                $tables = array_map(fn ($r) => is_array($r) ? $r['name'] : $r->tablename, $rows);
            } elseif ($driver === 'sqlsrv') {
                $rows = $db->select('select name from sys.tables order by name');
                $tables = array_map(fn ($r) => is_array($r) ? $r['name'] : $r->name, $rows);
            } else {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique($tables));
    }

    /** @param list<string> $tables @return list<string> */
    protected function finalizeTables(array $tables): array
    {
        // Exclude SQLite internals.
        $tables = array_values(array_unique(array_diff($tables, ['sqlite_sequence'])));
        sort($tables);

        return $tables;
    }

    /**
     * Parse a migration file for Schema::create/table/drop + $table->type('column').
     *
     * Columns are attributed per Schema block (not per file) so a file that
     * creates two tables (e.g. `cache` + `cache_locks`) does not cross-pollute
     * their expected column lists.
     *
     * @return array{created: list<string>, altered: list<string>, dropped: list<string>, columns: list<string>, columnsByTable: array<string, list<string>>}
     */
    public function parseMigrationFile(string $path): array
    {
        $code = @file_get_contents($path) ?: '';

        $created = $this->matchAll('/Schema\s*::\s*create\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $code);
        $altered = $this->matchAll('/Schema\s*::\s*table\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $code);
        $dropped = $this->matchAll('/Schema\s*::\s*dropIfExists\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $code);

        $columnsByTable = $this->columnsBySchemaBlock($code);

        $columns = [];
        if ($columnsByTable !== []) {
            $columns = array_values(array_unique(array_merge(...array_values($columnsByTable))));
        }

        // A drop in down() should not cancel a create in up(); keep both lists,
        // the report treats `created` as expected tables.
        return [
            'created' => array_values(array_unique($created)),
            'altered' => array_values(array_unique($altered)),
            'dropped' => array_values(array_unique($dropped)),
            'columns' => $columns,
            'columnsByTable' => $columnsByTable,
        ];
    }

    /**
     * Slice the file at each Schema::create/table block and extract
     * $table->type('column') calls from that block's slice only.
     *
     * @return array<string, list<string>>
     */
    protected function columnsBySchemaBlock(string $code): array
    {
        preg_match_all(
            '/Schema\s*::\s*(create|table)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            $code,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $blocks = [];
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $table = $matches[2][$i][0];
            $start = $matches[0][$i][1];
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($code);
            $slice = substr($code, $start, $end - $start);

            // $table->string('name'), $table->foreignId('user_id'); skips timestamps() (no column arg).
            $columns = $this->matchAll(
                '/\$\w+\s*->\s*(?:string|char|text|mediumText|longText|integer|tinyInteger|smallInteger|mediumInteger|bigInteger|unsignedBigInteger|unsignedInteger|unsignedSmallInteger|unsignedTinyInteger|id|foreignId|boolean|float|double|decimal|date|dateTime|timestamp|timestamps|softDeletes|rememberToken|time|year|binary|json|uuid|ulid|ipAddress|macAddress|enum|set)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
                $slice
            );

            // renameColumn('old', 'new') — both names are expected to exist at some point.
            $renamed = $this->matchAll('/->\s*renameColumn\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $slice);
            if (preg_match_all('/->\s*renameColumn\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $slice, $rm)) {
                $renamed = array_merge($rm[1], $rm[2]);
            }

            // dropColumn('foo') removes a column — never expected.
            $droppedCols = [];
            if (preg_match_all('/->\s*dropColumn\s*\(\s*(?:\[([^\]]+)\]|[\'"]([^\'"]+)[\'"])/i', $slice, $dm)) {
                foreach ($dm[1] as $list) {
                    if ($list !== '') {
                        $droppedCols = array_merge($droppedCols, $this->matchAll('/[\'"]([^\'"]+)[\'"]/', $list));
                    }
                }
                $droppedCols = array_merge($droppedCols, array_filter($dm[2]));
            }

            $columns = array_values(array_diff(array_unique(array_merge($columns, $renamed)), $droppedCols));

            if (! isset($blocks[$table])) {
                $blocks[$table] = [];
            }
            $blocks[$table] = array_values(array_unique(array_merge($blocks[$table], $columns)));
        }

        return $blocks;
    }

    /** @return list<array{name: string, type: string, nullable: bool}> */
    protected function tableColumns(string $table): array
    {
        try {
            // Plain table name → grammar scopes columns to the current
            // database via `schema()`, so this is already project-scoped.
            $columns = Schema::connection($this->connectionName())->getColumns($table);
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn ($c) => [
            'name' => $c['name'],
            'type' => $c['type_name'] ?? ($c['type'] ?? '?'),
            'nullable' => (bool) ($c['nullable'] ?? false),
        ], $columns);
    }

    protected function tableRowCount(string $table): ?int
    {
        try {
            return DB::connection($this->connectionName())->table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function connectionName(): string
    {
        return (string) config('database.default');
    }

    protected function migrationsTable(): string
    {
        return config('database.migrations.table', 'migrations');
    }

    protected function resolveDatabaseName(string $connection): string
    {
        try {
            return (string) DB::connection($connection)->getDatabaseName();
        } catch (\Throwable) {
            return (string) config("database.connections.{$connection}.database", '');
        }
    }

    /** @return list<string> */
    protected function matchAll(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);

        return $matches[1] ?? [];
    }
}
