<?php

/**
 * One-shot EXPLAIN proof for README (§4.B.7).
 * Usage: php artisan tinker --execute="require 'scripts/explain_proof.php';"
 * or: php scripts/explain_proof.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'pgsql') {
    fwrite(STDERR, "This script requires DB_CONNECTION=pgsql\n");
    exit(1);
}

$watchlistSql = <<<'SQL'
SELECT id, username, status, last_refreshed_at
FROM profiles
WHERE status = 'fetched'
ORDER BY last_refreshed_at DESC NULLS LAST
LIMIT 20
SQL;

$timeseriesSql = <<<'SQL'
SELECT *
FROM profile_snapshots
WHERE profile_id = (SELECT id FROM profiles ORDER BY id LIMIT 1)
  AND captured_at >= NOW() - INTERVAL '30 days'
ORDER BY captured_at DESC
SQL;

function explain(string $label, string $sql): string
{
    $rows = DB::select('EXPLAIN ANALYZE '.$sql);
    $lines = array_map(fn ($r) => $r->{'QUERY PLAN'}, $rows);

    return "## {$label}\n\n```\n".implode("\n", $lines)."\n```\n";
}

DB::statement('DROP INDEX IF EXISTS profiles_status_last_refreshed_idx');

$before = explain('Watchlist query BEFORE composite index', $watchlistSql);

DB::statement('CREATE INDEX profiles_status_last_refreshed_idx ON profiles (status, last_refreshed_at DESC) INCLUDE (username)');

$after = explain('Watchlist query AFTER composite index', $watchlistSql);
$series = explain('30-day snapshots query', $timeseriesSql);

$out = "# EXPLAIN ANALYZE proofs\n\nGenerated: ".now()->toIso8601String()."\n\n".$before."\n".$after."\n".$series."\n";
$path = __DIR__.'/../docs/explain-analyze.md';
file_put_contents($path, $out);

echo $out;
echo "\nWrote {$path}\n";
