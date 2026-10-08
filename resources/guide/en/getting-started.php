<?php

declare(strict_types=1);

/**
 * Help Centre — Getting started.
 *
 * Block types the article template understands: p, h, steps, list, note, tip,
 * warning, settings, table, link, code, faq. See resources/views/help/_blocks.php.
 */
return [
    'title' => 'Getting started',
    'summary' => 'What this platform is, how to open an account, verify it and find your way around.',
    'icon' => 'bi-rocket-takeoff',
    'audience' => 'everyone',
    'articles' => [

        [
            'slug' => 'what-this-platform-does',
            'title' => 'What this platform does',
            'summary' => 'A marketplace where scrap sellers and buyers find each other, agree a price, and settle on actual weighbridge weight.',
            'minutes' => 4,
            'keywords' => ['overview', 'introduction', 'how it works', 'marketplace'],
            'body' => [
                ['type' => 'p', 'text' => 'This is a business-to-business scrap marketplace. Sellers put material up with photographs, weight and grade. Buyers find it through search, auctions or by posting what they need. When the two agree, the platform creates an order and walks both sides through weighment, transport, payment and the GST invoice.'],
                ['type' => 'p', 'text' => 'It is built around one fact about the scrap trade: <strong>material is sold on an estimate and paid for on actual weight</strong>. Everything in the order flow exists to make that difference visible and agreed, instead of argued about on the phone.'],

                ['type' => 'h', 'text' => 'The five ways a deal can start'],
                ['type' => 'table', 'head' => ['Route', 'Who starts it', 'Best for'], 'rows' => [
                    ['Fixed-price listing', 'Seller', 'Material with a known market rate you want to move quickly.'],
                    ['Negotiable listing / Make an offer', 'Seller, then buyer', 'Mixed or uncertain lots where the price needs a conversation.'],
                    ['Auction', 'Seller', 'Material several buyers will compete for — gets the best price.'],
                    ['Wanted requirement', 'Buyer', 'A buyer who needs a specific material regularly and wants sellers to come to them.'],
                    ['RFQ (request for quotation)', 'Buyer', 'Larger or multi-line purchases where you want formal quotes to compare.'],
                ]],

                ['type' => 'h', 'text' => 'The path every deal follows'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Listing or requirement', 'text' => 'Material is offered, or a need is posted.'],
                    ['title' => 'Bid, offer or quote', 'text' => 'The other side responds with a price.'],
                    ['title' => 'Order', 'text' => 'Once accepted, an order is created automatically with the agreed figures.'],
                    ['title' => 'Weighment', 'text' => 'The weighbridge slip is recorded. Gross, tare and net weight, plus any deduction for moisture or mud.'],
                    ['title' => 'Logistics', 'text' => 'Transport is arranged and tracked against the order.'],
                    ['title' => 'Payment', 'text' => 'The buyer records what they paid, with the UTR or reference. The seller confirms receipt.'],
                    ['title' => 'Invoice', 'text' => 'A GST invoice is raised on the settled amount, with CGST+SGST or IGST worked out from both parties\' GSTINs.'],
                    ['title' => 'Review', 'text' => 'Both sides rate the deal. Ratings build the reputation that wins the next one.'],
                ]],

                ['type' => 'tip', 'text' => 'You do not have to choose a side. One account can both buy and sell — set <strong>Account type</strong> to "Both" in your profile.'],

                ['type' => 'h', 'text' => 'Where to go next'],
                ['type' => 'list', 'items' => [
                    'Opening an account: <em>Create your account</em>, below.',
                    'Selling your first lot: the <em>Selling</em> section.',
                    'Buying: the <em>Buying</em> section.',
                    'If you run this platform rather than trade on it: the <em>Operator setup</em> section.',
                ]],
            ],
        ],

        [
            'slug' => 'create-your-account',
            'title' => 'Create your account',
            'summary' => 'Registration asks for you, your business and your GST details in one form. It takes about five minutes.',
            'minutes' => 5,
            'keywords' => ['register', 'sign up', 'registration', 'new account', 'gstin', 'pincode', 'pin code', 'signup'],
            'body' => [
                ['type' => 'p', 'text' => 'Registration is free. One business, one account — accounts found running several identities to manipulate bidding can be suspended.'],
                ['type' => 'link', 'to' => 'register', 'text' => 'Open the registration page', 'icon' => 'bi-person-plus'],

                ['type' => 'steps', 'items' => [
                    ['title' => 'Choose what you will do', 'text' => 'Buy, Sell, or Both. This decides which menus you see. You can change it later in your profile, so pick what you expect to do most.'],
                    ['title' => 'Your details', 'text' => 'Full name, mobile number and a password. The mobile number is the one you will sign in with and the one buyers or sellers will call, so use a number you actually answer. Email is optional but strongly recommended — it is how you recover a forgotten password.'],
                    ['title' => 'Your business', 'text' => 'Business name and business type (trader, dealer, aggregator, recycler, manufacturer, transporter and so on). The type decides where you appear in the directory.'],
                    ['title' => 'Where you are', 'text' => 'State, city and PIN code. Type the six-digit PIN and the city and state fill in by themselves if that PIN is in the platform\'s list. Location matters more than you would think — most scrap buyers filter by distance, because freight decides whether a deal works.'],
                    ['title' => 'GST and PAN', 'text' => 'GSTIN, PAN and registration number. GSTIN is not compulsory to register, but it is required before an invoice can be raised, and the first two digits of your GSTIN decide whether a deal is taxed as CGST+SGST or IGST. Put it in now and you will not be chased for it later.'],
                    ['title' => 'Logo and photo', 'text' => 'Optional. A business with a logo gets noticeably more enquiries than one without.'],
                    ['title' => 'Accept the terms and submit', 'text' => 'You are signed in immediately and land on your dashboard.'],
                ]],

                ['type' => 'note', 'text' => 'Depending on how the operator has configured the platform, a new account may need administrator approval before it can trade. If so, you will see a notice on your dashboard and you only have to wait — nothing else is required of you.'],

                ['type' => 'h', 'text' => 'If something goes wrong'],
                ['type' => 'faq', 'items' => [
                    ['q' => 'It says my mobile number is already registered.', 'a' => 'That number already has an account. Use <em>Forgot password</em> to get back into it rather than making a second account.'],
                    ['q' => 'It says my GSTIN is already registered.', 'a' => 'Someone from your business has already signed up. Ask them to add you, or contact support if you believe the GSTIN has been used by someone else.'],
                    ['q' => 'My password is rejected.', 'a' => 'There is a minimum length set by the operator, shown under the field. Use a passphrase — three unrelated words are easier to remember and harder to guess than a short password with symbols in it.'],
                ]],
            ],
        ],

        [
            'slug' => 'verify-your-mobile-number',
            'title' => 'Verify your mobile number',
            'summary' => 'A one-time code proves the number is yours. Until it is verified you can browse, but not list or bid.',
            'minutes' => 2,
            'keywords' => ['otp', 'verify', 'mobile', 'sms', 'one time password', 'otp not received', 'code not coming'],
            'body' => [
                ['type' => 'p', 'text' => 'Verification exists so that everyone trading has a reachable phone number. It is usually the one thing standing between a new account and its first listing.'],

                ['type' => 'steps', 'items' => [
                    ['title' => 'Go to the verification page', 'text' => 'Your dashboard shows a banner with a link while the number is unverified.'],
                    ['title' => 'Request the code', 'text' => 'A code is sent to your mobile by SMS. It is valid for a limited time — the page tells you how long.'],
                    ['title' => 'Type the code', 'text' => 'Enter it and submit. Your account is verified at once and the banner disappears.'],
                ]],

                ['type' => 'link', 'to' => 'verify/mobile', 'text' => 'Go to mobile verification', 'icon' => 'bi-phone'],

                ['type' => 'h', 'text' => 'If the code does not arrive'],
                ['type' => 'list', 'items' => [
                    'Wait a minute. SMS delivery in India is often slow rather than failed.',
                    'Check that the number on your profile is correct, including no stray country code.',
                    'There is a limit on how many codes can be sent per hour. If you have hit it, wait and try again.',
                    'If no SMS provider has been configured on this installation yet, codes cannot be delivered at all. Contact the operator — they can verify your number from the admin panel.',
                ]],
                ['type' => 'warning', 'text' => 'Never share a one-time code with anyone, including someone claiming to be from support. Staff never need your code.'],
            ],
        ],

        [
            'slug' => 'complete-your-business-profile',
            'title' => 'Complete your business profile',
            'summary' => 'The page buyers and sellers look at before deciding whether to deal with you. Worth twenty minutes.',
            'minutes' => 6,
            'keywords' => ['business profile', 'company', 'about', 'directory'],
            'body' => [
                ['type' => 'p', 'text' => 'Your business profile is a public page in the directory. When someone receives your bid or your offer, this is what they open before replying. A thin profile loses deals to a full one at the same price.'],
                ['type' => 'link', 'to' => 'dashboard/business', 'text' => 'Edit your business profile', 'icon' => 'bi-building'],

                ['type' => 'h', 'text' => 'What to fill in, in order of how much it matters'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'About your business', 'text' => 'A few honest sentences: what you handle, how much you move in a month, how long you have been trading, and who you usually deal with. Avoid marketing language — this audience is reading for substance.'],
                    ['title' => 'Materials you deal in', 'text' => 'Pick them accurately. This is what matches you to buyer requirements, so a missing material means missed enquiries, and an over-claimed one means wasted calls.'],
                    ['title' => 'Logo', 'text' => 'A plain logo on a white background. It appears next to every listing and bid you make.'],
                    ['title' => 'Address and location', 'text' => 'Full address, city and PIN code. Buyers calculate freight from this, so an approximate address produces approximate quotes.'],
                    ['title' => 'Capacity and facilities', 'text' => 'Weighbridge on site, loading equipment, monthly tonnage. These are the questions a serious buyer asks first; answering them up front saves a phone call.'],
                    ['title' => 'GSTIN and PAN', 'text' => 'Required before any invoice can be raised against your deals.'],
                    ['title' => 'Bank details', 'text' => 'Only needed when you want to be paid by transfer. They are shown to a counterparty on a confirmed order, not published publicly.'],
                ]],

                ['type' => 'tip', 'text' => 'Fill in the weighbridge and capacity fields even if they feel unimportant. Buyers filter on them.'],
                ['type' => 'note', 'text' => 'Documents for KYC are attached to the business, not to you personally — so the business profile has to exist before you can submit KYC.'],
            ],
        ],

        [
            'slug' => 'get-kyc-verified',
            'title' => 'Get KYC verified',
            'summary' => 'Four documents, reviewed by the platform team. Verified businesses rank higher and can enter restricted auctions.',
            'minutes' => 5,
            'keywords' => ['kyc', 'verification', 'documents', 'gst certificate', 'pan', 'verified badge'],
            'body' => [
                ['type' => 'p', 'text' => 'KYC is how the platform separates real businesses from anyone who can fill in a form. It is the single highest-value thing you can do with your account.'],

                ['type' => 'h', 'text' => 'What verification gets you'],
                ['type' => 'list', 'items' => [
                    'A <strong>Verified</strong> badge on every listing, bid and offer you make.',
                    'Higher placement in search results and the business directory.',
                    'Access to auctions the seller has restricted to verified bidders — often the better material.',
                    'Larger deals. Counterparties commit more readily to a verified business.',
                ]],

                ['type' => 'h', 'text' => 'Documents required'],
                ['type' => 'table', 'head' => ['Document', 'Required', 'Notes'], 'rows' => [
                    ['GST Certificate', 'Yes', 'The registration certificate itself, not a return.'],
                    ['PAN Card', 'Yes', 'Business PAN, or the proprietor\'s PAN for a proprietorship.'],
                    ['Business Registration / Udyam', 'Yes', 'Udyam, incorporation certificate, partnership deed — whatever applies to you.'],
                    ['Address Proof', 'Yes', 'A utility bill, rent agreement or municipal document showing the trading address.'],
                    ['Shop &amp; Establishment Licence', 'Optional', 'Strengthens the application where you have one.'],
                    ['Cancelled Cheque / Bank Proof', 'Optional', 'Speeds up payment setup later.'],
                    ['Aadhaar (proprietor)', 'Optional', 'For proprietorships without separate business registration.'],
                    ['Other supporting document', 'Optional', 'Pollution board consent, trade licence, anything that helps.'],
                ]],

                ['type' => 'steps', 'items' => [
                    ['title' => 'Create the business profile first', 'text' => 'Documents attach to the business. The KYC page will tell you if it is missing.'],
                    ['title' => 'Upload each document', 'text' => 'Clear photographs are fine — a PDF is better. Make sure the whole document is in frame and the text is readable. A blurred corner is the most common reason for rejection.'],
                    ['title' => 'Check the "Missing" line', 'text' => 'The page lists what is still outstanding. The <strong>Submit for review</strong> button only appears once all four required documents are uploaded.'],
                    ['title' => 'Submit for review', 'text' => 'The status becomes <em>Pending</em>, then <em>Under review</em> when a reviewer picks it up.'],
                    ['title' => 'Wait for the outcome', 'text' => 'Usually a couple of working days. You are notified either way.'],
                ]],

                ['type' => 'link', 'to' => 'dashboard/kyc', 'text' => 'Open KYC verification', 'icon' => 'bi-patch-check'],

                ['type' => 'h', 'text' => 'If you are rejected'],
                ['type' => 'p', 'text' => 'The rejection reason is shown on the KYC page. It is nearly always a fixable problem — an unreadable scan, a document that does not match the business name, or an expired address proof. Replace the document named and submit again. There is no penalty for resubmitting.'],
                ['type' => 'note', 'text' => 'Verification can carry an expiry date. If yours is approaching, you are notified in advance and only need to re-upload what has changed.'],
            ],
        ],

        [
            'slug' => 'find-your-way-around',
            'title' => 'Find your way around',
            'summary' => 'What each menu does, on a computer and on a phone.',
            'minutes' => 4,
            'keywords' => ['navigation', 'menu', 'dashboard', 'mobile', 'where is', 'help', 'where is the menu'],
            'body' => [
                ['type' => 'h', 'text' => 'The public site'],
                ['type' => 'table', 'head' => ['Menu', 'What is there'], 'rows' => [
                    ['Buy Scrap', 'Every active listing, filterable by material, grade, location, quantity and price.'],
                    ['Auctions', 'Live and scheduled auctions with their closing countdowns.'],
                    ['Wanted', 'What buyers are asking for. Sellers should read this list regularly.'],
                    ['RFQ', 'Open requests for quotation you can quote against.'],
                    ['Scrap Rates', 'Indicative market rates by material and city, with history.'],
                    ['Businesses', 'The directory of traders, dealers, recyclers and transporters.'],
                ]],

                ['type' => 'h', 'text' => 'Your dashboard'],
                ['type' => 'p', 'text' => 'Everything that belongs to you. The left-hand menu is grouped by what you are doing:'],
                ['type' => 'list', 'items' => [
                    '<strong>Selling</strong> — My Listings, My Auctions, Offers received.',
                    '<strong>Buying</strong> — My Bids, My Requirements, RFQs, Offers made, Saved listings.',
                    '<strong>Deals</strong> — Orders, Payments, Transport, Invoices, Reviews, Disputes.',
                    '<strong>Account</strong> — Profile, Business, KYC, Security, Wallet, Notifications.',
                    '<strong>Messages</strong> — your conversations with counterparties, per deal.',
                ]],
                ['type' => 'link', 'to' => 'dashboard', 'text' => 'Go to my dashboard', 'icon' => 'bi-speedometer2'],

                ['type' => 'h', 'text' => 'On a phone'],
                ['type' => 'p', 'text' => 'The bar fixed to the bottom of the screen carries the five things you need most: Home, Buy, Sell, Auctions and Account. Everything else is behind the menu button at the top left, including the language selector.'],
                ['type' => 'tip', 'text' => 'You can install the site as an app. Open it in Chrome, then "Add to Home screen". It opens full-screen and keeps you signed in.'],

                ['type' => 'h', 'text' => 'Changing the language'],
                ['type' => 'p', 'text' => 'The interface is available in English, Hindi, Gujarati, Marathi, Bengali, Tamil and Punjabi. On a computer the selector is in the dark bar at the very top of the page; on a phone it is inside the menu. To make the choice stick to your account rather than this browser, set <strong>Preferred language</strong> in your profile.'],
                ['type' => 'note', 'text' => 'The interface is translated. Listing titles, descriptions and this Help Centre are shown in the language they were written in.'],
            ],
        ],
    ],
];
