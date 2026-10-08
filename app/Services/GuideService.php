<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;

/**
 * The Help Centre: a step-by-step manual for the whole application.
 *
 * The content lives in resources/guide/{locale}/{category}.php as plain arrays
 * rather than in the database, for three reasons. It ships and is versioned with
 * the code that it documents, so an update that changes a screen changes its
 * instructions in the same commit. It cannot be half-deleted by accident, which
 * a CMS page can. And it needs structure a rich-text field cannot carry —
 * numbered steps, settings tables, callouts — so that one template renders every
 * article the same way instead of each page being hand-built.
 *
 * Pages an operator wants to edit themselves (About, Terms, policies) are CMS
 * pages; this is the manual.
 *
 * Translations are per-locale directories. Anything missing from the visitor's
 * language falls back to English, article by article, so a partial translation
 * is still useful and can never leave a blank page.
 */
final class GuideService
{
    /** The order categories appear in: a reader's path from first login to daily operation. */
    private const ORDER = [
        'getting-started',
        'selling',
        'buying',
        'orders-and-payments',
        'your-account',
        'operator-setup',
        'operator-running',
    ];

    private const FALLBACK_LOCALE = 'en';

    /**
     * Words carried along by a natural question ("how do I…", "it is not
     * working") that say nothing about the subject. Without this, searching
     * "auctions not closing" ranks every article whose text happens to contain
     * "not" — including, absurdly, the one about notifications.
     */
    private const STOP_WORDS = [
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true, 'at' => true,
        'be' => true, 'but' => true, 'by' => true, 'can' => true, 'do' => true, 'does' => true,
        'for' => true, 'from' => true, 'get' => true, 'how' => true, 'i' => true, 'if' => true,
        'in' => true, 'is' => true, 'it' => true, 'me' => true, 'my' => true, 'no' => true,
        'not' => true, 'of' => true, 'on' => true, 'or' => true, 'the' => true, 'their' => true,
        'they' => true, 'this' => true, 'to' => true, 'was' => true, 'what' => true,
        'when' => true, 'where' => true, 'which' => true, 'why' => true, 'will' => true,
        'with' => true, 'you' => true, 'your' => true,
    ];

    /** @var array<string, array<string, array>> Parsed categories, keyed by locale. */
    private static array $cache = [];

    /**
     * Every category, in reading order, each with its articles.
     *
     * @return list<array>
     */
    public static function categories(?string $locale = null): array
    {
        $locale ??= Lang::locale();

        if (isset(self::$cache[$locale])) {
            return array_values(self::$cache[$locale]);
        }

        $categories = [];
        foreach (self::ORDER as $slug) {
            $category = self::loadCategory($slug, $locale);
            if ($category !== null) {
                $categories[$slug] = $category;
            }
        }

        self::$cache[$locale] = $categories;
        return array_values($categories);
    }

    /** One category by slug, or null if there is no such category. */
    public static function category(string $slug, ?string $locale = null): ?array
    {
        self::categories($locale);
        return self::$cache[$locale ?? Lang::locale()][$slug] ?? null;
    }

    /**
     * One article, with the category it belongs to and its neighbours, so the
     * page can offer "previous" and "next" without the caller searching again.
     *
     * @return array{category: array, article: array, previous: ?array, next: ?array}|null
     */
    public static function article(string $categorySlug, string $articleSlug, ?string $locale = null): ?array
    {
        $category = self::category($categorySlug, $locale);
        if ($category === null) {
            return null;
        }

        $articles = $category['articles'];
        foreach ($articles as $index => $article) {
            if ($article['slug'] !== $articleSlug) {
                continue;
            }

            return [
                'category' => $category,
                'article' => $article,
                'previous' => self::neighbour($category, $articles, $index - 1),
                'next' => self::neighbour($category, $articles, $index + 1),
            ];
        }

        return null;
    }

    /**
     * Free-text search over titles, summaries and body text.
     *
     * Ranked so that a title match beats a summary match, which beats a mention
     * somewhere in the body — a reader searching "cron" wants the article called
     * "Set up the scheduler", not every article that happens to mention it.
     *
     * Most readers are traders, so on an otherwise equal score a trading article
     * is shown above a configuration one — a seller searching "auction" wants
     * "Run an auction", not "Auction defaults". Staff get the opposite, because
     * they are usually looking for the setting.
     *
     * @return list<array{category: array, article: array, score: int, excerpt: string}>
     */
    public static function search(
        string $query,
        ?string $locale = null,
        int $limit = 25,
        bool $preferOperator = false
    ): array {
        $needle = self::normalise($query);
        if ($needle === '') {
            return [];
        }

        $words = array_values(array_filter(
            explode(' ', $needle),
            static fn (string $w): bool => mb_strlen($w) > 1 && !isset(self::STOP_WORDS[$w])
        ));
        // A query made entirely of common words ("how do I…") still has to search
        // for something, so fall back to the words as typed.
        if ($words === []) {
            $words = array_values(array_filter(explode(' ', $needle), static fn (string $w): bool => $w !== ''));
        }

        $results = [];
        foreach (self::categories($locale) as $category) {
            foreach ($category['articles'] as $article) {
                $title = self::normalise($article['title']);
                $summary = self::normalise($article['summary'] ?? '');
                $keywords = self::normalise(implode(' ', $article['keywords'] ?? []));
                $body = self::normalise(self::plainText($article));

                $score = 0.0;
                foreach ($words as $word) {
                    $score += 100 * self::match($title, $word);
                    $score += 40 * self::match($keywords, $word);
                    $score += 25 * self::match($summary, $word);
                    $score += 5 * self::match($body, $word);
                }

                // The whole phrase appearing together is a stronger signal than
                // the same words scattered across a long article. Keywords count
                // here too, so a phrase like "auctions not closing" can be
                // attached to the one article that actually answers it.
                if (count($words) > 1
                    && str_contains($title . ' ' . $keywords . ' ' . $summary . ' ' . $body, $needle)
                ) {
                    $score += 60;
                }

                // A nudge, not a reordering. At 15 it can only reorder articles
                // already within 15 points of each other, which is less than a
                // single summary match (25) and far less than a title (100), so
                // a genuinely better answer still wins.
                if ($score > 0 && ($category['audience'] === 'operator') === $preferOperator) {
                    $score += 15;
                }

                if ($score > 0) {
                    $results[] = [
                        'category' => $category,
                        'article' => $article,
                        'score' => (int) round($score),
                        'excerpt' => self::excerpt($article, $words[0]),
                    ];
                }
            }
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    /** Total number of articles, for the index page's summary line. */
    public static function articleCount(?string $locale = null): int
    {
        $count = 0;
        foreach (self::categories($locale) as $category) {
            $count += count($category['articles']);
        }
        return $count;
    }

    /**
     * Which locales the guide has been translated into, as code => label, for
     * the notice shown when a reader's language has no translation yet.
     *
     * @return array<string, string>
     */
    public static function translatedLocales(): array
    {
        $available = [];
        foreach (Lang::SUPPORTED as $code => $label) {
            if (is_dir(ROOT_PATH . '/resources/guide/' . $code)) {
                $available[$code] = $label;
            }
        }
        return $available;
    }

    /** True when the guide is being read in a language it has not been translated into. */
    public static function isFallingBack(?string $locale = null): bool
    {
        $locale ??= Lang::locale();
        return $locale !== self::FALLBACK_LOCALE && !isset(self::translatedLocales()[$locale]);
    }

    private static function neighbour(array $category, array $articles, int $index): ?array
    {
        if (!isset($articles[$index])) {
            return null;
        }
        return [
            'category_slug' => $category['slug'],
            'slug' => $articles[$index]['slug'],
            'title' => $articles[$index]['title'],
        ];
    }

    /**
     * Load one category file, preferring the reader's language and falling back
     * to English. A malformed or missing file is skipped rather than fatal: a
     * broken guide file must not take down the page that links to it.
     */
    private static function loadCategory(string $slug, string $locale): ?array
    {
        $data = self::requireFile($slug, $locale) ?? self::requireFile($slug, self::FALLBACK_LOCALE);
        if ($data === null || !isset($data['title'], $data['articles']) || !is_array($data['articles'])) {
            return null;
        }

        $articles = [];
        foreach ($data['articles'] as $article) {
            if (isset($article['slug'], $article['title'])) {
                $article['body'] ??= [];
                $articles[] = $article;
            }
        }

        if ($articles === []) {
            return null;
        }

        return [
            'slug' => $slug,
            'title' => (string) $data['title'],
            'summary' => (string) ($data['summary'] ?? ''),
            'icon' => (string) ($data['icon'] ?? 'bi-book'),
            'audience' => (string) ($data['audience'] ?? 'everyone'),
            'articles' => $articles,
        ];
    }

    private static function requireFile(string $slug, string $locale): ?array
    {
        // Both parts come from a fixed list, but they are still spelled out as
        // path segments, so refuse anything that is not a plain slug.
        if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1 || preg_match('/^[a-z_]+$/', $locale) !== 1) {
            return null;
        }

        $file = ROOT_PATH . '/resources/guide/' . $locale . '/' . $slug . '.php';
        if (!is_file($file)) {
            return null;
        }

        $data = require $file;
        return is_array($data) ? $data : null;
    }

    /** Everything a reader could search for in one article, flattened to text. */
    private static function plainText(array $article): string
    {
        $parts = [$article['title'], $article['summary'] ?? ''];

        foreach ($article['body'] as $block) {
            $parts[] = (string) ($block['text'] ?? '');

            foreach (($block['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $parts[] = (string) ($item['title'] ?? '');
                    $parts[] = (string) ($item['text'] ?? '');
                } else {
                    $parts[] = (string) $item;
                }
            }

            foreach (($block['rows'] ?? []) as $row) {
                $parts[] = implode(' ', array_map(static fn ($cell): string => (string) $cell, (array) $row));
            }

            $parts[] = implode(' ', array_map(static fn ($cell): string => (string) $cell, (array) ($block['head'] ?? [])));
        }

        return implode(' ', array_filter($parts));
    }

    /** A short run of text around the first match, to show under a search hit. */
    private static function excerpt(array $article, string $word): string
    {
        $summary = trim((string) ($article['summary'] ?? ''));
        $text = $summary !== '' ? $summary : self::plainText($article);
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if (mb_strlen($text) <= 180) {
            return $text;
        }

        $position = mb_stripos($text, $word);
        if ($position === false) {
            return mb_substr($text, 0, 177) . '…';
        }

        $start = max(0, $position - 60);
        return ($start > 0 ? '…' : '') . mb_substr($text, $start, 180) . '…';
    }

    /**
     * How well one word matches a field: 1 for the whole word, 0.4 for the start
     * of a longer word, 0 otherwise.
     *
     * Matching on a bare substring is what made "not" score against
     * "notifications". Anchoring to a word boundary fixes that while the partial
     * credit still lets "auction" find "auctions" and "invoice" find "invoices".
     */
    private static function match(string $haystack, string $word): float
    {
        if ($haystack === '' || $word === '') {
            return 0.0;
        }

        $padded = ' ' . $haystack . ' ';
        if (str_contains($padded, ' ' . $word . ' ')) {
            return 1.0;
        }
        return str_contains($padded, ' ' . $word) ? 0.4 : 0.0;
    }

    private static function normalise(string $text): string
    {
        $text = mb_strtolower(strip_tags($text));
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '');
    }
}
