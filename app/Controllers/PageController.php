<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class PageController extends Controller
{
    public function show(Request $request): Response
    {
        $page = Database::instance()->first(
            'SELECT * FROM cms_pages WHERE slug = :s AND is_published = 1',
            ['s' => (string) $request->param('slug')]
        );
        if ($page === null) {
            throw new HttpException(404, 'That page does not exist.');
        }

        return $this->view('cms/page', [
            'title' => $page['meta_title'] ?: $page['title'],
            'meta_description' => $page['meta_description'],
            'canonical' => base_url('page/' . $page['slug']),
            'page' => $page,
        ]);
    }

    public function faq(Request $request): Response
    {
        $faqs = Database::instance()->select(
            'SELECT * FROM faqs WHERE is_published = 1 ORDER BY category, sort_order, id'
        );
        $grouped = [];
        foreach ($faqs as $faq) {
            $grouped[(string) $faq['category']][] = $faq;
        }

        return $this->view('cms/faq', [
            'title' => 'Frequently Asked Questions',
            'meta_description' => 'Answers about registering, KYC, auctions, weighment, payments and commission on ScrapX.',
            'canonical' => base_url('faq'),
            'grouped' => $grouped,
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $faq['answer'])],
                ], $faqs),
            ],
        ]);
    }

    public function contact(Request $request): Response
    {
        $page = Database::instance()->first("SELECT * FROM cms_pages WHERE slug = 'contact' AND is_published = 1");
        return $this->view('cms/contact', [
            'title' => 'Contact Us',
            'meta_description' => 'Get in touch with the ScrapX team for sales, verification or support.',
            'canonical' => base_url('contact'),
            'page' => $page,
        ]);
    }

    public function submitContact(Request $request): Response
    {
        $validator = $this->validate($request, [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'mobile' => 'nullable|mobile',
            'subject' => 'nullable|max:190',
            'message' => 'required|min:10|max:4000',
        ]);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/contact');
        }

        Database::instance()->insert('contact_messages', [
            'name' => (string) $request->input('name'),
            'email' => strtolower((string) $request->input('email')),
            'mobile' => $request->input('mobile') ?: null,
            'subject' => $request->input('subject') ?: 'Website enquiry',
            'message' => (string) $request->input('message'),
            'status' => 'new',
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tell the support team in-app; email goes out only if SMTP is configured.
        $staff = Database::instance()->select(
            "SELECT DISTINCT ur.user_id FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug IN ('super_admin','admin','support_manager')"
        );
        \App\Services\NotificationService::dispatchMany(
            array_map(static fn (array $r): int => (int) $r['user_id'], $staff),
            'account_status',
            [
                'title' => 'New contact message',
                'body' => $request->input('name') . ': ' . mb_substr((string) $request->input('message'), 0, 120),
                'link' => '/admin/contact-messages',
                'channels' => [],
            ]
        );

        flash('success', 'Thank you — we have received your message and will reply soon.');
        return $this->redirect('/contact');
    }
}
