<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\Request;
use App\Core\Response;
use App\Models\City;
use App\Models\MarketRate;
use App\Models\Material;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\NotificationService;

final class ContentController extends Controller
{
    protected string $layout = 'layouts/admin';

    // ------------------------------------------------------------- CMS pages --

    public function pages(Request $request): Response
    {
        return $this->view('admin/pages', [
            'title' => 'CMS pages',
            'pages' => Database::instance()->select('SELECT * FROM cms_pages ORDER BY sort_order, id'),
            'edit' => $request->int('edit')
                ? Database::instance()->first('SELECT * FROM cms_pages WHERE id = :id', ['id' => $request->int('edit')])
                : null,
        ]);
    }

    public function savePage(Request $request): Response
    {
        $validator = $this->validate($request, [
            'title' => 'required|min:2|max:190',
            'slug' => 'required|alpha_dash|max:120',
            'content' => 'nullable|max:200000',
        ], ['title' => 'Page title', 'slug' => 'URL slug']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/admin/pages');
        }

        $id = $request->int('id') ?: null;
        $slug = slugify((string) $request->input('slug'));

        $clash = Database::instance()->first(
            'SELECT id FROM cms_pages WHERE slug = :s' . ($id !== null ? ' AND id <> :id' : ''),
            $id !== null ? ['s' => $slug, 'id' => $id] : ['s' => $slug]
        );
        if ($clash !== null) {
            return $this->fail('Another page already uses that URL slug.');
        }

        $data = [
            'title' => (string) $request->input('title'),
            'slug' => $slug,
            // Content is authored by trusted staff and rendered as HTML; it is
            // still passed through a tag whitelist to keep scripts out.
            'content' => $this->sanitizeHtml((string) $request->input('content', '')),
            'meta_title' => $request->input('meta_title') ?: null,
            'meta_description' => $request->input('meta_description') ?: null,
            'show_in_footer' => $request->bool('show_in_footer') ? 1 : 0,
            'show_in_header' => $request->bool('show_in_header') ? 1 : 0,
            'sort_order' => $request->int('sort_order'),
            'is_published' => $request->bool('is_published') ? 1 : 0,
            'updated_by' => $this->userId(),
            'updated_at' => now(),
        ];

        if ($id !== null) {
            Database::instance()->update('cms_pages', $data, ['id' => $id]);
        } else {
            $data['created_at'] = now();
            $id = Database::instance()->insert('cms_pages', $data);
        }

        AuditService::log('cms_page_saved', 'cms_page', $id, null, ['slug' => $slug]);
        flash('success', 'Page saved.');
        return $this->redirect('/admin/pages?edit=' . $id);
    }

    public function deletePage(Request $request): Response
    {
        $id = $request->paramInt('id');
        $page = Database::instance()->first('SELECT * FROM cms_pages WHERE id = :id', ['id' => $id]);
        if ($page === null) {
            throw new HttpException(404);
        }
        if ((int) $page['is_system'] === 1) {
            flash('danger', 'System pages such as Terms and Privacy cannot be deleted — unpublish them instead.');
            return $this->back('/admin/pages');
        }

        Database::instance()->delete('cms_pages', ['id' => $id]);
        AuditService::log('cms_page_deleted', 'cms_page', $id);
        flash('success', 'Page deleted.');
        return $this->redirect('/admin/pages');
    }

    // ------------------------------------------------------------------ FAQs --

    public function faqs(Request $request): Response
    {
        return $this->view('admin/faqs', [
            'title' => 'FAQs',
            'faqs' => Database::instance()->select('SELECT * FROM faqs ORDER BY category, sort_order, id'),
            'edit' => $request->int('edit')
                ? Database::instance()->first('SELECT * FROM faqs WHERE id = :id', ['id' => $request->int('edit')])
                : null,
        ]);
    }

    public function saveFaq(Request $request): Response
    {
        $question = trim((string) $request->input('question', ''));
        $answer = trim((string) $request->input('answer', ''));
        if ($question === '' || $answer === '') {
            return $this->fail('Question and answer are both required.');
        }

        $id = $request->int('id') ?: null;
        $data = [
            'question' => substr($question, 0, 255),
            'answer' => $this->sanitizeHtml($answer),
            'category' => (string) $request->input('category', 'general'),
            'sort_order' => $request->int('sort_order'),
            'is_published' => $request->bool('is_published') ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($id !== null) {
            Database::instance()->update('faqs', $data, ['id' => $id]);
        } else {
            $data['created_at'] = now();
            Database::instance()->insert('faqs', $data);
        }

        flash('success', 'FAQ saved.');
        return $this->redirect('/admin/faqs');
    }

    public function deleteFaq(Request $request): Response
    {
        Database::instance()->delete('faqs', ['id' => $request->paramInt('id')]);
        flash('success', 'FAQ deleted.');
        return $this->redirect('/admin/faqs');
    }

    // ---------------------------------------------------------- market rates --

    public function marketRates(Request $request): Response
    {
        $filters = array_filter([
            'material_id' => $request->int('material_id'),
            'city_id' => $request->int('city_id'),
            'date' => (string) $request->query('date', ''),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('admin/market_rates', [
            'title' => 'Market rates',
            'rates' => MarketRate::paginate($filters, $request->page(), 40),
            'filters' => $filters,
            'materials' => Database::instance()->select('SELECT id, name FROM materials WHERE is_active = 1 ORDER BY name'),
            'cities' => City::major(60),
            'units' => Unit::active(),
        ]);
    }

    public function saveMarketRate(Request $request): Response
    {
        $validator = $this->validate($request, [
            'material_id' => 'required|integer|exists:materials,id',
            'rate' => 'required|numeric|gt:0',
            'unit_id' => 'required|integer|exists:units,id',
            'rate_date' => 'required|date',
        ], ['material_id' => 'Material', 'rate' => 'Rate', 'unit_id' => 'Unit', 'rate_date' => 'Date']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/admin/market-rates');
        }

        $id = MarketRate::record([
            'material_id' => $request->int('material_id'),
            'grade_id' => $request->int('grade_id') ?: null,
            'city_id' => $request->int('city_id') ?: null,
            'rate' => $request->input('rate'),
            'unit_id' => $request->int('unit_id'),
            'rate_date' => (string) $request->input('rate_date'),
            'source' => $request->input('source') ?: 'Admin entry',
            'notes' => $request->input('notes'),
            'is_published' => $request->bool('is_published') ? 1 : 0,
            'created_by' => $this->userId(),
        ]);

        AuditService::log('market_rate_saved', 'market_rate', $id);
        flash('success', 'Rate saved.');
        return $this->redirect('/admin/market-rates');
    }

    public function deleteMarketRate(Request $request): Response
    {
        Database::instance()->delete('market_rates', ['id' => $request->paramInt('id')]);
        flash('success', 'Rate deleted.');
        return $this->back('/admin/market-rates');
    }

    // --------------------------------------------------- notification config --

    public function templates(Request $request): Response
    {
        return $this->view('admin/templates', [
            'title' => 'Notification templates',
            'templates' => Database::instance()->select('SELECT * FROM email_templates ORDER BY event, channel'),
            'edit' => $request->int('edit')
                ? Database::instance()->first('SELECT * FROM email_templates WHERE id = :id', ['id' => $request->int('edit')])
                : null,
            'events' => array_keys(NotificationService::EVENTS),
            'channels' => [
                'email' => ['label' => 'Email', 'configured' => NotificationService::provider('email')->isConfigured()],
                'sms' => ['label' => 'SMS', 'configured' => NotificationService::provider('sms')->isConfigured()],
                'whatsapp' => ['label' => 'WhatsApp', 'configured' => NotificationService::provider('whatsapp')->isConfigured()],
                'push' => ['label' => 'Push', 'configured' => NotificationService::provider('push')->isConfigured()],
            ],
        ]);
    }

    public function saveTemplate(Request $request): Response
    {
        $id = $request->paramInt('id');
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return $this->fail('The template body cannot be empty.');
        }

        Database::instance()->update('email_templates', [
            'name' => (string) $request->input('name', ''),
            'subject' => $request->input('subject') ?: null,
            'body' => $body,
            'variables' => $request->input('variables') ?: null,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'updated_at' => now(),
        ], ['id' => $id]);

        AuditService::log('template_saved', 'email_template', $id);
        flash('success', 'Template saved.');
        return $this->redirect('/admin/templates?edit=' . $id);
    }

    public function notificationQueue(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $sql = 'SELECT q.*, u.full_name FROM notification_queue q
                LEFT JOIN users u ON u.id = q.user_id WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM notification_queue q WHERE 1 = 1';
        $params = [];

        if ($status !== '') {
            $sql .= ' AND q.status = :status';
            $count .= ' AND q.status = :status';
            $params['status'] = $status;
        }

        return $this->view('admin/notification_queue', [
            'title' => 'Notification queue',
            'queue' => Model::paginateQuery($sql . ' ORDER BY q.id DESC', $params, $request->page(), 40, $count),
            'filters' => ['status' => $status],
            'summary' => Database::instance()->first(
                "SELECT COUNT(*) AS total,
                        SUM(status = 'queued') AS queued,
                        SUM(status = 'sent') AS sent,
                        SUM(status = 'failed') AS failed,
                        SUM(status = 'skipped') AS skipped
                 FROM notification_queue"
            ) ?? [],
            'providers' => [
                'email' => NotificationService::provider('email'),
                'sms' => NotificationService::provider('sms'),
                'whatsapp' => NotificationService::provider('whatsapp'),
                'push' => NotificationService::provider('push'),
            ],
        ]);
    }

    public function retryNotification(Request $request): Response
    {
        $id = $request->paramInt('id');
        Database::instance()->update('notification_queue', [
            'status' => 'queued',
            'attempts' => 0,
            'last_error' => null,
            'scheduled_at' => now(),
        ], ['id' => $id]);

        $result = NotificationService::processQueue(1);
        flash('info', sprintf('Queue run: %d sent, %d failed, %d skipped.', $result['sent'], $result['failed'], $result['skipped']));
        return $this->back('/admin/notifications');
    }

    // ------------------------------------------------------------------ plans --

    public function plans(Request $request): Response
    {
        $plans = Database::instance()->select('SELECT * FROM plans ORDER BY sort_order, price');
        foreach ($plans as &$plan) {
            $plan['features'] = Database::instance()->select(
                'SELECT * FROM subscription_features WHERE plan_id = :p ORDER BY sort_order',
                ['p' => (int) $plan['id']]
            );
            $plan['subscriber_count'] = (int) Database::instance()->scalar(
                "SELECT COUNT(*) FROM subscriptions WHERE plan_id = :p AND status = 'active'",
                ['p' => (int) $plan['id']],
                0
            );
        }

        return $this->view('admin/plans', [
            'title' => 'Subscription plans',
            'plans' => $plans,
            'edit' => $request->int('edit')
                ? Database::instance()->first('SELECT * FROM plans WHERE id = :id', ['id' => $request->int('edit')])
                : null,
            'subscriptions' => Database::instance()->select(
                'SELECT s.*, u.full_name, p.name AS plan_name FROM subscriptions s
                 INNER JOIN users u ON u.id = s.user_id
                 INNER JOIN plans p ON p.id = s.plan_id
                 ORDER BY s.id DESC LIMIT 25'
            ),
        ]);
    }

    public function savePlan(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return $this->fail('Plan name is required.');
        }

        $id = $request->int('id') ?: null;
        $data = [
            'name' => substr($name, 0, 90),
            'audience' => (string) $request->input('audience', 'both'),
            'description' => $request->input('description') ?: null,
            'price' => dec($request->input('price', 0), 2),
            'gst_rate' => dec($request->input('gst_rate', 18), 2),
            'billing_period' => (string) $request->input('billing_period', 'monthly'),
            'listing_limit' => $request->int('listing_limit'),
            'auction_limit' => $request->int('auction_limit'),
            'rfq_limit' => $request->int('rfq_limit'),
            'featured_credits' => $request->int('featured_credits'),
            'commission_discount' => dec($request->input('commission_discount', 0), 3),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'sort_order' => $request->int('sort_order'),
            'updated_at' => now(),
        ];

        if ($id !== null) {
            Database::instance()->update('plans', $data, ['id' => $id]);
        } else {
            $data['slug'] = slugify($name);
            $data['created_at'] = now();
            $id = Database::instance()->insert('plans', $data);
        }

        AuditService::log('plan_saved', 'plan', $id, null, ['name' => $name]);
        flash('success', 'Plan saved.');
        return $this->redirect('/admin/plans?edit=' . $id);
    }

    /**
     * Allow presentational HTML from staff, strip anything executable.
     * Staff are trusted but this limits the blast radius of a compromised
     * moderator account (stored XSS on every visitor's browser).
     */
    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6>'
            . '<blockquote><a><img><table><thead><tbody><tr><th><td><hr><span><div><small><code><pre>';
        $clean = strip_tags($html, $allowed);

        // Remove event handlers and javascript: URLs that survive strip_tags.
        $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $clean) ?? $clean;
        $clean = preg_replace('/<\s*(script|style|iframe|object|embed|form)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $clean) ?? $clean;

        return $clean;
    }
}
