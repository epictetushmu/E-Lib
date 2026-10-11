<?php

namespace App\Services;

use App\Includes\Environment;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;

/**
 * Book details (description, year, ISBN...) to pre-fill the upload/edit forms: Google Books first (better
 * descriptions), Open Library for whatever it leaves empty (no key, and up when Google's API isn't). Only the
 * ISBN or title/author is sent, never book text. Best effort: failures and misses are null.
 *
 * @phpstan-type Details array{title: string, author: string, year: string, isbn: string, description: string, categories: list<string>}
 */
class BookMetadataService
{
    private ?ClientInterface $http = null;

    /** @return Details|null */
    public function lookup(string $isbn, string $title, string $author): ?array
    {
        $isbn = preg_replace('/[^0-9X]/i', '', $isbn) ?? '';
        $title = trim($title);
        // The placeholder BookService stores for a missing author would filter out every real match.
        $author = trim($author) === BookService::DEFAULT_AUTHOR ? '' : trim($author);
        if ($isbn === '' && $title === '') {
            return null;
        }

        $found = $this->google($isbn, $title, $author);
        if (($found['description'] ?? '') !== '') {
            return $found;
        }
        $extra = $this->openLibrary($isbn, $title, $author);
        if ($found === null || $extra === null) {
            return $found ?? $extra;
        }
        foreach ($found as $field => $value) {
            if ($value === '' || $value === []) {
                $found[$field] = $extra[$field];
            }
        }
        return $found;
    }

    /** @return Details|null */
    private function google(string $isbn, string $title, string $author): ?array
    {
        $phrase = fn (string $s) => '"' . str_replace('"', '', $s) . '"';
        // ISBN first (exact); title/author when it finds nothing or fails — Google's isbn: search
        // intermittently answers 503 while title search keeps working.
        $queries = array_filter([
            $isbn !== '' ? "isbn:$isbn" : '',
            $title !== '' ? 'intitle:' . $phrase($title) . ($author !== '' ? ' inauthor:' . $phrase($author) : '') : '',
        ]);
        foreach ($queries as $q) {
            $info = $this->getJson('Google Books', 'https://www.googleapis.com/books/v1/volumes', array_filter([
                'q' => $q,
                'maxResults' => 1,
                'printType' => 'books',
                'key' => Environment::get('GOOGLE_BOOKS_API_KEY', ''), // optional; keyless shares a per-IP quota
            ]))['items'][0]['volumeInfo'] ?? null;
            if (is_array($info)) {
                $isbns = array_column($info['industryIdentifiers'] ?? [], 'identifier', 'type');
                return [
                    'title' => trim(($info['title'] ?? '') . (isset($info['subtitle']) ? ': ' . $info['subtitle'] : '')),
                    'author' => implode(', ', $info['authors'] ?? []),
                    'year' => substr((string) ($info['publishedDate'] ?? ''), 0, 4),
                    'isbn' => $isbns['ISBN_13'] ?? $isbns['ISBN_10'] ?? $isbn,
                    // Descriptions sometimes carry <p>/<b> markup; the app shows plain text.
                    'description' => trim(html_entity_decode(strip_tags((string) ($info['description'] ?? '')))),
                    'categories' => self::cleanSubjects($info['categories'] ?? []),
                ];
            }
        }
        return null;
    }

    /** @return Details|null */
    private function openLibrary(string $isbn, string $title, string $author): ?array
    {
        $queries = array_filter([
            $isbn !== '' ? ['isbn' => $isbn] : null,
            $title !== '' ? array_filter(['title' => $title, 'author' => $author]) : null,
        ]);
        $fields = 'key,title,author_name,first_publish_year,isbn,subject';
        foreach ($queries as $q) {
            $search = $this->getJson('Open Library', 'https://openlibrary.org/search.json', $q + ['fields' => $fields, 'limit' => 1]);
            $doc = $search['docs'][0] ?? null;
            if (!is_array($doc)) {
                continue;
            }
            // Descriptions aren't in search results: the edition (our ISBN) may have one, else its work.
            $text = fn ($d) => trim((string) (is_array($d) ? ($d['value'] ?? '') : $d));
            $edition = $isbn !== '' ? $this->getJson('Open Library', "https://openlibrary.org/isbn/$isbn.json", []) : [];
            $description = $text($edition['description'] ?? '');
            $workKey = $edition['works'][0]['key'] ?? $doc['key'] ?? null;
            if ($description === '' && is_string($workKey)) {
                $description = $text($this->getJson('Open Library', "https://openlibrary.org$workKey.json", [])['description'] ?? '');
            }
            $isbns = array_filter($doc['isbn'] ?? [], fn ($i) => is_string($i) && strlen($i) === 13);
            return [
                'title' => (string) ($doc['title'] ?? ''),
                'author' => implode(', ', $doc['author_name'] ?? []),
                'year' => (string) ($doc['first_publish_year'] ?? ''),
                'isbn' => $isbn !== '' ? $isbn : (string) (reset($isbns) ?: ''),
                'description' => $description,
                'categories' => self::cleanSubjects($doc['subject'] ?? []),
            ];
        }
        return null;
    }

    /** Open Library's own flags, not subjects. */
    private const NOT_SUBJECTS = ['accessible book', 'protected daisy', 'in library', 'lending library', 'large type books'];

    /**
     * Subjects usable as catalog categories: no classification codes ("Qa76.6 .i5858", "005.1"), tags
     * ("open_syllabus_project", "nyt:...", "pdf.yt") or library flags; "Programming -- Textbooks" → "Programming".
     *
     * @param array<mixed> $subjects
     * @return list<string>
     */
    public static function cleanSubjects(array $subjects): array
    {
        $clean = [];
        foreach ($subjects as $subject) {
            if (!is_string($subject)) {
                continue;
            }
            $subject = trim(explode(' -- ', $subject)[0]);
            $key = mb_strtolower($subject);
            if (
                preg_match('/^\p{Lu}[^\d_:.]{2,40}$/u', $subject) === 1
                && !in_array($key, self::NOT_SUBJECTS, true)
                && !isset($clean[$key])
            ) {
                $clean[$key] = $subject;
            }
        }
        return array_values($clean);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function getJson(string $source, string $url, array $query): array
    {
        try {
            $res = $this->http()->request('GET', $url, ['query' => $query]);
        } catch (TransferException $e) {
            // Status code only: the exception message contains the request URL, API key included.
            error_log("$source lookup failed (code {$e->getCode()})");
            return [];
        }
        $data = json_decode((string) $res->getBody(), true);
        return is_array($data) ? $data : [];
    }

    private function http(): ClientInterface
    {
        return $this->http ??= new Client([
            'timeout' => 5,
            'headers' => ['User-Agent' => 'E-Lib (HMU digital library)'],
        ]);
    }
}
