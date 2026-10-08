<?php

declare(strict_types=1);

/** Help Centre — Buying scrap. */
return [
    'title' => 'Buying scrap',
    'summary' => 'Search and filter listings, bid in auctions, make offers, and get sellers to come to you.',
    'icon' => 'bi-search',
    'audience' => 'buyer',
    'articles' => [

        [
            'slug' => 'find-the-material-you-need',
            'title' => 'Find the material you need',
            'summary' => 'Searching and filtering, and how to read a listing properly before you commit.',
            'minutes' => 6,
            'keywords' => ['search', 'filter', 'browse', 'buy', 'find material', 'saved', 'pincode'],
            'body' => [
                ['type' => 'p', 'text' => 'Start at <strong>Buy Scrap</strong>. Everything active is there; the filters narrow it down.'],
                ['type' => 'link', 'to' => 'buy', 'text' => 'Browse all listings', 'icon' => 'bi-search'],

                ['type' => 'h', 'text' => 'The filters that matter'],
                ['type' => 'table', 'head' => ['Filter', 'Why it matters'], 'rows' => [
                    ['Category and material', 'The first cut. Start broad — material is sometimes filed under a neighbouring category.'],
                    ['Grade', 'The difference between HMS 1 and HMS 2 is money. Filter once you know exactly what you want.'],
                    ['State and city', 'Freight usually decides whether a deal works at all. Begin with your own state and widen only if nothing fits.'],
                    ['Quantity range', 'Avoids lots too small to be worth a truck, or too large to pay for.'],
                    ['Price range', 'Remember to check the price basis — per kg and per MT differ by a factor of a thousand.'],
                    ['Verified sellers only', 'The most useful filter on the page. Fewer results, far fewer wasted calls.'],
                ]],
                ['type' => 'tip', 'text' => 'Save a search you repeat. You will be notified when new material matches it, instead of having to check.'],

                ['type' => 'h', 'text' => 'Reading a listing'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Check the price basis first', 'text' => 'Per kg, per MT or for the lot, and whether GST is included. Work out your landed cost before anything else.'],
                    ['title' => 'Look at the photographs properly', 'text' => 'Is the whole pile shown, or one flattering corner? Is there anything for scale? A listing with one distant photograph is a listing with something to hide.'],
                    ['title' => 'Read the condition and source', 'text' => 'Where the material came from tells you what to expect. Industrial offcuts behave very differently from demolition scrap.'],
                    ['title' => 'Open the seller\'s profile', 'text' => 'KYC verified? How many completed orders? What is the rating, and what do the reviews say? This takes thirty seconds and prevents most bad deals.'],
                    ['title' => 'Check the location and loading terms', 'text' => 'Who loads, and from where. Add the freight yourself — do not assume.'],
                    ['title' => 'Note the minimum order quantity', 'text' => 'If the lot has to move together, make sure you can take all of it.'],
                ]],
                ['type' => 'warning', 'text' => 'The quantity in a listing is an estimate. You pay on the weighbridge figure recorded against the order, so judge the <em>rate</em>, not the headline total.'],

                ['type' => 'h', 'text' => 'Market rates'],
                ['type' => 'p', 'text' => 'The <strong>Scrap Rates</strong> page carries indicative rates by material and city, with history. Use it to sanity-check a price before you negotiate. Rates are a guide maintained by the platform, not a quotation.'],
                ['type' => 'link', 'to' => 'market-rates', 'text' => 'Today\'s scrap rates', 'icon' => 'bi-graph-up-arrow'],
            ],
        ],

        [
            'slug' => 'bid-in-an-auction',
            'title' => 'Bid in an auction',
            'summary' => 'How bidding works, what the rules refuse and why, and how the close is protected.',
            'minutes' => 7,
            'keywords' => ['bid', 'auction', 'bidding', 'outbid', 'increment', 'reserve'],
            'body' => [
                ['type' => 'p', 'text' => 'Every auction page shows the current bid, the minimum next bid, the number of bidders and a live countdown. The page refreshes itself — you do not need to reload.'],
                ['type' => 'link', 'to' => 'auctions', 'text' => 'See live auctions', 'icon' => 'bi-hammer'],

                ['type' => 'h', 'text' => 'Before you bid'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Work out your ceiling', 'text' => 'Landed cost including freight, loading, GST and your margin. Write the number down. Auctions are designed to make you spend more than you intended.'],
                    ['title' => 'Check what you are bidding on', 'text' => 'The price basis — per kg, per MT or for the whole lot.'],
                    ['title' => 'Check eligibility', 'text' => 'Some auctions are restricted to KYC-verified bidders, and some require a deposit. Both are shown on the page.'],
                    ['title' => 'Read the anti-sniping settings', 'text' => 'They tell you whether a late bid will extend the close, which changes how you should play the ending.'],
                ]],

                ['type' => 'h', 'text' => 'Placing a bid'],
                ['type' => 'p', 'text' => 'Enter your amount and confirm. Bids are <strong>binding</strong>, so you are asked to confirm before it is placed.'],
                ['type' => 'p', 'text' => 'A bid is checked the moment it arrives and refused with a reason if it does not pass:'],
                ['type' => 'table', 'head' => ['Refusal', 'What it means'], 'rows' => [
                    ['Bid too low', 'Someone bid above you between your opening the page and submitting. The message gives you the new minimum.'],
                    ['Invalid increment', 'Bids must move in whole steps of the seller\'s increment. The message suggests the nearest valid figure.'],
                    ['Duplicate bid', 'You already hold that exact amount. Bid higher or wait.'],
                    ['Own auction', 'You cannot bid on your own material, or on your own business\'s material.'],
                    ['Not eligible', 'The auction is restricted to verified bidders and your KYC is not yet approved.'],
                    ['Too many bids', 'There is a per-minute limit to stop automated bidding. Wait a moment.'],
                ]],
                ['type' => 'note', 'text' => 'If two people bid the same amount at the same instant, they are processed one at a time and only one can hold that price. The second is automatically re-checked against the new price and told the new minimum — nobody gets a bid in by luck of timing.'],

                ['type' => 'h', 'text' => 'Being outbid'],
                ['type' => 'p', 'text' => 'You are notified when someone bids above you. Decide against the ceiling you wrote down, not against the number on the screen.'],

                ['type' => 'h', 'text' => 'The reserve price'],
                ['type' => 'p', 'text' => 'Many auctions carry a hidden reserve — the least the seller will take. You are shown only whether it has been met. If the auction ends below it, it closes <strong>unsold</strong> and the seller may still choose to award it to you.'],

                ['type' => 'h', 'text' => 'How the close works'],
                ['type' => 'p', 'text' => 'Bidding in the last seconds does not win by denying others a reply. A bid inside the auction\'s guarded window pushes the closing time back, every bidder is notified, and the countdown updates. There is a cap on extensions, so it cannot run forever.'],
                ['type' => 'p', 'text' => 'If you hold the winning bid at the close and the reserve is met, an order is created automatically and you move on to arranging pickup.'],
                ['type' => 'link', 'to' => 'dashboard/bids', 'text' => 'My bids', 'icon' => 'bi-lightning'],
            ],
        ],

        [
            'slug' => 'make-an-offer',
            'title' => 'Make an offer',
            'summary' => 'How to pitch a price on a negotiable listing so that it gets accepted.',
            'minutes' => 4,
            'keywords' => ['offer', 'negotiate', 'counter', 'make offer', 'price'],
            'body' => [
                ['type' => 'p', 'text' => 'Negotiable and make-an-offer listings carry a <strong>Make an offer</strong> button. You submit a price and quantity; the seller accepts, rejects or counters.'],

                ['type' => 'h', 'text' => 'Making an offer that works'],
                ['type' => 'list' , 'items' => [
                    '<strong>Say why.</strong> An offer with a reason — freight distance, the grade as photographed, the quantity you will commit to — is accepted far more often than a bare number.',
                    '<strong>Do not open absurdly low.</strong> Sellers on this platform see the market rate page too. A 40% lowball marks you as not serious and you will be ignored on the next lot as well.',
                    '<strong>Offer something besides money.</strong> Immediate pickup, your own transport, payment on collection, taking the whole lot — all of these are worth real money to a seller and cost you less than the equivalent discount.',
                    '<strong>State your terms clearly.</strong> When you will lift, how you will pay, who loads.',
                ]],

                ['type' => 'h', 'text' => 'Counter-offers'],
                ['type' => 'p', 'text' => 'If the seller counters, you can accept, reject or counter back. The whole chain is kept together so both sides can see how the price moved. Accepting creates an order immediately at the agreed figures.'],

                ['type' => 'h', 'text' => 'Validity'],
                ['type' => 'p', 'text' => 'Offers expire after a period set by the platform and cannot be accepted afterwards. If you need an answer by a date, say so in the message.'],
                ['type' => 'link', 'to' => 'dashboard/offers', 'text' => 'My offers', 'icon' => 'bi-chat-left-dots'],
                ['type' => 'tip', 'text' => 'Keep the negotiation in the message thread rather than on the phone. If anything is disputed later, the thread is the record.'],
            ],
        ],

        [
            'slug' => 'post-a-requirement',
            'title' => 'Post a requirement and let sellers come to you',
            'summary' => 'Instead of searching every day, publish what you need and let supply find you.',
            'minutes' => 5,
            'keywords' => ['wanted', 'requirement', 'post requirement', 'buy request', 'matches'],
            'body' => [
                ['type' => 'p', 'text' => 'If you buy the same materials regularly, posting a requirement is far more efficient than searching. It appears on the public <strong>Wanted</strong> page and is matched against sellers who deal in that material.'],
                ['type' => 'link', 'to' => 'dashboard/requirements/create', 'text' => 'Post a requirement', 'icon' => 'bi-plus-circle'],

                ['type' => 'steps', 'items' => [
                    ['title' => 'Name the material and grade', 'text' => 'Be exact. A vague requirement brings vague responses from sellers who are guessing.'],
                    ['title' => 'State quantity and frequency', 'text' => 'One-off, or monthly? "50 MT per month, ongoing" attracts a completely different and better class of seller than "50 MT".'],
                    ['title' => 'Give your target rate', 'text' => 'Optional, but it filters hard. Sellers who cannot meet it will not waste your time, and those who can will quote closer to it.'],
                    ['title' => 'Set the delivery point', 'text' => 'City and PIN code, and say whether you want it delivered or will collect. Sellers need this to price freight.'],
                    ['title' => 'Set your terms', 'text' => 'Payment terms, whether you need a weighbridge slip, any quality requirement or testing you will insist on.'],
                    ['title' => 'Publish', 'text' => 'It stays open until its expiry date, which you can extend.'],
                ]],

                ['type' => 'h', 'text' => 'Handling the responses'],
                ['type' => 'p', 'text' => 'Seller responses arrive in <strong>My Requirements</strong>. Compare on landed cost, not headline rate, and check each seller\'s verification and completed-order count before you commit. Accepting a response creates an order.'],
                ['type' => 'link', 'to' => 'dashboard/requirements', 'text' => 'My requirements', 'icon' => 'bi-card-checklist'],
                ['type' => 'tip', 'text' => 'Keep a standing requirement open for each material you buy routinely. It costs nothing and generates enquiries while you are doing something else.'],
            ],
        ],

        [
            'slug' => 'raise-an-rfq',
            'title' => 'Raise an RFQ for larger purchases',
            'summary' => 'A formal, multi-line request with a closing date, so you can compare quotes properly.',
            'minutes' => 5,
            'keywords' => ['rfq', 'request for quotation', 'tender', 'quotes', 'compare', 'award'],
            'body' => [
                ['type' => 'p', 'text' => 'Use an RFQ when the purchase is large, has several material lines, or when you need a documented process you can show to someone else. Sellers quote line by line and you compare them side by side.'],
                ['type' => 'link', 'to' => 'dashboard/rfq/create', 'text' => 'Create an RFQ', 'icon' => 'bi-clipboard-plus'],

                ['type' => 'steps', 'items' => [
                    ['title' => 'Add your line items', 'text' => 'One line per material and grade, each with its own quantity and unit. Keeping them separate is what lets you award the best price per line.'],
                    ['title' => 'Set the closing date', 'text' => 'Allow sellers real time to price properly — a few days, not a few hours. The RFQ closes automatically at the deadline.'],
                    ['title' => 'State the delivery point and terms', 'text' => 'Where it goes, who pays freight, payment terms, and any inspection or testing you will require.'],
                    ['title' => 'Publish', 'text' => 'It appears on the public RFQ page. Sellers submit quotes against it.'],
                    ['title' => 'Compare the quotes', 'text' => 'They are shown together per line. Compare landed cost, and weigh the seller\'s verification, rating and completed-order history alongside the price.'],
                    ['title' => 'Award it', 'text' => 'An order is created for the exact total quoted. The platform prices the award as a lot at the quoted figure, so there is no rounding drift between what was quoted and what is invoiced.'],
                ]],

                ['type' => 'h', 'text' => 'RFQ states'],
                ['type' => 'table', 'head' => ['State', 'Meaning'], 'rows' => [
                    ['Draft', 'Not published yet; only you can see it.'],
                    ['Open', 'Accepting quotes.'],
                    ['Closing soon', 'The deadline is near. Sellers are prompted.'],
                    ['Closed', 'The window has passed. No further quotes accepted.'],
                    ['Awarded', 'You have chosen a quote and an order exists.'],
                    ['Cancelled', 'Withdrawn by you.'],
                ]],
                ['type' => 'link', 'to' => 'dashboard/rfq', 'text' => 'My RFQs', 'icon' => 'bi-clipboard-check'],
                ['type' => 'note', 'text' => 'RFQs close by a scheduled job. If the scheduler is not running on this installation they will not close on time — an operator should see <em>Set up the scheduler</em> in Operator setup.'],
            ],
        ],
    ],
];
