#!/usr/bin/env php
<?php

/**
 * CLI: seeds a handful of sample books (with generated dummy PDFs + real
 * thumbnails) so the app has visible content in local/dev use.
 *
 * Goes through the same layers the app itself uses — App\Services\BookService
 * and App\Helpers\FileHelper — rather than writing to MongoDB directly, so the
 * seeded data is exactly what a real upload via /add-book would produce
 * (same validation, same file storage path, same thumbnail pipeline).
 *
 * Idempotent — skips any title that already exists (BookService::getBookByTitle()),
 * so it's safe to re-run.
 *
 * Usage: php scripts/seed-sample-books.php
 *    or: composer db:seed
 */

require_once __DIR__ . '/cli-output.php';

/**
 * A minimal-but-valid single-page PDF: a colored cover with the title/author
 * drawn as text, so the real thumbnail pipeline (Imagick/pdftoppm) has
 * something to render instead of falling back to a placeholder image.
 */
function buildDummyPdf(string $title, string $author): string
{
    $escape = fn (string $s): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);

    $r = mt_rand(20, 70) / 100;
    $g = mt_rand(20, 70) / 100;
    $b = mt_rand(40, 90) / 100;

    $content = sprintf(
        "q\n%.2f %.2f %.2f rg\n0 0 420 600 re f\nQ\n" .
            "BT /F1 22 Tf 1 0 0 1 30 520 Tm 1 1 1 rg (%s) Tj ET\n" .
            "BT /F1 14 Tf 1 0 0 1 30 480 Tm 1 1 1 rg (by %s) Tj ET\n" .
            "BT /F1 10 Tf 1 0 0 1 30 60 Tm 1 1 1 rg (Dummy content - seeded for local development) Tj ET\n",
        $r,
        $g,
        $b,
        $escape($title),
        $escape($author),
    );

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 600] " .
        "/Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
    $objects[4] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
    $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

    $pdf = "%PDF-1.4\n";
    foreach ($objects as $num => $body) {
        $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
    }
    $pdf .= "trailer\n<< /Root 1 0 R >>\n";

    return $pdf;
}

/** @return array{path:string,type:string,extension:string} */
function storeDummyPdf(string $projectRoot, string $title, string $author): array
{
    $uploadFileDir = $projectRoot . '/public/assets/uploads/documents/';
    if (!is_dir($uploadFileDir) && !@mkdir($uploadFileDir, 0777, true)) {
        throw new RuntimeException("Failed to create directory: {$uploadFileDir}");
    }

    $fileName = uniqid('doc_') . '.pdf';
    $destPath = $uploadFileDir . $fileName;

    if (file_put_contents($destPath, buildDummyPdf($title, $author)) === false) {
        throw new RuntimeException("Failed to write dummy PDF: {$destPath}");
    }

    return [
        'path' => '/assets/uploads/documents/' . $fileName,
        'type' => 'pdf',
        'extension' => 'pdf',
    ];
}

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

App\Includes\Environment::load($root . '/.env');

echo YELLOW . "E-Lib Sample Book Seeding\n" . RESET;
echo "==========================\n\n";

echo "Connecting...\n";
try {
    App\Repository\DatabaseRepository::getInstance();
    success("Connected.");
} catch (Throwable $e) {
    fail("Connection failed: " . $e->getMessage());
    fail("Run 'php scripts/mongo-ping.php' to diagnose.");
    exit(1);
}
echo "\n";

$bookService = new App\Services\BookService();
$booksModel = new App\Models\Books();

$sampleBooks = [
    [
        'title' => 'Foundations of Computer Science',
        'author' => 'A. Turing (dummy)',
        'year' => '2021',
        'description' => 'A dummy sample entry covering the theory of computation, algorithms, and data structures.',
        'categories' => ['Computer Science', 'Programming'],
        'isbn' => '',
        'status' => 'public',
        'featured' => true,
        'downloadable' => true,
    ],
    [
        'title' => 'Circuit Design Essentials',
        'author' => 'M. Faraday (dummy)',
        'year' => '2019',
        'description' => 'A dummy sample entry introducing analog and digital circuit design fundamentals.',
        'categories' => ['Electronics'],
        'isbn' => '',
        'status' => 'public',
        'featured' => true,
        'downloadable' => true,
    ],
    [
        'title' => 'Applied Calculus for Engineers',
        'author' => 'I. Newton (dummy)',
        'year' => '2020',
        'description' => 'A dummy sample entry on differential and integral calculus for engineering applications.',
        'categories' => ['Mathematics'],
        'isbn' => '',
        'status' => 'public',
        'featured' => false,
        'downloadable' => true,
    ],
    [
        'title' => 'Modern Computer Networking',
        'author' => 'V. Cerf (dummy)',
        'year' => '2022',
        'description' => 'A dummy sample entry covering network protocols, routing, and the modern internet stack.',
        'categories' => ['Networking', 'Computer Science'],
        'isbn' => '',
        'status' => 'public',
        'featured' => true,
        'downloadable' => true,
    ],
    [
        'title' => 'Introduction to Robotics Systems',
        'author' => 'J. Engelberger (dummy)',
        'year' => '2018',
        'description' => 'A dummy sample entry on robotic kinematics, actuators, and control systems. Left as a draft on purpose.',
        'categories' => ['Robotics'],
        'isbn' => '',
        'status' => 'draft',
        'featured' => false,
        'downloadable' => true,
    ],
    [
        'title' => 'Wireless Telecommunications Basics',
        'author' => 'G. Marconi (dummy)',
        'year' => '2017',
        'description' => 'A dummy sample entry introducing radio propagation, modulation, and wireless standards. ' .
            'Download disabled on purpose.',
        'categories' => ['Telecommunications', 'Physics'],
        'isbn' => '',
        'status' => 'public',
        'featured' => false,
        'downloadable' => false,
    ],
];

echo "Seeding " . count($sampleBooks) . " sample books...\n";

$created = 0;
$skipped = 0;

foreach ($sampleBooks as $book) {
    $title = $book['title'];

    try {
        if ($bookService->getBookByTitle($title)) {
            info("\"{$title}\" already exists — skipping.");
            $skipped++;
            continue;
        }

        $storedFile = storeDummyPdf($root, $title, $book['author']);

        $fileHelper = new App\Helpers\FileHelper($root . '/public' . $storedFile['path']);
        $thumbnailPath = $fileHelper->getThumbnail();

        $result = $bookService->addBook(
            $title,
            $book['author'],
            $book['year'],
            $book['description'],
            $book['categories'],
            $book['isbn'],
            $storedFile['path'],
            $thumbnailPath,
            $book['downloadable'],
            $storedFile['type'],
            $storedFile['extension'],
        );

        $id = $result['insertedId'] ?? null;
        if (!$id) {
            fail("\"{$title}\": insert did not return an id (" . ($result['error'] ?? 'unknown error') . ')');
            continue;
        }

        // BookService::addBook() always creates as draft/not-featured; flip to the
        // sample's intended status/featured flag now that the doc exists.
        $booksModel->updateById($id, [
            'status' => $book['status'],
            'featured' => $book['featured'],
        ]);

        success("Created \"{$title}\" ({$book['status']}" . ($book['featured'] ? ', featured' : '') . ") — id {$id}");
        $created++;
    } catch (Throwable $e) {
        fail("\"{$title}\": " . $e->getMessage());
    }
}

echo "\n" . GREEN . "Done. {$created} created, {$skipped} skipped (already existed).\n" . RESET;
exit(0);
