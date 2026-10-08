<?php

declare(strict_types=1);

/** Help Centre — First-time setup for whoever runs the platform. */
return [
    'title' => 'Operator setup',
    'summary' => 'Install the platform and configure every setting, in the order that gets you to a working site fastest.',
    'icon' => 'bi-sliders',
    'audience' => 'operator',
    'articles' => [

        [
            'slug' => 'setup-checklist',
            'title' => 'Setup checklist — read this first',
            'summary' => 'The whole configuration in one list, in the right order, with what is essential and what can wait.',
            'minutes' => 5,
            'keywords' => ['checklist', 'setup', 'first time', 'configuration', 'order', 'go live'],
            'body' => [
                ['type' => 'p', 'text' => 'This is the map. Each item links to the article that explains it. Work down the list — the order matters, because later steps depend on earlier ones.'],

                ['type' => 'h', 'text' => 'Essential — the site will not work properly without these'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Install the platform', 'text' => 'Database, administrator account, sample catalogue. Browser only, no command line. See <em>Install the platform</em>.'],
                    ['title' => 'Set up the scheduler (cron)', 'text' => 'One cron entry in your hosting panel. Without it auctions never close, offers never expire and no email or SMS is ever sent. This is the step most often missed, and it breaks the most. See <em>Set up the scheduler</em>.'],
                    ['title' => 'Fill in General settings', 'text' => 'Site name, contact details, address, currency, timezone. See <em>General settings</em>.'],
                    ['title' => 'Configure email', 'text' => 'Password resets and order confirmations depend on it. See <em>Email, SMS and WhatsApp</em>.'],
                    ['title' => 'Set your GST and invoice details', 'text' => 'Platform GSTIN, state code, invoice prefix. Needed before the first invoice. See <em>Invoices and GST</em>.'],
                    ['title' => 'Decide your commission', 'text' => 'How the platform earns. See <em>Commission and fees</em>.'],
                    ['title' => 'Review security settings', 'text' => 'What new accounts must do before they can trade. See <em>Security and access rules</em>.'],
                    ['title' => 'Take a backup and check it', 'text' => 'Before you let anyone in. See <em>Backups</em> in Running the platform.'],
                ]],

                ['type' => 'h', 'text' => 'Strongly recommended'],
                ['type' => 'list', 'items' => [
                    'SMS, so one-time codes and outbid alerts actually arrive — see <em>Email, SMS and WhatsApp</em>.',
                    'Marketplace rules: whether listings need approval, expiry periods, upload limits — see <em>Marketplace rules</em>.',
                    'Auction defaults, especially the anti-sniping window — see <em>Auction defaults</em>.',
                    'Rewrite the About, Terms, KYC Policy and Commission Policy pages in your own words. They ship as placeholders.',
                    'Connect the GitHub updater so you can update from the admin panel — see <em>Updates from GitHub</em> in Running the platform.',
                ]],

                ['type' => 'h', 'text' => 'Optional'],
                ['type' => 'list', 'items' => [
                    'An online payment gateway — see <em>Payments and bank details</em>.',
                    'WhatsApp notifications.',
                    'Wallets.',
                    'Subscription plans and featured-listing fees.',
                    'Importing your own city, PIN code, material and market-rate lists by CSV.',
                ]],

                ['type' => 'warning', 'text' => 'Do not announce the site before the scheduler is running and email works. Those two failures look like a broken platform to a trader, and first impressions in this trade are hard to recover.'],
                ['type' => 'link', 'to' => 'admin/settings', 'text' => 'Open Settings', 'icon' => 'bi-gear'],
            ],
        ],

        [
            'slug' => 'install-the-platform',
            'title' => 'Install the platform',
            'summary' => 'Upload, visit /install, answer five screens. No SSH, no Composer, no build step.',
            'minutes' => 8,
            'keywords' => ['install', 'installation', 'setup', 'hosting', 'cpanel', 'database', 'requirements', 'htaccess', '404', 'white screen'],
            'body' => [
                ['type' => 'p', 'text' => 'The platform installs through a browser and runs on ordinary shared hosting. You need no command-line access at any point, during installation or afterwards.'],

                ['type' => 'h', 'text' => 'What the server needs'],
                ['type' => 'table', 'head' => ['Requirement', 'Minimum'], 'rows' => [
                    ['PHP', '8.2 or newer'],
                    ['Database', 'MySQL 8.0+ or MariaDB 10.4+'],
                    ['PHP extensions', 'pdo, pdo_mysql, json, mbstring, fileinfo, openssl, curl'],
                    ['Recommended extensions', 'gd (image resizing), zip (backups), intl, exif'],
                    ['Web server', 'Apache with mod_rewrite, or nginx with equivalent rules'],
                ]],
                ['type' => 'p', 'text' => 'The installer checks all of this and tells you exactly what is missing, so you do not have to work it out yourself.'],

                ['type' => 'h', 'text' => 'Before you start'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Create a database', 'text' => 'In cPanel or your hosting panel: a database, a user, and give that user full rights on it. Keep the name, user and password to hand. The platform does <strong>not</strong> need permission to create databases — it only uses the one you give it.'],
                    ['title' => 'Upload the files', 'text' => 'Everything goes into your domain\'s document root — usually <code>public_html</code>. Upload the ZIP and extract it there; that is far faster than uploading thousands of files individually by FTP.'],
                    ['title' => 'Check the .htaccess file arrived', 'text' => 'It starts with a dot, so many FTP clients hide it. Without it every page but the home page will show "not found". Turn on "show hidden files" in your file manager and confirm it is there.'],
                ]],

                ['type' => 'h', 'text' => 'Running the installer'],
                ['type' => 'p', 'text' => 'Visit <code>https://yourdomain.com/install</code> and work through five screens:'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Welcome', 'text' => 'What is about to happen.'],
                    ['title' => 'Requirements', 'text' => 'Every check listed pass or fail, including which folders must be writable. Fix anything red before continuing — the installer will not let you past a genuine blocker.'],
                    ['title' => 'Database', 'text' => 'Host, name, user, password. There is a <strong>Test connection</strong> button — use it before moving on. Host is almost always <code>localhost</code> or <code>127.0.0.1</code>.'],
                    ['title' => 'Application', 'text' => 'Site name, site URL, and your administrator name, email, mobile and password. Use a strong administrator password; this account can do everything. There is also a tick box for demo data.'],
                    ['title' => 'Run', 'text' => 'The installer writes <code>config.php</code>, creates the tables, seeds roles, permissions, states, cities, the scrap catalogue, CMS pages, notification templates and scheduled jobs, creates your administrator account and then locks itself.'],
                ]],
                ['type' => 'p', 'text' => 'A successful install reports each step: 14 migrations applied, 84 tables created, 10 roles seeded, 36 states and major cities, 23 categories and 104 materials.'],

                ['type' => 'h', 'text' => 'Demo data'],
                ['type' => 'p', 'text' => 'The demo option creates sample users, listings, auctions, bids, requirements and market rates so you can see how a populated site behaves. It is useful for learning the platform and for showing it to someone.'],
                ['type' => 'warning', 'text' => 'Remove demo data before going live — <strong>Admin → System → Export / Data</strong> has a purge option. Live traders must never see sample listings.'],

                ['type' => 'h', 'text' => 'After installation'],
                ['type' => 'list', 'items' => [
                    '<code>/install</code> returns "forbidden" once the lock file exists, so the installer cannot be run again.',
                    'Sign in with the administrator account you just created.',
                    'Go straight to <em>Set up the scheduler</em> — nothing time-based works until you do.',
                ]],

                ['type' => 'h', 'text' => 'If something goes wrong'],
                ['type' => 'faq', 'items' => [
                    ['q' => 'Every page except the home page says "not found".', 'a' => 'The <code>.htaccess</code> file is missing or URL rewriting is off. Re-upload it with hidden files shown, and ask your host to enable mod_rewrite.'],
                    ['q' => 'The requirements screen says a folder is not writable.', 'a' => 'Set that folder to 0755 (or 0775 on some hosts) in your file manager. It is normally <code>storage/</code> or <code>uploads/</code>.'],
                    ['q' => 'The database test fails with access denied.', 'a' => 'The user, password or rights are wrong. In cPanel, check the user is actually added to that database with ALL PRIVILEGES. On most shared hosts the real names carry an account prefix, like <code>myaccount_scrap</code>.'],
                    ['q' => 'A blank white page.', 'a' => 'Usually a PHP version below 8.2, or a missing extension. Check the PHP version selector in your hosting panel, then re-run <code>/install/requirements</code>.'],
                ]],
            ],
        ],

        [
            'slug' => 'set-up-the-scheduler',
            'title' => 'Set up the scheduler (cron)',
            'summary' => 'The one step that breaks the most when skipped. Ten background jobs depend on it.',
            'minutes' => 6,
            'keywords' => ['cron', 'scheduler', 'cron job', 'background', 'auctions not closing', 'queue', 'cronjob', 'crontab', 'auctions not closing', 'nothing is sending'],
            'body' => [
                ['type' => 'danger', 'text' => 'Without the scheduler: <strong>auctions never close</strong>, offers never expire, listings never expire, RFQs never close, and no email, SMS or WhatsApp message is ever delivered. The site will look as if it works and quietly fail at everything time-based. Do this now.'],

                ['type' => 'h', 'text' => 'What runs, and how often'],
                ['type' => 'table', 'head' => ['Job', 'Every', 'What it does'], 'rows' => [
                    ['Auction lifecycle', '1 minute', 'Starts scheduled auctions, closes expired ones, awards winners.'],
                    ['Notification queue', '1 minute', 'Delivers queued email, SMS and WhatsApp messages.'],
                    ['Auction alerts', '5 minutes', 'Sends ending-soon alerts to bidders and watchers.'],
                    ['Expire offers', '15 minutes', 'Marks offers past their validity as expired.'],
                    ['Close RFQs', '15 minutes', 'Closes RFQs whose quote window has ended.'],
                    ['Expire listings and requirements', '1 hour', 'Archives anything past its expiry date.'],
                    ['Refresh statistics', '2 hours', 'Recalculates category counts, ratings and business metrics.'],
                    ['Housekeeping', '6 hours', 'Prunes rate limits, expired codes, old backups, stale sessions.'],
                    ['Update check', '24 hours', 'Checks GitHub for a newer version.'],
                    ['Automatic backup', '24 hours', 'Creates a scheduled backup, when enabled.'],
                ]],
                ['type' => 'p', 'text' => 'You do not schedule these individually. One cron entry calls the platform every minute and it decides which jobs are due.'],

                ['type' => 'h', 'text' => 'Getting your scheduler URL'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Open Settings → Scheduler', 'text' => 'The page shows your complete scheduler URL, including its secret key, ready to copy.'],
                    ['title' => 'Copy it', 'text' => 'It looks like <code>https://yourdomain.com/cron/run?key=<em>your-secret-key</em></code>.'],
                ]],
                ['type' => 'link', 'to' => 'admin/settings?group=cron', 'text' => 'Open Scheduler settings', 'icon' => 'bi-clock-history'],
                ['type' => 'warning', 'text' => 'The key is what stops anyone on the internet triggering your jobs. Treat it as a password: do not post it anywhere, and regenerate it if it leaks.'],

                ['type' => 'h', 'text' => 'Option A — a real cron job (best)'],
                ['type' => 'p', 'text' => 'In cPanel, open <strong>Cron Jobs</strong>, choose "Once per minute" (or <code>* * * * *</code>) and use:'],
                ['type' => 'code', 'text' => 'curl -s "https://yourdomain.com/cron/run?key=YOUR-SECRET-KEY" > /dev/null'],
                ['type' => 'p', 'text' => 'If <code>curl</code> is not available on your host, use <code>wget</code> instead:'],
                ['type' => 'code', 'text' => 'wget -q -O /dev/null "https://yourdomain.com/cron/run?key=YOUR-SECRET-KEY"'],
                ['type' => 'p', 'text' => 'With shell access you can also call the command-line runner directly, which avoids the web server entirely:'],
                ['type' => 'code', 'text' => 'cd /path/to/your/site && php cli.php cron'],

                ['type' => 'h', 'text' => 'Option B — an external monitoring service'],
                ['type' => 'p', 'text' => 'If your hosting plan has no cron, use a free uptime or cron service (cron-job.org, EasyCron and similar). Point it at your scheduler URL on a one-minute or five-minute schedule. It works, but it is slower and depends on a third party, so prefer Option A where you can.'],

                ['type' => 'h', 'text' => 'Option C — web fallback'],
                ['type' => 'p', 'text' => 'There is a <strong>web-triggered scheduler</strong> option in the Scheduler settings. With it on, ordinary visitor traffic occasionally triggers due jobs. It is a safety net for hosting with no cron at all, not a plan: on a quiet site jobs run late or not at all, which means auctions closing late. Turn it on only if you have no alternative.'],

                ['type' => 'h', 'text' => 'Checking that it works'],
                ['type' => 'steps' , 'items' => [
                    ['title' => 'Wait two minutes after adding the cron entry', 'text' => 'Give it a chance to fire.'],
                    ['title' => 'Open Admin → System → Scheduler', 'text' => 'Every job is listed with its last run time, status, duration and any message.'],
                    ['title' => 'Confirm recent runs', 'text' => 'Auction lifecycle and Notification queue should show a run within the last minute or two.'],
                    ['title' => 'Check the health page', 'text' => 'Admin → System → Health flags "Scheduler has never run" prominently. On a fresh install this is the one failing check, and it should clear once cron is working.'],
                ]],
                ['type' => 'link', 'to' => 'admin/cron', 'text' => 'Open the Scheduler monitor', 'icon' => 'bi-activity'],
                ['type' => 'tip', 'text' => 'You can run any job by hand from the Scheduler page. Useful for testing without waiting for the clock.'],
            ],
        ],

        [
            'slug' => 'general-settings',
            'title' => 'General settings',
            'summary' => 'Site name, contact details, currency, timezone and social links.',
            'minutes' => 4,
            'keywords' => ['general', 'site name', 'logo', 'favicon', 'contact', 'timezone', 'currency', 'tagline'],
            'body' => [
                ['type' => 'p', 'text' => 'These appear across the public site, in emails and on invoices. Fill them in before anyone else sees the platform.'],
                ['type' => 'link', 'to' => 'admin/settings?group=general', 'text' => 'Open General settings', 'icon' => 'bi-gear'],

                ['type' => 'settings', 'intro' => 'Identity', 'rows' => [
                    ['Site Name', 'Your platform\'s name. It is used in the header, page titles, emails, SMS and invoices — all from this one setting, so changing it here changes it everywhere. The About, Terms and policy pages follow it too.'],
                    ['Tagline', 'One line under the name on the home page.'],
                    ['Logo', 'Shown in the header in place of the name. A plain logo on a transparent or white background, around 180×48 pixels.'],
                    ['Favicon', 'The small icon in the browser tab. A square image, 64×64 or larger.'],
                ]],

                ['type' => 'settings', 'intro' => 'Contact — shown publicly, so use details you want traders to use', 'rows' => [
                    ['Contact Email', 'Your support address. Appears in the header, footer and on the contact page.'],
                    ['Contact Phone', 'A number someone will answer. In this trade, a platform with no answered phone loses trust quickly.'],
                    ['WhatsApp Number', 'Enables a WhatsApp contact link.'],
                    ['Registered Address', 'Your business address. Required on invoices.'],
                ]],

                ['type' => 'settings', 'intro' => 'Regional', 'rows' => [
                    ['Default Language', 'For visitors who have not chosen one. Members override it in their profile. This is a dropdown of the languages actually installed, so you cannot pick one with no translations.'],
                    ['Currency Code', '<code>INR</code> for India.'],
                    ['Currency Symbol', '<code>₹</code>.'],
                    ['Display Timezone', '<code>Asia/Kolkata</code> for India. Times are stored in UTC and shown in this zone, so changing it never alters a stored record — it only changes how times are displayed.'],
                ]],

                ['type' => 'settings', 'intro' => 'Social links — leave blank to hide the icon', 'rows' => [
                    ['Facebook / LinkedIn / X / YouTube URL', 'Full URLs including <code>https://</code>.'],
                ]],
                ['type' => 'tip' , 'text' => 'Also fill in the SEO group — default meta title, description and keywords. It is what search engines show, and it costs five minutes.'],
            ],
        ],

        [
            'slug' => 'email-sms-and-whatsapp',
            'title' => 'Email, SMS and WhatsApp',
            'summary' => 'Configuring each channel, and how to test that messages really go out.',
            'minutes' => 8,
            'keywords' => ['email', 'smtp', 'mail', 'sms', 'whatsapp', 'otp', 'notifications', 'dlt', 'test email', 'email not working', 'otp not sending', 'smtp settings'],
            'body' => [
                ['type' => 'p', 'text' => 'Three channels, configured separately. Email is essential. SMS matters a great deal if you want mobile verification and outbid alerts to work. WhatsApp is optional.'],
                ['type' => 'note', 'text' => 'Any channel without credentials is reported honestly: messages are queued and then marked <strong>skipped</strong> with a reason, never silently dropped and never shown as sent. You can see this in Admin → Content → Queue.'],

                ['type' => 'h', 'text' => 'Email'],
                ['type' => 'p', 'text' => 'Used for password resets, order confirmations, invoices and dispute updates. Without it, a member who forgets their password cannot get back in.'],
                ['type' => 'settings', 'intro' => 'Settings → Mail', 'rows' => [
                    ['Mail driver', '<code>smtp</code> is strongly recommended. The <code>mail</code> driver uses the server\'s own sendmail, which on shared hosting usually lands in spam.'],
                    ['From address', 'An address at your own domain — <code>noreply@yourdomain.com</code>. A Gmail or Yahoo from-address will be rejected or spam-filed by most recipients.'],
                    ['From name', 'Your platform name.'],
                    ['SMTP host', 'From your email provider, e.g. <code>smtp.yourdomain.com</code>.'],
                    ['SMTP port', '587 for TLS, 465 for SSL.'],
                    ['SMTP username / password', 'Usually the full email address and its password. The password is stored encrypted and never shown back to you.'],
                    ['SMTP encryption', '<code>tls</code> for port 587, <code>ssl</code> for 465.'],
                ]],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Fill in the settings and save', 'text' => ''],
                    ['title' => 'Send a test email', 'text' => 'There is a <strong>Send a test to…</strong> box at the top of the Mail settings page. Put in your own address and send.'],
                    ['title' => 'Check both inbox and spam', 'text' => 'If it lands in spam, add SPF and DKIM records for your domain. Your email provider will give you the exact values; this is the single most effective fix for deliverability.'],
                ]],
                ['type' => 'link', 'to' => 'admin/settings?group=mail', 'text' => 'Open Mail settings', 'icon' => 'bi-envelope'],

                ['type' => 'h', 'text' => 'SMS'],
                ['type' => 'p', 'text' => 'Carries one-time codes for mobile verification and time-critical alerts such as being outbid. Without SMS, new members cannot verify their number themselves — you would have to do it for them from the admin panel.'],
                ['type' => 'settings', 'intro' => 'Settings → SMS', 'rows' => [
                    ['SMS provider', 'Which gateway you have an account with.'],
                    ['SMS API endpoint', 'The URL your provider gives you.'],
                    ['SMS API key', 'Your key. Stored encrypted.'],
                    ['Sender ID', 'Your approved six-character header, e.g. <code>SCRPTR</code>.'],
                    ['DLT template id', 'Required in India. Every transactional SMS must use a template registered on the DLT platform, or the operator will block it.'],
                ]],
                ['type' => 'warning', 'text' => 'Indian SMS regulation (TRAI DLT) requires your sender ID and every message template to be registered before anything is delivered. Allow a few working days for that approval — start it early, because nothing you configure here works until it is done.'],
                ['type' => 'link', 'to' => 'admin/settings?group=sms', 'text' => 'Open SMS settings', 'icon' => 'bi-chat-text'],

                ['type' => 'h', 'text' => 'WhatsApp'],
                ['type' => 'p', 'text' => 'Optional, through the WhatsApp Business Cloud API. Needs a Meta Business account and approved message templates.'],
                ['type' => 'settings', 'intro' => 'Settings → WhatsApp', 'rows' => [
                    ['WhatsApp provider', 'Which integration you are using.'],
                    ['Phone number ID', 'From your Meta Business dashboard.'],
                    ['Business account ID', 'Also from Meta Business.'],
                    ['Access token', 'A permanent token, not a temporary test one. Stored encrypted.'],
                ]],

                ['type' => 'h', 'text' => 'Turning channels on and off'],
                ['type' => 'settings', 'intro' => 'Settings → Notifications', 'rows' => [
                    ['Email / SMS / WhatsApp notifications enabled', 'Master switches. Leave a channel off until it is configured and tested.'],
                    ['Queue batch size per run', 'How many messages are delivered each time the queue job runs. Raise it on a busy site; lower it if your provider rate-limits you.'],
                    ['Max delivery attempts', 'How many times a failed message is retried before being abandoned.'],
                    ['Notify bidders when outbid', 'Keep this on. It is what keeps auctions competitive.'],
                    ['Ending-soon alert (minutes before close)', 'How long before an auction closes bidders are warned.'],
                ]],
                ['type' => 'link', 'to' => 'admin/notifications', 'text' => 'Open the notification queue', 'icon' => 'bi-send'],
                ['type' => 'tip', 'text' => 'After configuring any channel, watch the queue for a few minutes. A message stuck as pending, or marked skipped with a reason, tells you precisely what is wrong.'],
            ],
        ],

        [
            'slug' => 'commission-and-fees',
            'title' => 'Commission and fees',
            'summary' => 'How the platform earns, and the one rule that keeps traders from feeling cheated.',
            'minutes' => 5,
            'keywords' => ['commission', 'fees', 'revenue', 'percentage', 'buyer fee', 'seller fee', 'listing fee'],
            'body' => [
                ['type' => 'p', 'text' => 'Commission is charged on completed deals. It is calculated on the <strong>settled</strong> order value after weighment — never on the original estimate — so a short load never produces a commission bill that looks wrong.'],
                ['type' => 'link', 'to' => 'admin/settings?group=commission', 'text' => 'Open Commission settings', 'icon' => 'bi-percent'],

                ['type' => 'settings', 'intro' => 'Settings → Commission', 'rows' => [
                    ['Charge platform commission', 'The master switch. Turn it off to run the marketplace free, for example while you are building up traffic.'],
                    ['Sale commission (%)', 'The main rate. Between 0.5% and 2% is normal for bulk commodity trading; much above that and traders will take deals off-platform.'],
                    ['Fixed commission (₹)', 'A flat amount instead of, or alongside, the percentage.'],
                    ['Commission charged to', 'Seller, buyer, or split. Charging the seller is the usual convention in scrap.'],
                    ['Buyer fee (%) / Seller fee (%)', 'Separate fees on each side, if you want to split rather than use one commission.'],
                    ['Auction success fee (%)', 'An extra fee on auction deals, which take more platform resource to run.'],
                    ['Listing fee (₹)', 'Charged per listing. Think hard before setting this — a listing fee on an empty marketplace stops it filling up.'],
                    ['Featured listing fee (₹)', 'For promoted placement. Much easier to sell than a listing fee, because the seller sees the benefit.'],
                    ['GST on commission (%)', '18% in India. Commission is a service you are supplying, so it is taxable.'],
                    ['Minimum commission (₹)', 'A floor, so tiny deals still cover your costs.'],
                    ['Maximum commission (₹)', 'A ceiling. Worth setting — on a ₹50 lakh deal an uncapped percentage can look extortionate and push the deal off-platform.'],
                ]],

                ['type' => 'h', 'text' => 'Choosing your rates'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Start low, or free', 'text' => 'An empty marketplace has no value to anyone. Liquidity first, revenue second.'],
                    ['title' => 'Charge one side, not both', 'text' => 'Two fees on one deal feel like three. Pick the side that gets more value from the platform.'],
                    ['title' => 'Cap it', 'text' => 'Always set a maximum. It is what keeps your largest and most valuable traders on the platform.'],
                    ['title' => 'Publish it', 'text' => 'Rewrite the Commission Policy page with your actual rates. Hidden fees are the fastest way to lose a trading community.'],
                ]],
                ['type' => 'p', 'text' => 'Commission is shown on every order before it is confirmed and recorded in a ledger both you and the trader can see, so nothing appears as a surprise afterwards.'],
                ['type' => 'link', 'to' => 'admin/commissions', 'text' => 'Commission ledger', 'icon' => 'bi-percent'],
            ],
        ],

        [
            'slug' => 'invoices-and-gst',
            'title' => 'Invoices and GST',
            'summary' => 'Your GSTIN, state code, numbering and invoice terms. Set these before the first invoice is raised.',
            'minutes' => 4,
            'keywords' => ['invoice', 'gst', 'gstin', 'state code', 'prefix', 'numbering', 'terms', 'hsn'],
            'body' => [
                ['type' => 'p', 'text' => 'These control the tax invoice the platform produces for commission, and the invoice layout used for trades.'],
                ['type' => 'link', 'to' => 'admin/settings?group=invoice', 'text' => 'Open Invoice settings', 'icon' => 'bi-receipt'],

                ['type' => 'settings', 'intro' => 'Settings → Invoice', 'rows' => [
                    ['Platform GSTIN', 'Your own GST number. Required before you can invoice commission.'],
                    ['Platform GST state code', 'The first two digits of your GSTIN — 24 for Gujarat, 27 for Maharashtra, 29 for Karnataka and so on. This is what decides CGST+SGST against IGST, so it must be right.'],
                    ['Invoice number prefix', 'For example <code>INV/</code>. Numbering becomes <code>INV/2026-27/000001</code>, sequential per Indian financial year.'],
                    ['Starting serial', 'Where numbering begins. Set this if you are migrating from another system and need to continue an existing series.'],
                    ['Invoice terms', 'Your payment terms, printed on every invoice.'],
                    ['Invoice footer', 'A single closing line — jurisdiction, a declaration, your website.'],
                ]],

                ['type' => 'h', 'text' => 'How the tax is decided'],
                ['type' => 'p', 'text' => 'From the state codes in both parties\' GSTINs: same state means CGST + SGST at half the rate each, different states mean IGST at the full rate. The platform works this out per invoice, so you never choose it manually.'],
                ['type' => 'warning', 'text' => 'Get the prefix and starting serial right before the first invoice is raised. Invoice numbering must be unbroken and sequential for GST purposes, and changing it after invoices exist creates a gap you will have to explain.'],
                ['type' => 'p', 'text' => 'GST rates per material come from the HSN codes in the catalogue — <strong>Admin → Marketplace → Units &amp; HSN</strong>. Check the rates against your own tax advice before going live; they ship as sensible defaults, not as tax advice.'],
                ['type' => 'note', 'text' => 'The platform produces tax invoices. It does not file returns and does not generate e-invoices (IRN) or e-way bills.'],
            ],
        ],

        [
            'slug' => 'payments-and-bank-details',
            'title' => 'Payments and bank details',
            'summary' => 'Which payment methods traders may use, your own bank details, and connecting a gateway.',
            'minutes' => 5,
            'keywords' => ['payments', 'razorpay', 'gateway', 'upi', 'bank', 'ifsc', 'wallet', 'escrow'],
            'body' => [
                ['type' => 'p', 'text' => 'By default buyers and sellers settle directly with each other and record the payment against the order. The platform does not hold trading money unless you deliberately enable wallets.'],
                ['type' => 'link', 'to' => 'admin/settings?group=payments', 'text' => 'Open Payment settings', 'icon' => 'bi-cash-coin'],

                ['type' => 'settings', 'intro' => 'Settings → Payments', 'rows' => [
                    ['Active gateway', 'Leave as none to run manual-only. Set to Razorpay once you have keys.'],
                    ['Enabled payment methods', 'Which methods traders may record — UPI, NEFT, RTGS, IMPS, bank transfer, cheque, cash and so on. Turn off anything you do not want used.'],
                    ['Razorpay key id / key secret', 'From your Razorpay dashboard. The secret is stored encrypted and never shown back.'],
                    ['Razorpay webhook secret', 'Lets Razorpay confirm payments back to the platform automatically. Without it you will be confirming online payments by hand.'],
                    ['Platform UPI ID', 'For collecting commission and subscription payments.'],
                    ['Bank account name / number / IFSC / Bank name', 'Your own details, for payments made to the platform.'],
                    ['Enable user wallets', 'Off by default. Only turn this on if you understand the responsibility of holding other people\'s money.'],
                ]],

                ['type' => 'h', 'text' => 'Connecting Razorpay'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Create a Razorpay account and complete their KYC', 'text' => 'Test keys work immediately; live keys need their approval.'],
                    ['title' => 'Copy the key id and secret', 'text' => 'From Settings → API Keys in the Razorpay dashboard.'],
                    ['title' => 'Paste them in and save', 'text' => 'Then set the active gateway to Razorpay.'],
                    ['title' => 'Add the webhook', 'text' => 'In Razorpay, add a webhook pointing at your site\'s Razorpay webhook endpoint, and put the same signing secret in the settings here. This is what makes automatic confirmation work.'],
                    ['title' => 'Test with a small live payment', 'text' => 'Not a test-mode one. Confirm it appears against the order and the status updates.'],
                ]],
                ['type' => 'note', 'text' => 'With no gateway configured, the platform says so plainly and offers manual recording instead. It never shows a Pay button that cannot work.'],
                ['type' => 'tip', 'text' => 'Most Indian scrap trading settles by RTGS and NEFT against a weighbridge slip. A gateway is genuinely optional here — manual recording with a UTR is what traders expect, and it costs you nothing in gateway fees.'],
            ],
        ],

        [
            'slug' => 'marketplace-rules',
            'title' => 'Marketplace rules',
            'summary' => 'Approval, expiry periods, upload limits and what guests may see.',
            'minutes' => 5,
            'keywords' => ['marketplace', 'approval', 'moderation', 'expiry', 'uploads', 'images', 'guest'],
            'body' => [
                ['type' => 'p', 'text' => 'These decide how much friction there is between a seller and a live listing. Start strict while you learn your traders, then loosen.'],
                ['type' => 'link', 'to' => 'admin/settings?group=marketplace', 'text' => 'Open Marketplace settings', 'icon' => 'bi-shop'],

                ['type' => 'settings', 'intro' => 'Moderation', 'rows' => [
                    ['Listings need admin approval', 'On is the right choice at launch: you see every listing before a trader does, and you catch the junk. Expect to check listings daily, or sellers will leave.'],
                    ['Auto-approve KYC-verified sellers', 'The best compromise. Verified businesses publish instantly; everyone else is queued. Turn this on as soon as you have verified sellers.'],
                ]],

                ['type' => 'settings', 'intro' => 'Expiry', 'rows' => [
                    ['Listing expiry (days)', 'How long a listing stays live. 30 days suits most scrap, which moves quickly. Too long and your marketplace fills with material that was sold weeks ago — the fastest way to lose buyer trust.'],
                    ['Requirement expiry (days)', 'The same for buyer requirements.'],
                    ['Offer validity (hours)', 'How long an offer can be accepted. 48 to 72 hours is reasonable; it keeps negotiations moving without being unfair.'],
                ]],

                ['type' => 'settings', 'intro' => 'Display', 'rows' => [
                    ['Listings per page', '20 to 24 works well on both phone and desktop.'],
                    ['Guests can browse listings', 'Keep on. Search engines need to see your listings, and that is where new traders come from.'],
                    ['Guest preview limit', 'How many a visitor sees before being asked to register. A reasonable trade between discovery and sign-ups.'],
                ]],

                ['type' => 'settings', 'intro' => 'Attachments', 'rows' => [
                    ['Max images per listing', '8 to 12. Photographs are what sell scrap, so be generous here.'],
                    ['Max videos / documents per listing', 'Videos are large; keep the limit low unless your hosting is comfortable.'],
                ]],

                ['type' => 'settings', 'intro' => 'Settings → Uploads', 'rows' => [
                    ['Max image size (MB)', 'Phone cameras produce 4–8 MB files. Allowing 8 MB avoids constant complaints; images are re-encoded smaller on upload anyway.'],
                    ['Max document / video size (MB)', 'Keep within what your host\'s own PHP limits allow, or uploads fail with a confusing error.'],
                    ['Image re-encode quality', '80 to 85 is the sweet spot — visually indistinguishable from the original at a fraction of the size.'],
                    ['Max image width (px)', '1600px is plenty for a listing photograph and keeps pages fast on a phone connection.'],
                ]],
                ['type' => 'warning', 'text' => 'Your host\'s PHP settings (<code>upload_max_filesize</code> and <code>post_max_size</code>) override anything you set here. Setting a 20 MB limit when PHP allows 8 MB produces failed uploads, not large ones. Check the Health page, which reports the real PHP limits.'],
            ],
        ],

        [
            'slug' => 'auction-defaults',
            'title' => 'Auction defaults',
            'summary' => 'The increments, anti-sniping window and eligibility rules new auctions start with.',
            'minutes' => 4,
            'keywords' => ['auction', 'defaults', 'increment', 'anti sniping', 'extension', 'mask bidders', 'poll'],
            'body' => [
                ['type' => 'p', 'text' => 'These are the values a seller sees pre-filled when they create an auction. They can change most of them; good defaults mean most never need to.'],
                ['type' => 'link', 'to' => 'admin/settings?group=auction', 'text' => 'Open Auction settings', 'icon' => 'bi-hammer'],

                ['type' => 'settings', 'intro' => 'Anti-sniping — the part that matters most', 'rows' => [
                    ['Auto-extension window (seconds)', 'A bid landing within this much of the close pushes the close back. 120 seconds is a sensible default.'],
                    ['Extension duration (seconds)', 'How much time such a bid adds. 120 seconds again works well.'],
                    ['Maximum extensions', 'A ceiling so an auction cannot run forever. 10 is reasonable.'],
                ]],
                ['type' => 'p', 'text' => 'Without this, whoever has the fastest connection wins rather than whoever values the material most — and the serious bidders leave. Do not set the window to zero.'],

                ['type' => 'settings', 'intro' => 'Bidding', 'rows' => [
                    ['Default bid increment (₹)', 'What a seller gets pre-filled. Roughly 1% of a typical lot value.'],
                    ['Minimum allowed increment (₹)', 'A floor, so nobody sets an increment of ₹1 and turns the auction into a thousand bids.'],
                    ['Max bids per minute per user', 'Stops automated bidding. 20 to 40 is comfortable for a human.'],
                ]],

                ['type' => 'settings', 'intro' => 'Eligibility and display', 'rows' => [
                    ['Bidders must be KYC verified', 'A platform-wide requirement. Strict, but it raises the quality of every auction. Sellers can also require it per auction.'],
                    ['Mask bidder identity publicly', 'Shows bidders as "Bidder 3" rather than by name. Recommended — it prevents collusion and stops bidders being approached off-platform.'],
                    ['Live poll interval (ms)', 'How often the auction page refreshes itself. 3000–5000ms is a good balance; lower values add server load for little benefit.'],
                    ['Enable reverse auctions', 'Lets buyers run auctions where sellers bid the price down. Leave off unless you have asked for it.'],
                ]],
                ['type' => 'danger', 'text' => 'Auctions are closed and awarded by the scheduler. If the scheduler is not running, none of this matters — auctions will simply never close. See <em>Set up the scheduler</em>.'],
            ],
        ],

        [
            'slug' => 'security-and-access-rules',
            'title' => 'Security and access rules',
            'summary' => 'What a new account must do before it can trade, lockout rules, and maintenance mode.',
            'minutes' => 6,
            'keywords' => ['security', 'approval', 'verification', 'otp', 'lockout', 'session', 'maintenance mode', 'password'],
            'body' => [
                ['type' => 'p', 'text' => 'This group decides how much a new account must prove before it can list or bid. Tighter means fewer fake accounts and more work for you; looser means faster growth and more junk.'],
                ['type' => 'link', 'to' => 'admin/settings?group=security', 'text' => 'Open Security settings', 'icon' => 'bi-shield-lock'],

                ['type' => 'settings', 'intro' => 'What new accounts must do', 'rows' => [
                    ['Require mobile OTP verification', 'Keep this on. It is the cheapest and most effective barrier against fake accounts. Needs SMS configured.'],
                    ['Require email verification', 'Useful, though in this trade mobile matters more than email.'],
                    ['New accounts need admin approval', 'Every registration waits for you. Right for a curated, invitation-style marketplace; too slow for open growth. If you turn it on, check the queue daily.'],
                    ['Require KYC before trading', 'The strictest setting: no listing or bidding until documents are approved. It produces a high-trust marketplace and a slow start. Consider turning it on only for auctions instead.'],
                ]],

                ['type' => 'settings', 'intro' => 'Sign-in protection', 'rows' => [
                    ['Max login attempts', 'Wrong passwords before sign-in is locked. 5 is standard.'],
                    ['Login lockout window (seconds)', 'How long the lock lasts. 900 (15 minutes) is enough to defeat guessing without punishing a genuine mistake.'],
                    ['Minimum password length', '10 or more. Length is what makes a password strong.'],
                    ['Session lifetime (minutes)', 'How long before an idle member is signed out. Traders find a short session annoying; 1440 (a day) is a fair balance.'],
                ]],

                ['type' => 'settings', 'intro' => 'One-time codes', 'rows' => [
                    ['OTP length', '6 digits.'],
                    ['OTP validity (minutes)', '10 minutes. Indian SMS can be slow, so do not go below 5.'],
                    ['Max OTP verify attempts', 'Wrong entries before a new code is required. 3 to 5.'],
                    ['Max OTP sends per hour', 'Stops someone being spammed, and stops your SMS credit being burned.'],
                ]],
                ['type' => 'note', 'text' => 'Codes are stored hashed, never in readable form. If no SMS provider is configured, the code is written to the log with the destination number masked — so you can still help a member during setup without the code being exposed in an email or on screen.'],

                ['type' => 'h', 'text' => 'Maintenance mode'],
                ['type' => 'p', 'text' => 'Puts the public site behind a notice while you work. Use it before a database change or a major update.'],
                ['type' => 'settings', 'rows' => [
                    ['Maintenance mode', 'The switch itself.'],
                    ['Maintenance message', 'What visitors see. Give a realistic time — "back by 3pm" is far better than "temporarily unavailable".'],
                    ['Maintenance bypass IPs', 'Addresses that can still browse the public site normally. Put your own office IP here.'],
                ]],
                ['type' => 'tip', 'text' => 'You cannot lock yourself out. Sign-in, sign-out, the admin panel and the scheduler stay reachable in maintenance mode, so the switch that turns it off is always available. A banner across the top of the admin panel reminds you it is on, with a link to turn it off.'],
                ['type' => 'link', 'to' => 'admin/settings?group=security', 'text' => 'Maintenance mode is in Security settings', 'icon' => 'bi-cone-striped'],
            ],
        ],

        [
            'slug' => 'content-catalogue-and-staff',
            'title' => 'Content, catalogue and staff',
            'summary' => 'Rewriting the shipped pages, extending the scrap catalogue, and giving your team the right access.',
            'minutes' => 6,
            'keywords' => ['pages', 'cms', 'faq', 'terms', 'catalogue', 'materials', 'hsn', 'roles', 'permissions', 'staff', 'import', 'pincode', 'pincodes', 'csv', 'bulk upload'],
            'body' => [
                ['type' => 'h', 'text' => 'Rewrite the shipped pages'],
                ['type' => 'p', 'text' => 'The platform installs About, Terms &amp; Conditions, Privacy, KYC Policy, Commission Policy and How It Works as working placeholders. They are readable, but they are not your legal terms.'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Open Admin → Content → Pages', 'text' => 'Each page has a rich-text editor.'],
                    ['title' => 'Replace the Terms and Privacy text with your own', 'text' => 'Take legal advice. These are the pages that matter if a deal ends badly.'],
                    ['title' => 'Put your real rates in the Commission Policy', 'text' => 'Traders will look for them.'],
                    ['title' => 'Write the About page in your own words', 'text' => 'Who you are, where you operate, why a trader should use you.'],
                ]],
                ['type' => 'tip', 'text' => 'Write <code>{{site_name}}</code> anywhere in a page and it is replaced with your site name when the page is shown. That way renaming the platform later updates these pages automatically.'],
                ['type' => 'link', 'to' => 'admin/pages', 'text' => 'Edit content pages', 'icon' => 'bi-file-earmark-richtext'],
                ['type' => 'p', 'text' => 'FAQs are separate, at <strong>Admin → Content → FAQs</strong>, grouped by category and shown on the public FAQ page and the home page. Add the questions your traders actually ask; it reduces support calls more than anything else on this list.'],

                ['type' => 'h', 'text' => 'The scrap catalogue'],
                ['type' => 'p', 'text' => 'The platform ships 23 categories and 104 materials covering ferrous, non-ferrous, e-waste, plastic, paper and rubber. Review it against what your traders actually deal in.'],
                ['type' => 'list', 'items' => [
                    '<strong>Categories</strong> — the top-level groups buyers browse.',
                    '<strong>Materials</strong> — the individual items, each with an HSN code and GST rate. Check these against your tax advice.',
                    '<strong>Grades</strong> — variants within a material, such as HMS 1 and HMS 2. Grade is what buyers filter on hardest, so it is worth getting right.',
                    '<strong>Units &amp; HSN</strong> — units of measure and the HSN codes that drive GST.',
                ]],
                ['type' => 'link', 'to' => 'admin/catalog', 'text' => 'Manage the catalogue', 'icon' => 'bi-diagram-3'],

                ['type' => 'h', 'text' => 'Bulk import by CSV'],
                ['type' => 'p', 'text' => 'Adding things one at a time is slow. <strong>Admin → System → Export / Data</strong> imports categories, materials, grades, market rates, cities and PIN codes from CSV.'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Download the template for the type you are importing', 'text' => 'It has the exact column headings expected.'],
                    ['title' => 'Fill it in', 'text' => 'Keep the headings unchanged.'],
                    ['title' => 'Run it as a dry run first', 'text' => 'You get a row-by-row report of what would happen, with errors named, and nothing is written.'],
                    ['title' => 'Then import for real', 'text' => ''],
                ]],
                ['type' => 'p', 'text' => 'The PIN code table ships <strong>empty</strong> by design — the platform seeds states and the trading-hub cities, but the full PIN list is far too large and too regional to bundle. Until you import it, typing a PIN code will not auto-fill city and state; the form simply asks the member to choose the city, which is honest rather than broken. Import the PIN codes for the regions you actually trade in and the auto-fill starts working immediately.'],
                ['type' => 'link', 'to' => 'admin/export', 'text' => 'Import and export data', 'icon' => 'bi-upload'],

                ['type' => 'h', 'text' => 'Staff and permissions'],
                ['type' => 'p', 'text' => 'Ten roles ship ready to use: Super Admin, Admin, Moderator, KYC Manager, Finance Manager, Support Manager, plus the trading roles. Each carries its own set of permissions.'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Create an account for each member of staff', 'text' => 'Never share one login. The audit trail is worthless if three people use the same account.'],
                    ['title' => 'Give each the narrowest role that fits', 'text' => 'Your KYC reviewer needs KYC Manager, not Admin.'],
                    ['title' => 'Keep Super Admin to yourself', 'text' => 'One or two people at most.'],
                    ['title' => 'Check the audit log', 'text' => 'Admin → System → Audit records who changed what and when.'],
                ]],
                ['type' => 'link', 'to' => 'admin/roles', 'text' => 'Roles and permissions', 'icon' => 'bi-person-badge'],
            ],
        ],
    ],
];
