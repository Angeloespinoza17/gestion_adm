<?php

namespace App\Services\Library;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenLibraryService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term, int $limit = 8): array
    {
        $term = trim($term);
        $limit = max(1, min($limit, 12));

        if (mb_strlen($term) < 3) {
            throw ValidationException::withMessages([
                'q' => 'Ingresa al menos 3 caracteres o un ISBN válido.',
            ]);
        }

        $cacheKey = 'open-library:'.sha1(mb_strtolower($term).':'.$limit);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($term, $limit) {
            try {
                $response = Http::acceptJson()
                    ->withUserAgent($this->userAgent())
                    ->timeout(8)
                    ->retry(2, 250, throw: false)
                    ->get(rtrim((string) config('services.open_library.base_url'), '/').'/search.json', [
                        'q' => $this->query($term),
                        'lang' => 'es',
                        'limit' => $limit,
                        'fields' => implode(',', [
                            'key',
                            'title',
                            'subtitle',
                            'author_name',
                            'first_publish_year',
                            'publish_year',
                            'isbn',
                            'publisher',
                            'language',
                            'number_of_pages_median',
                            'cover_i',
                            'subject',
                            'edition_key',
                        ]),
                    ]);
            } catch (ConnectionException) {
                throw ValidationException::withMessages([
                    'open_library' => 'Open Library no está disponible en este momento. Puedes completar la ficha manualmente.',
                ]);
            }

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'open_library' => 'Open Library respondió con un error temporal. Intenta nuevamente o completa la ficha manualmente.',
                ]);
            }

            return collect($response->json('docs', []))
                ->map(fn (array $book) => $this->normalize($book))
                ->filter(fn (array $book) => filled($book['title']))
                ->values()
                ->all();
        });
    }

    private function query(string $term): string
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', $term);

        if (in_array(strlen((string) $isbn), [10, 13], true)) {
            return 'isbn:'.strtoupper((string) $isbn);
        }

        return $term;
    }

    /**
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    private function normalize(array $book): array
    {
        $workKey = (string) ($book['key'] ?? '');
        $editionKey = Arr::first($book['edition_key'] ?? []);
        $coverId = $book['cover_i'] ?? null;
        $authors = array_values(array_filter($book['author_name'] ?? []));
        $publishers = array_values(array_filter($book['publisher'] ?? []));
        $isbns = array_values(array_filter($book['isbn'] ?? []));
        $subjects = array_slice(array_values(array_filter($book['subject'] ?? [])), 0, 12);
        $years = array_map('intval', array_filter($book['publish_year'] ?? []));

        return [
            'title' => trim((string) ($book['title'] ?? '')),
            'subtitle' => trim((string) ($book['subtitle'] ?? '')) ?: null,
            'main_author' => $authors[0] ?? 'Autor no informado',
            'secondary_authors' => array_slice($authors, 1),
            'publisher' => $publishers[0] ?? null,
            'publication_year' => $book['first_publish_year'] ?? ($years ? min($years) : null),
            'isbn' => $this->preferredIsbn($isbns),
            'language' => $this->languageLabel(Arr::first($book['language'] ?? [])),
            'page_count' => $book['number_of_pages_median'] ?? null,
            'keywords' => $subjects,
            'cover_image_url' => $coverId
                ? sprintf('https://covers.openlibrary.org/b/id/%s-L.jpg', $coverId)
                : null,
            'open_library_work_key' => $workKey ?: null,
            'open_library_edition_key' => $editionKey ? '/books/'.ltrim((string) $editionKey, '/') : null,
            'open_library_cover_id' => $coverId ? (int) $coverId : null,
            'source_url' => $workKey ? 'https://openlibrary.org'.$workKey : null,
            'source_metadata' => [
                'provider' => 'open_library',
                'retrieved_at' => now()->toIso8601String(),
                'work_key' => $workKey ?: null,
                'edition_key' => $editionKey ?: null,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $isbns
     */
    private function preferredIsbn(array $isbns): ?string
    {
        foreach ($isbns as $isbn) {
            $normalized = preg_replace('/[^0-9Xx]/', '', $isbn);
            if (strlen((string) $normalized) === 13) {
                return strtoupper((string) $normalized);
            }
        }

        return isset($isbns[0]) ? strtoupper((string) $isbns[0]) : null;
    }

    private function languageLabel(?string $language): ?string
    {
        return match ($language) {
            'spa' => 'Español',
            'eng' => 'Inglés',
            'fre', 'fra' => 'Francés',
            'por' => 'Portugués',
            'ger', 'deu' => 'Alemán',
            default => $language ? strtoupper($language) : null,
        };
    }

    private function userAgent(): string
    {
        $name = (string) config('services.open_library.application_name', config('app.name', 'School Library'));
        $contact = (string) config('services.open_library.contact', 'biblioteca@example.invalid');

        return sprintf('%s (%s)', $name, $contact);
    }
}
