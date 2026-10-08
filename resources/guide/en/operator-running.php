<?php

declare(strict_types=1);

/** Help Centre — Day-to-day operation for whoever runs the platform. */
return [
    'title' => 'Running the platform',
    'summary' => 'The daily work: approvals, KYC, disputes, backups, updates and knowing when something is wrong.',
    'icon' => 'bi-shield-lock',
    'audience' => 'operator',
    'articles' => [

        [
            'slug' => 'your-daily-routine',
            'title' => 'Your daily routine',
            'summary' => 'A fifteen-minute pass that keeps the marketplace healthy, in the order to do it.',
            'minutes' => 4,
            'keywords' => ['daily', 'routine', 'checklist', 'moderation', 'queue', 'admin dashboard'],
            'body' => [
                ['type' => 'p', 'text' => 'The admin dashboard shows live counters for everything waiting on you — pending KYC, listings awaiting approval, open disputes, open reports and fraud flags. They sit next to the menu items, so you can see what needs attention without opening anything.'],
                ['type' => 'link', 'to' => 'admin', 'text' => 'Open the admin dashboard', 'icon' => 'bi-speedometer2'],

                ['type' => 'h', 'text' => 'Every day'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Approve pending listings', 'text' => 'Do this first and do it early. A seller whose listing sits unapproved for two days does not come back. Aim to clear the queue within a few hours.'],
                    ['title' => 'Review KYC submissions', 'text' => 'The highest-value thing you do. Verified traders are the ones who complete large deals.'],
                    ['title' => 'Check open disputes', 'text' => 'Especially any at "Evidence requested" — that is the stage disputes stall at, and a stalled dispute costs you two traders.'],
                    ['title' => 'Glance at the notification queue', 'text' => 'Anything stuck pending, or a run of failures, means a provider problem you want to know about today.'],
                    ['title' => 'Glance at the scheduler', 'text' => 'Confirm Auction lifecycle and Notification queue ran in the last couple of minutes.'],
                ]],

                ['type' => 'h', 'text' => 'Every week'],
                ['type' => 'list', 'items' => [
                    'Update market rates, or import them by CSV. Traders check the rate page and stale rates make the whole platform look abandoned.',
                    'Open the Health page and work through anything flagged.',
                    'Check that a backup exists and <strong>download one off-server</strong>.',
                    'Read the fraud flags and content reports.',
                    'Skim new business profiles for junk registrations.',
                ]],

                ['type' => 'h', 'text' => 'Every month'],
                ['type' => 'list', 'items' => [
                    'Check for application updates.',
                    'Review your commission ledger against what you have actually collected.',
                    'Read the error log for anything recurring.',
                    'Restore a backup into a test database. An untested backup is a hope, not a backup.',
                ]],
                ['type' => 'tip', 'text' => 'Set a recurring reminder for the listing-approval pass. It is the single task where being slow visibly costs you sellers.'],
            ],
        ],

        [
            'slug' => 'approve-listings-and-review-kyc',
            'title' => 'Approve listings and review KYC',
            'summary' => 'What to look for, what to reject, and how to write a rejection a trader can act on.',
            'minutes' => 6,
            'keywords' => ['approve', 'moderation', 'reject', 'kyc', 'verification', 'review', 'documents'],
            'body' => [
                ['type' => 'h', 'text' => 'Approving listings'],
                ['type' => 'p', 'text' => 'Where approval is required, new listings wait in <strong>Admin → Marketplace → Listings</strong> with a pending filter.'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Does it describe real material?', 'text' => 'A title, a quantity, a grade and photographs that match each other.'],
                    ['title' => 'Are the photographs genuine?', 'text' => 'Stock photographs and screenshots lifted from other sites are the commonest problem. A real yard photograph looks like a real yard photograph.'],
                    ['title' => 'Is the price plausible?', 'text' => 'Wildly below market usually means a scam or a unit mistake — per kg entered as per MT. Both are worth a message before approving.'],
                    ['title' => 'Is it in the right category?', 'text' => 'Miscategorised material is invisible to the buyers who want it. Fix it rather than rejecting it.'],
                    ['title' => 'Does it contain contact details in the description?', 'text' => 'Sellers routing buyers off-platform. Reject with an explanation of why that is against the terms.'],
                    ['title' => 'Approve, or reject with a reason', 'text' => 'The reason is shown to the seller on the listing.'],
                ]],
                ['type' => 'link', 'to' => 'admin/listings', 'text' => 'Review listings', 'icon' => 'bi-box-seam'],
                ['type' => 'tip', 'text' => 'Write rejections a seller can act on. "Please add photographs of the actual material — the current images appear to be from a search engine" gets a corrected listing. "Rejected" gets a lost seller.'],

                ['type' => 'h', 'text' => 'Reviewing KYC'],
                ['type' => 'p', 'text' => 'Submissions are in <strong>Admin → People → KYC</strong>, with the documents attached.'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Check the GST certificate', 'text' => 'Does the business name match the profile? Is the GSTIN format valid and the certificate current? Verify the GSTIN on the government portal for anything high-value.'],
                    ['title' => 'Check PAN against the business name', 'text' => 'A proprietorship will show the proprietor\'s PAN — that is normal and expected.'],
                    ['title' => 'Check business registration', 'text' => 'Udyam, incorporation certificate or partnership deed. The name must match.'],
                    ['title' => 'Check address proof', 'text' => 'Does the address match the profile? Is the document recent?'],
                    ['title' => 'Check every document is readable', 'text' => 'A blurred or cropped scan cannot be verified. Ask for a replacement rather than guessing.'],
                    ['title' => 'Approve, or reject with a specific reason', 'text' => 'Name the document and the problem.'],
                ]],
                ['type' => 'p', 'text' => 'Approving sets the business as verified, marks the GSTIN verified, and gives it the badge, higher placement and access to restricted auctions.'],
                ['type' => 'link', 'to' => 'admin/kyc', 'text' => 'Review KYC submissions', 'icon' => 'bi-patch-check'],
                ['type' => 'warning', 'text' => 'Never approve KYC on a document you could not actually read. The verified badge is the platform\'s promise to every trader who relies on it — once traders learn it means nothing, you cannot get that trust back.'],
            ],
        ],

        [
            'slug' => 'handle-disputes-and-fraud',
            'title' => 'Handle disputes and fraud',
            'summary' => 'Working a dispute to a resolution, and reading the fraud signals the platform raises.',
            'minutes' => 6,
            'keywords' => ['dispute', 'fraud', 'resolution', 'reports', 'suspend', 'evidence', 'risk'],
            'body' => [
                ['type' => 'h', 'text' => 'Working a dispute'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Read the order first, not the complaint', 'text' => 'The order history, weighment record and message thread tell you what happened. Read those before either party\'s account of it.'],
                    ['title' => 'Check the weighment', 'text' => 'Most scrap disputes are weight or deduction disputes. Is there a slip attached? Which weighbridge? Was the deduction reason recorded, and was it agreed beforehand in the thread?'],
                    ['title' => 'Ask for what is missing, once and specifically', 'text' => 'Set the dispute to "Evidence requested" and name exactly what you need. Vague requests produce vague responses and the dispute stalls.'],
                    ['title' => 'Decide on the record in front of you', 'text' => 'What was agreed in the thread usually settles it. This is why you encourage traders to agree terms in writing.'],
                    ['title' => 'Record the resolution with your reasoning', 'text' => 'Both sides see it. Reasoning is what makes a decision acceptable even to the party who lost.'],
                    ['title' => 'Act on the account if needed', 'text' => 'A trader acting in bad faith repeatedly should be suspended. One bad actor costs you many good traders.'],
                ]],
                ['type' => 'link', 'to' => 'admin/disputes', 'text' => 'Open disputes', 'icon' => 'bi-shield-check'],
                ['type' => 'p', 'text' => 'Be clear with both sides about what you can do: examine the record, decide, adjust platform commission, and act against an account. You cannot move money the platform never held, and unless you have said otherwise you are not a party to their contract.'],

                ['type' => 'h', 'text' => 'Fraud flags'],
                ['type' => 'p', 'text' => '<strong>Admin → People → Risk &amp; Fraud</strong> lists accounts the platform has flagged automatically — patterns such as several accounts from one address, a GSTIN used more than once, bidding patterns that look like collusion, or a burst of registrations.'],
                ['type' => 'p', 'text' => 'A flag is a prompt to look, not a verdict. Open the account, read its history, and decide.'],
                ['type' => 'link', 'to' => 'admin/fraud', 'text' => 'Risk and fraud flags', 'icon' => 'bi-shield-exclamation'],

                ['type' => 'h', 'text' => 'Content reports'],
                ['type' => 'p', 'text' => 'Traders can report a listing or a business. Reports are in <strong>Admin → Support → Reports</strong>. Act on them visibly — a community that sees reports acted on reports more, and that is your cheapest moderation.'],

                ['type' => 'h', 'text' => 'The warning signs worth knowing'],
                ['type' => 'list', 'items' => [
                    'A price far below market with urgency attached.',
                    'Pressure to settle off-platform, or contact details hidden in a description.',
                    'A new account listing large quantities of high-value material.',
                    'Several accounts sharing an address, a GSTIN or a bank account.',
                    'Bidding that moves in lockstep between two accounts.',
                    'A request to pay a bank account different from the one on the order.',
                ]],
            ],
        ],

        [
            'slug' => 'backups',
            'title' => 'Backups',
            'summary' => 'How to take one, how to restore one, and the rule that makes backups actually work.',
            'minutes' => 5,
            'keywords' => ['backup', 'restore', 'download', 'retention', 'disaster', 'database dump'],
            'body' => [
                ['type' => 'danger', 'text' => 'A backup that lives only on the same server as the site is not a backup. If the account is lost, suspended or compromised, both go together. <strong>Download a copy off-server every week.</strong>'],

                ['type' => 'h', 'text' => 'Taking one'],
                ['type' => 'p', 'text' => '<strong>Admin → System → Backups</strong> offers three kinds:'],
                ['type' => 'table', 'head' => ['Type', 'Contains', 'Use when'], 'rows' => [
                    ['Database', 'Every table, as SQL.', 'Before a settings change or a data import. Quick.'],
                    ['Files', 'Application files, and uploads when enabled.', 'Before editing files by hand.'],
                    ['Full', 'Both.', 'Weekly, and always before an update.'],
                ]],
                ['type' => 'link', 'to' => 'admin/backups', 'text' => 'Backups', 'icon' => 'bi-hdd'],

                ['type' => 'h', 'text' => 'Automatic backups'],
                ['type' => 'settings', 'intro' => 'Settings → Backup', 'rows' => [
                    ['Automatic scheduled backups', 'Turn on. The scheduler then takes them without you remembering.'],
                    ['Automatic backup interval (hours)', '24 for most sites.'],
                    ['Backups to keep', 'Older ones are pruned automatically. 7 to 14 is a sensible window — enough history to recover from a problem you noticed late, without filling your disk.'],
                    ['Include /uploads in full backups', 'Makes backups much larger but genuinely complete. On tight hosting, back uploads up separately by FTP instead.'],
                ]],
                ['type' => 'note', 'text' => 'Automatic backups depend on the scheduler. If cron is not running, no automatic backup is ever taken.'],

                ['type' => 'h', 'text' => 'Restoring'],
                ['type' => 'p', 'text' => 'Restore is deliberately awkward: it opens a confirmation box and you must type <code>RESTORE</code> before it will proceed. That is because restoring replaces current data with the backup\'s — anything that happened since is gone.'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Take a fresh backup first', 'text' => 'Even of the broken state. You may need to get back to it.'],
                    ['title' => 'Turn on maintenance mode', 'text' => 'So nobody is trading mid-restore.'],
                    ['title' => 'Restore the backup you want', 'text' => 'Type RESTORE to confirm.'],
                    ['title' => 'Check the site, then leave maintenance mode', 'text' => 'Sign in, open a few pages, check recent orders exist.'],
                ]],

                ['type' => 'h', 'text' => 'Test a restore before you need it'],
                ['type' => 'p', 'text' => 'Once a month, create an empty database, restore a backup into it and look at the data. Everyone believes their backups work until the day they try one. Twenty minutes a month buys you certainty.'],
            ],
        ],

        [
            'slug' => 'updates-from-github',
            'title' => 'Updates from GitHub',
            'summary' => 'Connecting the repository and applying an update from the admin panel, with automatic rollback.',
            'minutes' => 7,
            'keywords' => ['update', 'github', 'upgrade', 'version', 'token', 'rollback', 'protected paths', 'release'],
            'body' => [
                ['type' => 'p', 'text' => 'The platform updates itself from a GitHub repository. No FTP, no SSH, no command line.'],
                ['type' => 'link', 'to' => 'admin/updates', 'text' => 'Open Updates', 'icon' => 'bi-cloud-download'],

                ['type' => 'h', 'text' => 'Connecting the repository'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Push the codebase to a GitHub repository', 'text' => 'Private is fine, and usually preferable.'],
                    ['title' => 'Enter the owner and repository name', 'text' => 'The owner is the user or organisation; the repository is just its name, without the owner prefix.'],
                    ['title' => 'Choose a channel', 'text' => '<strong>Branch</strong> tracks the head of a branch, so any new commit counts as an update. <strong>Releases</strong> tracks tagged releases only, which is the safer choice for a live site.'],
                    ['title' => 'Add a token for a private repository', 'text' => 'A GitHub personal access token with <code>repo</code> scope.'],
                    ['title' => 'Test the connection', 'text' => 'The page reports what it found, so you know it works before you rely on it.'],
                ]],
                ['type' => 'warning', 'text' => 'The token is encrypted before storage and is never rendered into a page, into JavaScript, into logs, into an API response or into an error message. Re-opening the page shows only "Stored — leave blank to keep it", so nobody can read it back out of the form. Treat it as a password regardless: give it the narrowest scope that works, and revoke it if you suspect it has leaked.'],

                ['type' => 'h', 'text' => 'What happens when you apply an update'],
                ['type' => 'p', 'text' => 'Fifteen steps, in order. If any step fails, the whole update rolls back:'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Checks and preparation', 'text' => 'Verifies the repository connection, reads the remote manifest and checks compatibility — PHP version and required extensions. If your server cannot satisfy it, the update is refused <em>before</em> anything is downloaded.'],
                    ['title' => 'Full backup', 'text' => 'Database and files, before a single file is touched.'],
                    ['title' => 'Maintenance mode', 'text' => 'Turned on automatically.'],
                    ['title' => 'Download and extract', 'text' => 'The archive is fetched from GitHub and extracted to a temporary directory.'],
                    ['title' => 'Protected paths skipped', 'text' => 'Nothing in the protected list is touched — see below.'],
                    ['title' => 'Only changed files copied', 'text' => 'Checksums are compared and identical files skipped.'],
                    ['title' => 'Every file recorded', 'text' => 'With its action: added, updated, skipped because protected, skipped because identical, or failed.'],
                    ['title' => 'Migrations, cache, health check', 'text' => 'Pending database migrations are applied, the cache cleared and the health check run.'],
                    ['title' => 'Maintenance mode off, result written', 'text' => 'With the full step-by-step log.'],
                ]],

                ['type' => 'h', 'text' => 'Protected paths — never overwritten'],
                ['type' => 'code', 'text' => "config.php        .env              uploads/\nstorage/          backup/           backups/\nlogs/             user_uploads/     public/uploads/     storage/uploads/"],
                ['type' => 'p', 'text' => 'Your database credentials, your per-installation encryption key and every file your traders uploaded are safe by construction, not by convention. You can add more paths under <strong>Extra protected paths</strong>, one per line — put any file you have customised there.'],

                ['type' => 'h', 'text' => 'If an update fails'],
                ['type' => 'p', 'text' => 'The platform restores the pre-update backup — files <em>and</em> database — records the update as rolled back, and keeps the error with a step-by-step log so you can see exactly where it stopped. A successful update can also be rolled back by hand from its detail page while its backup still exists.'],

                ['type' => 'h', 'text' => 'Good practice'],
                ['type' => 'list', 'items' => [
                    'Track <strong>Releases</strong> rather than a branch on a live site.',
                    'Read the release notes before applying.',
                    'Update at a quiet hour — not while an auction is closing.',
                    'Keep "Always back up before updating" on.',
                    'Open the site afterwards and check a few pages, then the Health page.',
                ]],
                ['type' => 'tip', 'text' => 'Automatic update checking runs daily through the scheduler and tells you when something is available. It only ever checks; nothing is applied without you choosing to.'],
            ],
        ],

        [
            'slug' => 'health-logs-and-troubleshooting',
            'title' => 'Health, logs and troubleshooting',
            'summary' => 'The three pages that tell you what is wrong, and fixes for the problems that actually come up.',
            'minutes' => 7,
            'keywords' => ['health', 'logs', 'errors', 'troubleshooting', 'audit', 'not working', 'debug', '500', 'not working', 'error', '500', 'broken', 'slow'],
            'body' => [
                ['type' => 'h', 'text' => 'The Health page'],
                ['type' => 'p', 'text' => '<strong>Admin → System → Health</strong> runs two dozen checks every time you open it: PHP version and extensions, folder permissions, database connectivity and size, the real PHP upload limits, whether the scheduler has run, queue backlog, disk space, and whether each notification and payment provider is actually configured.'],
                ['type' => 'p', 'text' => 'Open it after any change to the server or the settings. It is the fastest way to find out that something you assumed was working is not.'],
                ['type' => 'link', 'to' => 'admin/health', 'text' => 'Open Health', 'icon' => 'bi-heart-pulse'],

                ['type' => 'h', 'text' => 'The error log'],
                ['type' => 'p', 'text' => '<strong>Admin → System → Logs</strong> shows the application log in the browser — no FTP needed. When a trader reports something broken, look here first and match the time.'],
                ['type' => 'p', 'text' => 'Signed-in staff also see the real cause on a 500 page itself — the exception, file and line, with a link to the log. Ordinary visitors never see any of that, only a plain apology.'],
                ['type' => 'link', 'to' => 'admin/logs', 'text' => 'Open the log', 'icon' => 'bi-journal-text'],

                ['type' => 'h', 'text' => 'The audit trail'],
                ['type' => 'p', 'text' => '<strong>Admin → System → Audit</strong> records who changed what and when — settings, approvals, KYC decisions, role changes. This is why every member of staff needs their own account.'],
                ['type' => 'link', 'to' => 'admin/audit', 'text' => 'Open the audit trail', 'icon' => 'bi-clipboard-data'],

                ['type' => 'h', 'text' => 'Problems that actually come up'],
                ['type' => 'faq', 'items' => [
                    ['q' => 'Auctions are not closing.', 'a' => 'The scheduler is not running. Open Admin → System → Scheduler and look at the last run times. See <em>Set up the scheduler</em> in Operator setup — this is by far the commonest problem on a new installation.'],
                    ['q' => 'No emails or SMS are going out.', 'a' => 'Two possibilities. Either the channel has no credentials — check the notification queue, where messages will be marked <em>skipped</em> with the reason — or the scheduler is not running, so nothing is being delivered at all. The queue page distinguishes the two.'],
                    ['q' => 'Nobody can verify their mobile number.', 'a' => 'No SMS provider is configured. Until you set one up you can verify numbers yourself from Admin → People → Users.'],
                    ['q' => 'Uploads fail, or large images are rejected.', 'a' => 'Your host\'s PHP limits are below your platform setting. The Health page reports the real <code>upload_max_filesize</code> and <code>post_max_size</code>. Raise them in your hosting panel, or lower the platform setting to match.'],
                    ['q' => 'Images and styling do not load, or forms do nothing when submitted.', 'a' => 'The site URL in your configuration disagrees with the address people are using — http against https, or www against the bare domain. The browser\'s security policy then blocks the site\'s own files and refuses form submissions. Make the configured URL match the address people actually visit.'],
                    ['q' => 'Every page except the home page says "not found".', 'a' => 'The <code>.htaccess</code> file is missing, or mod_rewrite is off. Re-upload it with hidden files visible.'],
                    ['q' => 'A page shows a 500 error.', 'a' => 'Sign in as staff and open the page again — you will see the exception, file and line directly on it. The log has the full detail.'],
                    ['q' => 'The site is slow.', 'a' => 'Check database size and disk space on the Health page, and the queue backlog. A very large notification queue with a small batch size will fall behind; raise the batch size.'],
                    ['q' => 'I am locked out of the admin panel.', 'a' => 'Use Forgot password. If email is not working yet and you have shell access, <code>php cli.php admin:password</code> resets an administrator password.'],
                    ['q' => 'I turned on maintenance mode and cannot get back in.', 'a' => 'You can. Sign-in, the admin panel and the scheduler stay reachable in maintenance mode by design. Go to the sign-in page, sign in, and the admin banner has a link to turn it off.'],
                ]],

                ['type' => 'h', 'text' => 'Command-line helpers'],
                ['type' => 'p', 'text' => 'Everything can be done through the browser, but if you do have shell access these exist:'],
                ['type' => 'code', 'text' => "php cli.php migrate            apply pending migrations\nphp cli.php migrate:status     show applied and pending\nphp cli.php cron               run the scheduler now\nphp cli.php health             run the health check\nphp cli.php backup full        take a backup\nphp cli.php admin:password     reset an administrator password\nphp cli.php routes             list every route"],
                ['type' => 'note', 'text' => 'These are a convenience for operators who have shell access. Installing and updating never require them.'],
            ],
        ],

        [
            'slug' => 'reports-and-data',
            'title' => 'Reports and data',
            'summary' => 'What the analytics show, and getting your data out as CSV.',
            'minutes' => 3,
            'keywords' => ['reports', 'analytics', 'export', 'csv', 'data', 'statistics', 'charts', 'pincode', 'csv import'],
            'body' => [
                ['type' => 'h', 'text' => 'Reports'],
                ['type' => 'p', 'text' => '<strong>Admin → Commerce → Reports</strong> covers trade volume over time, the materials and cities with the most activity, the top sellers and buyers, commission earned, order completion and dispute rates, registrations and KYC conversion.'],
                ['type' => 'p', 'text' => 'The two numbers worth watching weekly are <strong>order completion rate</strong> — of deals agreed, how many actually finish — and <strong>KYC conversion</strong>. A falling completion rate is the earliest sign of trouble in a marketplace, well before anyone complains.'],
                ['type' => 'link', 'to' => 'admin/reports', 'text' => 'Open Reports', 'icon' => 'bi-bar-chart'],

                ['type' => 'h', 'text' => 'Exporting data'],
                ['type' => 'p', 'text' => '<strong>Admin → System → Export / Data</strong> exports users, businesses, listings, orders, payments, commissions, invoices and market rates as CSV, with the filters you choose. Files open directly in Excel, including Hindi, Gujarati and other Indian-language text.'],
                ['type' => 'link', 'to' => 'admin/export', 'text' => 'Export data', 'icon' => 'bi-download'],
                ['type' => 'p', 'text' => 'The same page imports categories, materials, grades, market rates, cities and PIN codes — always with a dry-run option that reports row by row what would happen, and writes nothing. See <em>Content, catalogue and staff</em> in Operator setup.'],
                ['type' => 'tip', 'text' => 'Export your users and orders monthly and keep the files with your backups. It is a readable record that needs no software to open.'],
            ],
        ],
    ],
];
