<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\RateLimiter;

/**
 * Scheduler.
 *
 * Works two ways, because shared hosting often has no reliable cron:
 *   1. System cron:  * * * * * php /path/cli.php cron
 *   2. Web fallback: GET /cron/run?key=<cron_secret>  (hit by an uptime pinger,
 *      or opportunistically by the app itself)
 *
 * Each job declares an interval; runDue() only executes the ones that are due,
 * so calling it every minute is safe and calling it hourly just means jobs run
 * a little later.
 */
final class CronService
{
    /** job key => callable returning ['affected' => int, 'message' => string] */
    public static function jobs(): array
    {
        return [
            'auction_lifecycle' => static function (): array {
                $started = AuctionService::startDue();
                $closed = AuctionService::closeDue();
                return [
                    'affected' => $started + $closed,
                    'message' => "{$started} auction(s) started, {$closed} closed",
                ];
            },
            'auction_alerts' => static function (): array {
                $sent = AuctionService::sendEndingAlerts();
                return ['affected' => $sent, 'message' => "{$sent} ending-soon alert(s) queued"];
            },
            'expire_offers' => static function (): array {
                $expired = OfferService::expireDue();
                return ['affected' => $expired, 'message' => "{$expired} offer(s) expired"];
            },
            'expire_listings' => static function (): array {
                $listings = Database::instance()->statement(
                    "UPDATE listings SET status = 'expired', updated_at = :n
                     WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < :now AND deleted_at IS NULL",
                    ['n' => now(), 'now' => now()]
                );
                $requirements = RequirementService::expireDue();
                return [
                    'affected' => $listings + $requirements,
                    'message' => "{$listings} listing(s) and {$requirements} requirement(s) expired",
                ];
            },
            'close_rfqs' => static function (): array {
                $result = RfqService::processDue();
                return [
                    'affected' => $result['closed'] + $result['closing_soon'],
                    'message' => "{$result['closing_soon']} closing soon, {$result['closed']} closed",
                ];
            },
            'notification_queue' => static function (): array {
                $batch = SettingsService::int('notify_queue_batch', 25);
                $result = NotificationService::processQueue($batch);
                return [
                    'affected' => $result['sent'],
                    'message' => "{$result['sent']} sent, {$result['failed']} failed, {$result['skipped']} skipped (no provider)",
                ];
            },
            'update_check' => static function (): array {
                if (!SettingsService::bool('update_auto_check', true)) {
                    return ['affected' => 0, 'message' => 'Automatic update checks are disabled'];
                }
                $result = Updates\UpdateService::check();
                return [
                    'affected' => ($result['update_available'] ?? false) ? 1 : 0,
                    'message' => $result['ok']
                        ? (($result['update_available'] ?? false)
                            ? 'Update available: ' . ($result['latest_version'] ?? '?')
                            : 'Already up to date')
                        : 'Check failed: ' . ($result['error'] ?? ''),
                ];
            },
            'auto_backup' => static function (): array {
                if (!SettingsService::bool('backup_auto_enabled', false)) {
                    return ['affected' => 0, 'message' => 'Automatic backups are disabled'];
                }
                $hours = SettingsService::int('backup_auto_frequency_hours', 24);
                $last = Database::instance()->scalar(
                    "SELECT created_at FROM system_backups WHERE trigger_type = 'scheduled' AND status = 'completed'
                     ORDER BY id DESC LIMIT 1"
                );
                if ($last !== null && strtotime((string) $last . ' UTC') > time() - ($hours * 3600)) {
                    return ['affected' => 0, 'message' => 'Last scheduled backup is still recent'];
                }
                $result = BackupService::create('database', 'scheduled', null);
                BackupService::prune();
                return [
                    'affected' => $result['ok'] ? 1 : 0,
                    'message' => $result['ok'] ? 'Backup created: ' . $result['filename'] : 'Backup failed: ' . ($result['error'] ?? ''),
                ];
            },
            'cleanup' => static function (): array {
                $db = Database::instance();
                $rateLimits = RateLimiter::prune();
                $otps = $db->statement('DELETE FROM otp_codes WHERE expires_at < :cutoff', [
                    'cutoff' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
                ]);
                $resets = $db->statement('DELETE FROM password_resets WHERE expires_at < :cutoff', [
                    'cutoff' => gmdate('Y-m-d H:i:s', strtotime('-7 days')),
                ]);
                $views = $db->statement('DELETE FROM listing_views WHERE viewed_on < :cutoff', [
                    'cutoff' => gmdate('Y-m-d', strtotime('-180 days')),
                ]);
                $runs = $db->statement('DELETE FROM cron_runs WHERE created_at < :cutoff', [
                    'cutoff' => gmdate('Y-m-d H:i:s', strtotime('-30 days')),
                ]);
                $logs = self::pruneLogFiles(30);
                $backups = BackupService::prune();
                return [
                    'affected' => $rateLimits + $otps + $resets + $views + $runs + $logs + $backups,
                    'message' => "Pruned {$rateLimits} rate limits, {$otps} OTPs, {$resets} resets, {$views} views, {$runs} runs, {$logs} logs, {$backups} backups",
                ];
            },
            'refresh_stats' => static function (): array {
                \Database\Seeders\CatalogSeeder::refreshCounts(Database::instance());
                $businesses = \App\Models\Business::refreshAllStats();
                $flagged = FraudService::evaluateRecent(50);
                return [
                    'affected' => $businesses,
                    'message' => "Refreshed {$businesses} business profiles, risk-scored {$flagged} accounts",
                ];
            },
        ];
    }

    /** @return array<int, array{job:string,status:string,message:string,duration_ms:int}> */
    public static function runDue(string $source = 'cron'): array
    {
        $db = Database::instance();
        $jobs = self::jobs();
        $results = [];

        $due = $db->select(
            'SELECT * FROM cron_jobs WHERE is_enabled = 1
             AND (last_run_at IS NULL OR last_run_at <= DATE_SUB(:now, INTERVAL interval_minutes MINUTE))
             ORDER BY id',
            ['now' => now()]
        );

        foreach ($due as $job) {
            $key = (string) $job['job_key'];
            if (!isset($jobs[$key])) {
                continue;
            }
            $results[] = self::execute($key, $jobs[$key], $source);
        }

        SettingsService::set('cron_last_run_at', now(), 'string', 'cron');
        return $results;
    }

    public static function runJob(string $key, string $source = 'manual'): array
    {
        $jobs = self::jobs();
        if (!isset($jobs[$key])) {
            return ['job' => $key, 'status' => 'failed', 'message' => 'Unknown job', 'duration_ms' => 0];
        }
        return self::execute($key, $jobs[$key], $source);
    }

    private static function execute(string $key, callable $job, string $source): array
    {
        $db = Database::instance();
        $start = microtime(true);

        $db->statement(
            "UPDATE cron_jobs SET last_status = 'running', last_run_at = :n WHERE job_key = :k",
            ['n' => now(), 'k' => $key]
        );

        try {
            $result = $job();
            $duration = (int) round((microtime(true) - $start) * 1000);
            $message = (string) ($result['message'] ?? 'ok');
            $affected = (int) ($result['affected'] ?? 0);

            $db->statement(
                "UPDATE cron_jobs SET last_status = 'success', last_duration_ms = :d, last_message = :m,
                        run_count = run_count + 1, updated_at = :n
                 WHERE job_key = :k",
                ['d' => $duration, 'm' => substr($message, 0, 500), 'n' => now(), 'k' => $key]
            );
            $db->insert('cron_runs', [
                'job_key' => $key,
                'status' => 'success',
                'duration_ms' => $duration,
                'affected' => $affected,
                'message' => substr($message, 0, 500),
                'trigger_source' => $source,
                'created_at' => now(),
            ]);

            return ['job' => $key, 'status' => 'success', 'message' => $message, 'duration_ms' => $duration];
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            logger()->error('Cron job failed: ' . $key . ' — ' . $e->getMessage());

            $db->statement(
                "UPDATE cron_jobs SET last_status = 'failed', last_duration_ms = :d, last_message = :m,
                        run_count = run_count + 1, fail_count = fail_count + 1, updated_at = :n
                 WHERE job_key = :k",
                ['d' => $duration, 'm' => substr($e->getMessage(), 0, 500), 'n' => now(), 'k' => $key]
            );
            $db->insert('cron_runs', [
                'job_key' => $key,
                'status' => 'failed',
                'duration_ms' => $duration,
                'affected' => 0,
                'message' => substr($e->getMessage(), 0, 500),
                'trigger_source' => $source,
                'created_at' => now(),
            ]);

            return ['job' => $key, 'status' => 'failed', 'message' => $e->getMessage(), 'duration_ms' => $duration];
        }
    }

    private static function pruneLogFiles(int $keepDays): int
    {
        $files = glob(STORAGE_PATH . '/logs/*.log') ?: [];
        $cutoff = time() - ($keepDays * 86400);
        $removed = 0;
        foreach ($files as $file) {
            if ((filemtime($file) ?: time()) < $cutoff && @unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    public static function status(): array
    {
        return Database::instance()->select('SELECT * FROM cron_jobs ORDER BY id');
    }

    public static function recentRuns(int $limit = 50): array
    {
        return Database::instance()->select(
            'SELECT * FROM cron_runs ORDER BY id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** Has the scheduler run recently enough to be trusted? */
    public static function isHealthy(): array
    {
        $last = SettingsService::get('cron_last_run_at', '');
        if ($last === '' || $last === null) {
            return ['ok' => false, 'message' => 'The scheduler has never run. Set up cron or enable the web fallback.'];
        }
        $age = time() - strtotime((string) $last . ' UTC');
        if ($age > 3600) {
            return ['ok' => false, 'message' => 'Scheduler last ran ' . time_ago((string) $last) . '. Auctions may not close on time.'];
        }
        return ['ok' => true, 'message' => 'Scheduler last ran ' . time_ago((string) $last) . '.'];
    }

    public static function verifySecret(string $key): bool
    {
        $secret = (string) SettingsService::get('cron_secret', '');
        return $secret !== '' && hash_equals($secret, $key);
    }
}
