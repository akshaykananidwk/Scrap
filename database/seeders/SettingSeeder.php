<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * Every configurable behaviour of the platform lives here — no business rule is
 * hard-coded in PHP. Secrets are seeded empty and encrypted when an admin saves them.
 */
final class SettingSeeder
{
    /** group => [key, value, type, label, description, is_public] */
    public const SETTINGS = [
        'general' => [
            ['site_name', 'ScrapX', 'string', 'Site Name', 'Shown in the header, emails and invoices', 1],
            ['site_tagline', "India's B2B Scrap Trading Marketplace", 'string', 'Tagline', '', 1],
            ['site_logo', '', 'string', 'Logo', 'Path inside /uploads', 1],
            ['site_favicon', '', 'string', 'Favicon', '', 1],
            ['contact_email', '', 'string', 'Contact Email', '', 1],
            ['contact_phone', '', 'string', 'Contact Phone', '', 1],
            ['whatsapp_number', '', 'string', 'WhatsApp Number', 'Used for the floating chat button', 1],
            ['contact_address', '', 'text', 'Registered Address', '', 1],
            ['default_language', 'en', 'string', 'Default Language', 'en / hi / gu', 1],
            ['currency_code', 'INR', 'string', 'Currency Code', '', 1],
            ['currency_symbol', '₹', 'string', 'Currency Symbol', '', 1],
            ['timezone', 'Asia/Kolkata', 'string', 'Display Timezone', 'Data is stored in UTC', 0],
            ['facebook_url', '', 'string', 'Facebook URL', '', 1],
            ['linkedin_url', '', 'string', 'LinkedIn URL', '', 1],
            ['twitter_url', '', 'string', 'X / Twitter URL', '', 1],
            ['youtube_url', '', 'string', 'YouTube URL', '', 1],
        ],
        'marketplace' => [
            ['listing_requires_approval', '1', 'boolean', 'Listings need admin approval', 'New listings stay pending until approved', 0],
            ['listing_default_expiry_days', '30', 'integer', 'Listing expiry (days)', '', 0],
            ['requirement_default_expiry_days', '30', 'integer', 'Requirement expiry (days)', '', 0],
            ['offer_default_expiry_hours', '72', 'integer', 'Offer validity (hours)', '', 0],
            ['listings_per_page', '20', 'integer', 'Listings per page', '', 1],
            ['guest_can_view_listings', '1', 'boolean', 'Guests can browse listings', 'Contact details always require an account', 1],
            ['guest_listing_limit', '12', 'integer', 'Guest preview limit', 'Listings a signed-out visitor can see per page', 1],
            ['max_images_per_listing', '10', 'integer', 'Max images per listing', '', 0],
            ['max_videos_per_listing', '2', 'integer', 'Max videos per listing', '', 0],
            ['max_documents_per_listing', '5', 'integer', 'Max documents per listing', '', 0],
            ['auto_approve_verified_sellers', '1', 'boolean', 'Auto-approve KYC-verified sellers', '', 0],
        ],
        'auction' => [
            ['auction_extension_window_seconds', '120', 'integer', 'Auto-extension window (seconds)', 'A bid inside this window before close extends the auction', 0],
            ['auction_extension_duration_seconds', '120', 'integer', 'Extension duration (seconds)', '', 0],
            ['auction_max_extensions', '5', 'integer', 'Maximum extensions', '', 0],
            ['auction_default_increment', '500', 'decimal', 'Default bid increment (₹)', '', 0],
            ['auction_min_increment', '1', 'decimal', 'Minimum allowed increment (₹)', '', 0],
            ['auction_requires_kyc', '1', 'boolean', 'Bidders must be KYC verified', '', 0],
            ['auction_mask_bidders', '1', 'boolean', 'Mask bidder identity publicly', 'Shows Bidder #3 instead of the business name', 0],
            ['auction_poll_interval_ms', '4000', 'integer', 'Live poll interval (ms)', 'How often the auction page refreshes over AJAX', 1],
            ['auction_bid_rate_limit', '20', 'integer', 'Max bids per minute per user', 'Anti-hammering protection', 0],
            ['auction_reverse_enabled', '1', 'boolean', 'Enable reverse auctions', '', 0],
            ['auction_auto_create_order', '1', 'boolean', 'Create the order automatically when an auction closes', 'Turn off to let the seller award manually', 0],
            ['auction_default_duration_hours', '72', 'integer', 'Default auction duration (hours)', '', 0],
        ],
        'commission' => [
            ['commission_enabled', '1', 'boolean', 'Charge platform commission', '', 0],
            ['commission_percentage', '1.000', 'decimal', 'Sale commission (%)', 'Applied to the order value', 0],
            ['commission_fixed', '0.00', 'decimal', 'Fixed commission (₹)', 'Added on top of the percentage', 0],
            ['commission_party', 'seller', 'string', 'Commission charged to', 'seller / buyer / both', 0],
            ['buyer_fee_percentage', '0.000', 'decimal', 'Buyer fee (%)', '', 0],
            ['seller_fee_percentage', '0.000', 'decimal', 'Seller fee (%)', '', 0],
            ['auction_fee_percentage', '0.500', 'decimal', 'Auction success fee (%)', '', 0],
            ['listing_fee', '0.00', 'decimal', 'Listing fee (₹)', 'Charged per published listing', 0],
            ['featured_listing_fee', '499.00', 'decimal', 'Featured listing fee (₹)', '', 0],
            ['commission_gst_rate', '18.00', 'decimal', 'GST on commission (%)', '', 0],
            ['commission_min_amount', '0.00', 'decimal', 'Minimum commission (₹)', '', 0],
            ['commission_max_amount', '0.00', 'decimal', 'Maximum commission (₹)', '0 = no cap', 0],
        ],
        'security' => [
            ['require_mobile_verification', '1', 'boolean', 'Require mobile OTP verification', '', 0],
            ['require_email_verification', '0', 'boolean', 'Require email verification', '', 0],
            ['require_admin_approval', '0', 'boolean', 'New accounts need admin approval', '', 0],
            ['require_kyc_for_trading', '0', 'boolean', 'Require KYC before trading', '', 0],
            ['login_max_attempts', '5', 'integer', 'Max login attempts', 'Per 15 minutes per IP + identifier', 0],
            ['login_decay_seconds', '900', 'integer', 'Login lockout window (seconds)', '', 0],
            ['otp_length', '6', 'integer', 'OTP length', '', 0],
            ['otp_expiry_minutes', '10', 'integer', 'OTP validity (minutes)', '', 0],
            ['otp_max_attempts', '5', 'integer', 'Max OTP verify attempts', '', 0],
            ['otp_resend_limit', '3', 'integer', 'Max OTP sends per hour', '', 0],
            ['session_lifetime_minutes', '480', 'integer', 'Session lifetime (minutes)', '', 0],
            ['password_min_length', '8', 'integer', 'Minimum password length', '', 0],
            ['maintenance_mode', '0', 'boolean', 'Maintenance mode', 'Shows a maintenance page to visitors', 0],
            ['maintenance_message', 'Website is temporarily under maintenance.', 'text', 'Maintenance message', '', 0],
            ['maintenance_allowed_ips', '', 'string', 'Maintenance bypass IPs', 'Comma separated', 0],
        ],
        'uploads' => [
            ['upload_max_image_mb', '5', 'integer', 'Max image size (MB)', '', 0],
            ['upload_max_doc_mb', '8', 'integer', 'Max document size (MB)', '', 0],
            ['upload_max_video_mb', '25', 'integer', 'Max video size (MB)', '', 0],
            ['upload_image_quality', '82', 'integer', 'Image re-encode quality', '1-100', 0],
            ['upload_image_max_width', '1600', 'integer', 'Max image width (px)', 'Larger images are resized down', 0],
        ],
        'notifications' => [
            ['notify_channel_email', '1', 'boolean', 'Email notifications enabled', '', 0],
            ['notify_channel_sms', '0', 'boolean', 'SMS notifications enabled', 'Requires an SMS provider', 0],
            ['notify_channel_whatsapp', '0', 'boolean', 'WhatsApp notifications enabled', 'Requires WhatsApp Cloud API credentials', 0],
            ['notify_queue_batch', '25', 'integer', 'Queue batch size per run', '', 0],
            ['notify_max_attempts', '3', 'integer', 'Max delivery attempts', '', 0],
            ['outbid_notification', '1', 'boolean', 'Notify bidders when outbid', '', 0],
            ['auction_ending_alert_minutes', '30', 'integer', 'Ending-soon alert (minutes before close)', '', 0],
        ],
        'mail' => [
            ['mail_driver', 'mail', 'string', 'Mail driver', 'mail (PHP) or smtp', 0],
            ['mail_from_address', '', 'string', 'From address', '', 0],
            ['mail_from_name', 'ScrapX', 'string', 'From name', '', 0],
            ['smtp_host', '', 'string', 'SMTP host', '', 0],
            ['smtp_port', '587', 'integer', 'SMTP port', '', 0],
            ['smtp_username', '', 'string', 'SMTP username', '', 0],
            ['smtp_password', '', 'secret', 'SMTP password', 'Stored encrypted', 0],
            ['smtp_encryption', 'tls', 'string', 'SMTP encryption', 'tls / ssl / none', 0],
        ],
        'sms' => [
            ['sms_provider', '', 'string', 'SMS provider', 'Leave empty to disable', 0],
            ['sms_api_url', '', 'string', 'SMS API endpoint', '', 0],
            ['sms_api_key', '', 'secret', 'SMS API key', 'Stored encrypted', 0],
            ['sms_sender_id', '', 'string', 'Sender ID', '', 0],
            ['sms_dlt_template_id', '', 'string', 'DLT template id', 'Required for Indian transactional SMS', 0],
        ],
        'whatsapp' => [
            ['whatsapp_provider', '', 'string', 'WhatsApp provider', 'cloud_api or empty', 0],
            ['whatsapp_phone_number_id', '', 'string', 'Phone number ID', '', 0],
            ['whatsapp_business_id', '', 'string', 'Business account ID', '', 0],
            ['whatsapp_access_token', '', 'secret', 'Access token', 'Stored encrypted', 0],
        ],
        'payments' => [
            ['payment_gateway', 'manual', 'string', 'Active gateway', 'manual or razorpay', 0],
            ['payment_methods', '["upi","bank_transfer","neft","rtgs","imps","cash","credit"]', 'json', 'Enabled payment methods', '', 1],
            ['razorpay_key_id', '', 'string', 'Razorpay key id', '', 0],
            ['razorpay_key_secret', '', 'secret', 'Razorpay key secret', 'Stored encrypted', 0],
            ['razorpay_webhook_secret', '', 'secret', 'Razorpay webhook secret', 'Stored encrypted', 0],
            ['upi_id', '', 'string', 'Platform UPI ID', 'Shown for manual UPI payments', 0],
            ['bank_account_name', '', 'string', 'Bank account name', '', 0],
            ['bank_account_number', '', 'string', 'Bank account number', '', 0],
            ['bank_ifsc', '', 'string', 'Bank IFSC', '', 0],
            ['bank_name', '', 'string', 'Bank name', '', 0],
            ['wallet_enabled', '0', 'boolean', 'Enable user wallets', '', 0],
        ],
        'invoice' => [
            ['invoice_prefix', 'INV', 'string', 'Invoice number prefix', '', 0],
            ['invoice_start_number', '1', 'integer', 'Starting serial', '', 0],
            ['invoice_terms', 'Goods once sold will not be taken back. Subject to local jurisdiction.', 'text', 'Invoice terms', '', 0],
            ['invoice_footer', 'This is a computer generated invoice.', 'string', 'Invoice footer', '', 0],
            ['platform_gstin', '', 'string', 'Platform GSTIN', 'Used on commission invoices', 0],
            ['platform_state_code', '24', 'string', 'Platform GST state code', 'Determines CGST/SGST vs IGST', 0],
        ],
        'updates' => [
            ['github_owner', '', 'string', 'GitHub owner', '', 0],
            ['github_repo', '', 'string', 'GitHub repository', '', 0],
            ['github_branch', 'main', 'string', 'Branch', '', 0],
            ['github_token', '', 'secret', 'GitHub token', 'Stored encrypted, never displayed', 0],
            ['github_use_releases', '0', 'boolean', 'Track releases instead of branch commits', '', 0],
            ['update_auto_check', '1', 'boolean', 'Check for updates automatically', 'Runs daily via the scheduler', 0],
            ['update_last_check_at', '', 'string', 'Last update check', '', 0],
            ['update_latest_version', '', 'string', 'Latest known version', '', 0],
            ['update_backup_before', '1', 'boolean', 'Always back up before updating', '', 0],
            ['update_protected_paths', '["config.php",".env","uploads","storage","backup","logs",".htaccess"]', 'json', 'Protected paths', 'Never overwritten by an update', 0],
        ],
        'backup' => [
            ['backup_retention_count', '10', 'integer', 'Backups to keep', 'Older backups are pruned automatically', 0],
            ['backup_auto_enabled', '0', 'boolean', 'Automatic scheduled backups', '', 0],
            ['backup_auto_frequency_hours', '24', 'integer', 'Automatic backup interval (hours)', '', 0],
            ['backup_include_uploads', '0', 'boolean', 'Include /uploads in full backups', 'Can make backups very large', 0],
        ],
        'cron' => [
            ['cron_web_fallback_enabled', '1', 'boolean', 'Allow web-triggered scheduler', 'Lets /cron/run?key=… work without system cron', 0],
            ['cron_secret', '', 'string', 'Scheduler secret key', 'Generated at install', 0],
            ['cron_last_run_at', '', 'string', 'Last scheduler run', '', 0],
        ],
        'seo' => [
            ['seo_meta_title', 'ScrapX — B2B Scrap Trading, Auctions & RFQ Marketplace in India', 'string', 'Default meta title', '', 1],
            ['seo_meta_description', 'Buy and sell iron, copper, aluminium, e-waste, plastic and paper scrap. Live auctions, buyer requirements, RFQs and verified businesses across India.', 'text', 'Default meta description', '', 1],
            ['seo_meta_keywords', 'scrap marketplace, scrap trading india, metal scrap, e-waste, scrap auction, scrap rates', 'text', 'Default keywords', '', 1],
            ['seo_og_image', '', 'string', 'Default social share image', '', 1],
            ['seo_google_analytics', '', 'string', 'Analytics measurement ID', '', 1],
            ['seo_search_console', '', 'string', 'Search Console verification', '', 1],
            ['seo_indexing_enabled', '1', 'boolean', 'Allow search engine indexing', 'Turn off for a staging site', 1],
        ],
    ];

    public static function run(Database $db): void
    {
        foreach (self::SETTINGS as $group => $settings) {
            foreach ($settings as [$key, $value, $type, $label, $description, $isPublic]) {
                $exists = $db->first('SELECT id FROM settings WHERE key_name = :k', ['k' => $key]);
                if ($exists !== null) {
                    // Keep operator-configured values; only refresh metadata.
                    $db->update('settings', [
                        'group_name' => $group,
                        'label' => $label,
                        'description' => $description,
                        'is_public' => $isPublic,
                        'updated_at' => now(),
                    ], ['id' => (int) $exists['id']]);
                    continue;
                }
                $db->insert('settings', [
                    'group_name' => $group,
                    'key_name' => $key,
                    'value' => $value,
                    'type' => $type,
                    'label' => $label,
                    'description' => $description,
                    'is_public' => $isPublic,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
