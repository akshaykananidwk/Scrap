<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\GuideService;

/**
 * The Help Centre. Public on purpose: someone deciding whether to register
 * should be able to read how the platform works first, and a trader locked out
 * of their account still needs the article about getting back in.
 */
final class HelpController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->input('q', ''));

        if ($query !== '') {
            return $this->view('help/search', [
                'title' => 'Search the Help Centre: ' . $query,
                'meta_description' => 'Search results in the ' . site_name() . ' Help Centre.',
                'canonical' => base_url('help'),
                'query' => $query,
                'results' => GuideService::search($query, null, 25, $this->readerIsOperator()),
                'categories' => GuideService::categories(),
                'falling_back' => GuideService::isFallingBack(),
            ]);
        }

        return $this->view('help/index', [
            'title' => 'Help Centre — how to use ' . site_name(),
            'meta_description' => 'Step-by-step guides to buying, selling, auctions, orders, weighment, '
                . 'payments and configuration on ' . site_name() . '.',
            'canonical' => base_url('help'),
            'categories' => GuideService::categories(),
            'article_count' => GuideService::articleCount(),
            'falling_back' => GuideService::isFallingBack(),
        ]);
    }

    public function category(Request $request): Response
    {
        $slug = (string) $request->param('category');
        $category = GuideService::category($slug);

        if ($category === null) {
            return $this->notFound();
        }

        return $this->view('help/category', [
            'title' => $category['title'] . ' — Help Centre',
            'meta_description' => $category['summary'],
            'canonical' => base_url('help/' . $slug),
            'category' => $category,
            'categories' => GuideService::categories(),
            'falling_back' => GuideService::isFallingBack(),
        ]);
    }

    public function article(Request $request): Response
    {
        $categorySlug = (string) $request->param('category');
        $found = GuideService::article($categorySlug, (string) $request->param('article'));

        if ($found === null) {
            return $this->notFound();
        }

        return $this->view('help/article', [
            'title' => $found['article']['title'] . ' — Help Centre',
            'meta_description' => (string) ($found['article']['summary'] ?? ''),
            'canonical' => base_url('help/' . $categorySlug . '/' . $found['article']['slug']),
            'category' => $found['category'],
            'article' => $found['article'],
            'previous' => $found['previous'],
            'next' => $found['next'],
            'categories' => GuideService::categories(),
            'falling_back' => GuideService::isFallingBack(),
        ]);
    }

    /**
     * Every article on one page, for reading end to end or printing.
     *
     * An operator setting the platform up away from a desk, or training someone,
     * wants the whole manual in their hand rather than forty-eight pages to
     * click through.
     */
    public function printAll(Request $request): Response
    {
        return $this->view('help/print', [
            'title' => site_name() . ' — complete guide',
            'categories' => GuideService::categories(),
            'article_count' => GuideService::articleCount(),
        ], 'layouts/bare');
    }

    private function notFound(): Response
    {
        abort(404, 'That help article does not exist. Try the Help Centre index.');
    }

    /**
     * Whether the reader is more likely looking for a setting than for how to
     * trade. Used only to break near-ties in search results.
     */
    private function readerIsOperator(): bool
    {
        try {
            return \App\Core\Auth::check() && \App\Core\Auth::isStaff();
        } catch (\Throwable) {
            return false;
        }
    }
}
