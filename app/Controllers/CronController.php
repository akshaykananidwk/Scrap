<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CronService;
use App\Services\SettingsService;

/**
 * Web-triggered scheduler for hosts without system cron.
 *
 *   https://example.com/cron/run?key=<cron_secret>
 *
 * Point an uptime monitor at that URL every minute (or every five). The secret
 * is generated at install and shown in Admin → System → Scheduler.
 */
final class CronController extends Controller
{
    public function run(Request $request): Response
    {
        if (!SettingsService::bool('cron_web_fallback_enabled', true)) {
            throw new HttpException(403, 'The web scheduler is disabled. Use system cron instead.');
        }

        $key = (string) $request->input('key', '');
        if (!CronService::verifySecret($key)) {
            // Deliberately vague, and rate limited, so the key cannot be probed.
            \App\Core\RateLimiter::enforce('cron:' . $request->ip(), 10, 600, 'Too many scheduler attempts.');
            throw new HttpException(403, 'Invalid scheduler key.');
        }

        @set_time_limit(300);
        $job = (string) $request->input('job', '');

        $results = $job !== ''
            ? [CronService::runJob($job, 'web')]
            : CronService::runDue('web');

        $failed = array_filter($results, static fn (array $r): bool => $r['status'] !== 'success');

        return $this->json([
            'success' => $failed === [],
            'ran' => count($results),
            'results' => $results,
            'server_time' => now(),
        ]);
    }
}
