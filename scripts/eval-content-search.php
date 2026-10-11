#!/usr/bin/env php
<?php

/**
 * CLI: measure content-search quality against labelled queries, through the real search path
 * (Atlas $search + $vectorSearch + the relevance gate), so it shows what users get.
 *
 * Read-only — safe against any database, including production, to tune the thresholds on the real
 * library. Needs Atlas (or the atlas-local dev image), indexed books and a running embedder, so it
 * can't run in CI (plain mongo has no Atlas Search).
 *
 * Usage: php scripts/eval-content-search.php [queries.json]   (default: tests/Eval/content-search-queries.json)
 *   Try other thresholds without editing .env: SEMANTIC_MIN_SCORE=0.76 SEMANTIC_MARGIN=0.02 php scripts/...
 *
 * queries.json: [{"query": "...", "expect": "part of the title of the book that should come first" | null}]
 *   null = nothing in the library answers it, so no results is the right answer.
 *
 * Metrics, over queries with an expected book:
 *   hit@1     — expected book is first
 *   MRR       — mean of 1/rank of the expected book (0 if missing)
 *   precision — share of returned books that are the expected one
 * and, over null queries, how many still returned something (should be 0).
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\Includes\Environment;
use App\Services\ContentSearchService;

Environment::load($root . '/.env');
// A slow first embedding (model still loading) would silently fall back to keyword-only and skew the numbers.
putenv('EMBEDDING_TIMEOUT=' . (getenv('EMBEDDING_TIMEOUT') ?: 60));

$file = $argv[1] ?? $root . '/tests/Eval/content-search-queries.json';
$cases = json_decode((string) file_get_contents($file), true);
if (!is_array($cases)) {
    fwrite(STDERR, "Cannot read queries from $file\n");
    exit(1);
}

$service = new ContentSearchService();
printf(
    "SEMANTIC_MIN_SCORE=%s SEMANTIC_MARGIN=%s model=%s\n\n",
    Environment::get('SEMANTIC_MIN_SCORE', '0.75 (default)'),
    Environment::get('SEMANTIC_MARGIN', '0.03 (default)'),
    ContentSearchService::model()
);

$labelled = $hits = $falsePositives = $unanswerable = 0;
$reciprocalRanks = $precision = 0.0;
foreach ($cases as $case) {
    $titles = array_map(fn (array $r) => (string) ($r['book']['title'] ?? '?'), $service->search($case['query'], false));
    $expect = $case['expect'];

    if ($expect === null) {
        $unanswerable++;
        $falsePositives += $titles === [] ? 0 : 1;
        $verdict = $titles === [] ? 'ok  ' : 'FAIL';
    } else {
        $labelled++;
        $rank = null;
        foreach ($titles as $i => $title) {
            if (mb_stripos($title, $expect) !== false) {
                $rank = $i + 1;
                break;
            }
        }
        $hits += $rank === 1 ? 1 : 0;
        $reciprocalRanks += $rank === null ? 0 : 1 / $rank;
        $precision += $rank === null ? 0 : 1 / count($titles);
        $verdict = $rank === 1 && count($titles) === 1 ? 'ok  ' : ($rank === 1 ? 'meh ' : 'FAIL');
    }

    $query = mb_strimwidth($case['query'], 0, 50, '…');
    printf(
        "%s %s -> %s\n",
        $verdict,
        $query . str_repeat(' ', 50 - mb_strwidth($query)),
        $titles === [] ? '(no results)' : implode(' | ', array_map(fn ($t) => mb_strimwidth($t, 0, 30, '…'), $titles))
    );
}

printf(
    "\nhit@1 %d/%d   MRR %.2f   precision %.2f   unanswerable queries with results %d/%d\n",
    $hits,
    $labelled,
    $labelled ? $reciprocalRanks / $labelled : 0,
    $labelled ? $precision / $labelled : 0,
    $falsePositives,
    $unanswerable
);
