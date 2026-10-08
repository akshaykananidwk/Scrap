<?php

declare(strict_types=1);

/** Help Centre — Managing your own account. */
return [
    'title' => 'Your account',
    'summary' => 'Profile, notifications, wallet, security and language — the settings that are yours rather than the platform\'s.',
    'icon' => 'bi-person-gear',
    'audience' => 'everyone',
    'articles' => [

        [
            'slug' => 'profile-and-preferences',
            'title' => 'Profile and preferences',
            'summary' => 'Your own details, your account type, and the language the site speaks to you in.',
            'minutes' => 3,
            'keywords' => ['profile', 'preferences', 'account type', 'language', 'email', 'mobile', 'avatar'],
            'body' => [
                ['type' => 'p', 'text' => 'Your profile is you as a person; your business profile is the company. They are separate pages because one business can have several people.'],
                ['type' => 'link', 'to' => 'dashboard/profile', 'text' => 'Edit my profile', 'icon' => 'bi-person'],

                ['type' => 'settings', 'intro' => 'What you can change here:', 'rows' => [
                    ['Full name', 'Shown to counterparties on deals.'],
                    ['Email', 'Used for notifications and password recovery. Changing it clears its verified status until you confirm the new address.'],
                    ['Alternate mobile', 'A second number counterparties can reach you on.'],
                    ['Designation', 'Your role — Proprietor, Partner, Purchase Manager. Helps the other side know who they are dealing with.'],
                    ['Account type', 'Buyer, Seller or Both. This changes which menus you see; switch to Both if you start doing the other.'],
                    ['Preferred language', 'Sets the interface language for your account on every device, rather than just this browser.'],
                    ['Profile photo', 'Optional.'],
                ]],
                ['type' => 'note', 'text' => 'Your main mobile number is your sign-in identity. To change it you must verify the new number with a one-time code.'],
            ],
        ],

        [
            'slug' => 'control-your-notifications',
            'title' => 'Control your notifications',
            'summary' => 'Which alerts reach you and on which channel, so the important ones are not lost in noise.',
            'minutes' => 3,
            'keywords' => ['notifications', 'alerts', 'email', 'sms', 'whatsapp', 'outbid', 'unsubscribe'],
            'body' => [
                ['type' => 'p', 'text' => 'Notifications tell you about things that need an answer — an offer received, being outbid, an auction about to close, a payment recorded, a dispute update.'],
                ['type' => 'link', 'to' => 'dashboard/notifications', 'text' => 'My notifications', 'icon' => 'bi-bell'],

                ['type' => 'h', 'text' => 'Channels'],
                ['type' => 'table', 'head' => ['Channel', 'Best for'], 'rows' => [
                    ['In the dashboard', 'Always on. The bell icon carries the unread count.'],
                    ['Email', 'A written record — order confirmations, invoices, dispute updates.'],
                    ['SMS', 'Time-critical alerts. Being outbid, an auction closing.'],
                    ['WhatsApp', 'The same, where the operator has configured it.'],
                ]],
                ['type' => 'p', 'text' => 'You choose which channels you want in your profile. The platform-wide availability of each channel is the operator\'s setting, so a channel can be switched off for everyone.'],

                ['type' => 'h', 'text' => 'If notifications are not arriving'],
                ['type' => 'list', 'items' => [
                    'Check the channel is enabled on your profile.',
                    'For email: check your spam folder, and that your email address is verified.',
                    'For SMS and WhatsApp: the operator must have configured a provider. If none is configured, messages are recorded as <em>skipped</em> rather than silently dropped — the platform does not pretend to have sent something it could not.',
                    'Dashboard notifications always work regardless of any provider.',
                ]],
                ['type' => 'tip', 'text' => 'If you bid in auctions, keep SMS on for outbid alerts. It is the one notification where a few minutes genuinely matters.'],
            ],
        ],

        [
            'slug' => 'keep-your-account-secure',
            'title' => 'Keep your account secure',
            'summary' => 'Passwords, sessions, the login history, and the warning signs of someone trying to get in.',
            'minutes' => 4,
            'keywords' => ['security', 'password', 'login history', 'sessions', 'hacked', 'two factor', 'signin', 'sign in', 'locked out', 'forgot password'],
            'body' => [
                ['type' => 'p', 'text' => 'Your account can commit you to binding bids and accept offers. Treat it like your bank login.'],
                ['type' => 'link', 'to' => 'dashboard/security', 'text' => 'Security settings', 'icon' => 'bi-shield-lock'],

                ['type' => 'h', 'text' => 'Passwords'],
                ['type' => 'list', 'items' => [
                    'Use a passphrase — three or four unrelated words. Longer beats complicated.',
                    'Never reuse the password from another site. Most account takeovers are a password leaked elsewhere, tried here.',
                    'Change it immediately if you suspect anyone has seen it.',
                ]],

                ['type' => 'h', 'text' => 'Login history'],
                ['type' => 'p', 'text' => 'The security page lists recent sign-ins with date, device and address. Look through it occasionally. An entry you do not recognise means: change your password now, then tell the operator.'],

                ['type' => 'h', 'text' => 'Lockout'],
                ['type' => 'p', 'text' => 'Repeated wrong passwords lock sign-in for a period. This is deliberate — it stops anyone guessing their way in. If it happens to you, wait for the window to pass or use <em>Forgot password</em>.'],

                ['type' => 'h', 'text' => 'Things staff never ask for'],
                ['type' => 'warning', 'text' => 'Nobody from the platform will ever ask for your password or a one-time code. Anyone who does is attempting fraud, whatever name or number they are calling from.'],

                ['type' => 'h', 'text' => 'Trading safely'],
                ['type' => 'list', 'items' => [
                    'Prefer KYC-verified counterparties, especially for a first deal.',
                    'Keep the negotiation in the platform\'s message thread. It is the record if anything is disputed.',
                    'Be suspicious of pressure to settle outside the platform, of an unusually low price with urgency attached, and of a request to pay a different account from the one on the order.',
                    'Check the bank details on the order itself. Never pay to details sent only over a chat app.',
                ]],
            ],
        ],

        [
            'slug' => 'your-wallet',
            'title' => 'Your wallet',
            'summary' => 'What the wallet holds, if the operator has enabled it.',
            'minutes' => 2,
            'keywords' => ['wallet', 'balance', 'credit', 'deposit', 'ledger'],
            'body' => [
                ['type' => 'note', 'text' => 'Wallets are optional. If the operator has not enabled them the wallet page says so plainly, and nothing here applies to this installation.'],
                ['type' => 'p', 'text' => 'Where enabled, the wallet is a ledger attached to your account. It can hold auction deposits, refunds, platform credits and adjustments, and every movement is listed with its reason and the order it relates to.'],
                ['type' => 'link', 'to' => 'dashboard/wallet', 'text' => 'My wallet', 'icon' => 'bi-wallet2'],
                ['type' => 'p', 'text' => 'How money gets in and out, and whether it can be withdrawn, is decided by the operator. Ask them rather than assuming — the platform does not hold trading money by default.'],
            ],
        ],

        [
            'slug' => 'change-the-language',
            'title' => 'Change the language',
            'summary' => 'Seven languages, and the difference between changing it for this browser and for your account.',
            'minutes' => 2,
            'keywords' => ['language', 'hindi', 'gujarati', 'marathi', 'bengali', 'tamil', 'punjabi', 'translate'],
            'body' => [
                ['type' => 'p', 'text' => 'The interface is available in English, हिन्दी, ગુજરાતી, मराठी, বাংলা, தமிழ் and ਪੰਜਾਬੀ.'],

                ['type' => 'h', 'text' => 'Two ways to change it'],
                ['type' => 'table', 'head' => ['Where', 'Effect'], 'rows' => [
                    ['The switcher in the top bar (on a computer) or inside the menu (on a phone)', 'Changes it immediately for this browser.'],
                    ['<strong>Preferred language</strong> in your profile', 'Changes it for your account, on every device you sign in from.'],
                ]],
                ['type' => 'link', 'to' => 'dashboard/profile', 'text' => 'Set my preferred language', 'icon' => 'bi-translate'],

                ['type' => 'h', 'text' => 'What is translated'],
                ['type' => 'p', 'text' => 'The interface — menus, buttons, labels, status words. Content written by people is shown in the language it was written in: listing titles and descriptions, messages, business profiles, and the pages an operator writes themselves.'],
                ['type' => 'p', 'text' => 'This Help Centre is translated where a translation exists. If you are reading it in English while using the site in another language, that section has not been translated yet — you will see a notice saying so.'],
            ],
        ],
    ],
];
