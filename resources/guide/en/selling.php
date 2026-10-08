<?php

declare(strict_types=1);

/** Help Centre — Selling scrap. */
return [
    'title' => 'Selling scrap',
    'summary' => 'Post material, choose how you want to be paid, run an auction, and handle the offers that come in.',
    'icon' => 'bi-box-seam',
    'audience' => 'seller',
    'articles' => [

        [
            'slug' => 'post-your-first-listing',
            'title' => 'Post your first listing',
            'summary' => 'The nine-step sell wizard, step by step, with what to put in each field and why it matters.',
            'minutes' => 10,
            'keywords' => ['listing', 'sell', 'post', 'wizard', 'create listing', 'new listing', 'pincode', 'how to sell', 'upload photos'],
            'body' => [
                ['type' => 'p' , 'text' => 'Listing material is a guided form in nine steps. You can move back and forth freely; nothing is published until the last step. Each step checks its own fields, so <strong>Next</strong> will not move on while something required is empty or wrong.'],
                ['type' => 'link', 'to' => 'dashboard/listings/create', 'text' => 'Start a new listing', 'icon' => 'bi-plus-circle'],
                ['type' => 'note', 'text' => 'Your mobile number must be verified before you can list. If the page sends you elsewhere, that is why — see <em>Verify your mobile number</em> in Getting started.'],

                ['type' => 'h', 'text' => 'Step 1 — What are you selling?'],
                ['type' => 'list', 'items' => [
                    '<strong>Listing title.</strong> Write it the way a buyer would search. "HMS 1 heavy melting scrap 80:20, 20 MT, Jamnagar" beats "Good quality scrap for sale". At least eight characters; aim for a line that states material, grade, quantity and place.',
                    '<strong>Sale method.</strong> How you want the price decided — see <em>Choosing how to sell</em> next, this is the decision that shapes everything after it.',
                    '<strong>Category.</strong> Picking the category loads the matching material list in step 2, so get this right first.',
                ]],

                ['type' => 'h', 'text' => 'Step 2 — Material and grade'],
                ['type' => 'p', 'text' => 'Choose the material, then the grade if the material has graded variants. Grade is what buyers filter on hardest — an ungraded listing of a graded material gets fewer, vaguer enquiries. If your lot is genuinely mixed, say so in the description rather than guessing at a grade.'],

                ['type' => 'h', 'text' => 'Step 3 — Quantity available'],
                ['type' => 'list', 'items' => [
                    '<strong>Quantity and unit.</strong> MT, kg, pieces — whatever you actually trade in.',
                    '<strong>Minimum order quantity.</strong> Leave blank if you will sell any part of the lot. Set it if the lot has to move together or if small pickups are not worth your time.',
                    '<strong>Whether more is available regularly.</strong> Mark this if you generate the material monthly. It is what turns a one-off buyer into a standing arrangement.',
                ]],
                ['type' => 'warning', 'text' => 'Quantity here is an <em>estimate</em>, and the platform treats it as one. Final money is calculated from the weighbridge slip recorded on the order. Do not inflate it — the settlement will correct it and you will only have lost the buyer\'s trust.'],

                ['type' => 'h', 'text' => 'Step 4 — Price and GST'],
                ['type' => 'list', 'items' => [
                    '<strong>Price.</strong> Leave it empty to show "Price on request" if you would rather be asked.',
                    '<strong>Price basis.</strong> Per kg, per MT, or for the lot. Get this right — it is the commonest source of confusion in a negotiation. The platform derives the other figures from whichever you give.',
                    '<strong>GST rate.</strong> Pre-filled from the material\'s HSN code. Change it only if you know your case differs.',
                    '<strong>Whether the price includes GST.</strong> State it. Buyers assume the opposite of whatever you meant.',
                ]],

                ['type' => 'h', 'text' => 'Step 5 — Condition and source'],
                ['type' => 'p', 'text' => 'Condition, where the material came from (industrial, demolition, end-of-life vehicles, factory rejects), and anything a buyer needs to know before sending a truck — contamination, attached fittings, how it is baled or loose. Buyers are pricing in risk here. Being specific about a flaw costs you less than having the truck turned away over it.'],

                ['type' => 'h', 'text' => 'Step 6 — Where is the material?'],
                ['type' => 'p', 'text' => 'State, city and PIN code, plus whether loading is on you or the buyer. Freight often decides whether a deal makes sense at all, so an inaccurate location wastes everybody\'s time. Type the PIN and the city and state fill in automatically.'],

                ['type' => 'h', 'text' => 'Step 7 — Photos and documents'],
                ['type' => 'list', 'items' => [
                    'Photographs are the single biggest driver of enquiries. Take them in daylight, show the whole pile, then two or three close-ups of the actual material.',
                    'Include something for scale — a person, a drum, a pallet.',
                    'Photograph the problems too. A buyer who sees the contamination in the listing will not argue about it at the weighbridge.',
                    'Documents (test reports, lab analysis, weighbridge slips from previous loads) can be attached and are visible to serious buyers.',
                ]],
                ['type' => 'note', 'text' => 'How many images, videos and documents you may attach, and the maximum file size, are set by the operator. The form tells you the limits.'],

                ['type' => 'h', 'text' => 'Step 8 — Trade terms'],
                ['type' => 'p', 'text' => 'Payment terms, how soon the material must be lifted, and whether you will accept part-loads. Say what you actually want. Vague terms produce offers you will reject anyway.'],

                ['type' => 'h', 'text' => 'Step 9 — Review and publish'],
                ['type' => 'p', 'text' => 'The last step shows everything back to you as a buyer will see it. Read it once as if you were buying. Then publish.'],
                ['type' => 'p', 'text' => 'What happens next depends on how the platform is configured: your listing either goes live immediately, or sits as <strong>Pending</strong> until an administrator approves it. KYC-verified sellers may be approved automatically. You are notified when it goes live.'],

                ['type' => 'h', 'text' => 'Listing states'],
                ['type' => 'table', 'head' => ['State', 'Meaning'], 'rows' => [
                    ['Draft', 'Saved but not submitted. Only you can see it.'],
                    ['Pending', 'Waiting for administrator approval.'],
                    ['Active', 'Live and visible to buyers.'],
                    ['Paused', 'Hidden by you, keeps its history. Use this when the material is temporarily committed.'],
                    ['Sold', 'Material gone.'],
                    ['Expired', 'Passed its expiry date. Re-publish it from My Listings if the material is still available.'],
                    ['Rejected', 'An administrator declined it; the reason is shown on the listing.'],
                    ['Archived', 'Removed from the marketplace but kept in your records.'],
                ]],
                ['type' => 'link', 'to' => 'dashboard/listings', 'text' => 'My listings', 'icon' => 'bi-list-ul'],
            ],
        ],

        [
            'slug' => 'choosing-how-to-sell',
            'title' => 'Choosing how to sell: fixed, negotiable, offers or auction',
            'summary' => 'Which sale method gets the best result for the material you have.',
            'minutes' => 4,
            'keywords' => ['fixed price', 'negotiable', 'make offer', 'auction', 'sale method', 'tender'],
            'body' => [
                ['type' => 'p', 'text' => 'This is the choice made in step 1 of the wizard, and it has more effect on what you are paid than anything else in the listing.'],

                ['type' => 'table', 'head' => ['Method', 'How it works', 'Use it when'], 'rows' => [
                    ['Fixed price', 'You name a price. A buyer accepts it and an order is created.', 'The material has a clear market rate and you want it gone with no back-and-forth.'],
                    ['Negotiable', 'A price is shown as a starting point, and buyers can message you to discuss it.', 'You have a number in mind but expect to move a little.'],
                    ['Make an offer', 'No price shown. Buyers submit offers; you accept, reject or counter.', 'You genuinely do not know what the lot is worth, or the quality is uneven.'],
                    ['Auction', 'Buyers bid against each other until the clock runs out.', 'Several buyers will want it. Almost always the highest price for sought-after material.'],
                    ['Tender', 'Sealed quotes, compared after the window closes.', 'Large or institutional lots where you must show a fair process.'],
                ]],

                ['type' => 'h', 'text' => 'Rules of thumb'],
                ['type' => 'list', 'items' => [
                    'Common, liquid material with a published rate — <strong>fixed price</strong>. Speed is worth more than the last hundred rupees a tonne.',
                    'Scarce, high-value or unusually clean material — <strong>auction</strong>. Let the competition set the price.',
                    'Mixed lots, odd grades, anything you would struggle to describe — <strong>make an offer</strong>. Let buyers price the risk and pick the best one.',
                    'First time on the platform and unsure — <strong>negotiable</strong>. You keep control and learn what the market says.',
                ]],
                ['type' => 'tip', 'text' => 'You can turn an existing active listing into an auction later from My Listings, without re-entering anything.'],
            ],
        ],

        [
            'slug' => 'run-an-auction',
            'title' => 'Run an auction',
            'summary' => 'Setting the starting price, increment, reserve and anti-sniping window — and awarding the result.',
            'minutes' => 9,
            'keywords' => ['auction', 'bidding', 'reserve price', 'increment', 'anti sniping', 'award', 'unsold', 'sniping', 'extend', 'reserve'],
            'body' => [
                ['type' => 'p', 'text' => 'An auction is set up either in the wizard (choose Auction as the sale method, which adds two extra steps) or on an existing listing from My Listings.'],

                ['type' => 'h', 'text' => 'The numbers you must set'],
                ['type' => 'settings', 'intro' => 'These appear under "Auction setup":', 'rows' => [
                    ['Auction type', 'Forward (buyers bid up) is the normal choice. Reverse auctions, where sellers bid down, are used for buying and may be switched off on this installation.'],
                    ['Starting price (₹)', 'Where bidding opens. Set it clearly below what you expect to get — a high start discourages the early bids that attract later ones. The first bid is the hardest to get.'],
                    ['Bid increment (₹)', 'The minimum step between bids. Too small and the auction crawls through fifty tiny bids; too large and bidders drop out. Roughly 1–2% of the expected final price works well.'],
                    ['Reserve price (₹)', 'The least you will accept. Optional, and never shown to bidders — they only see whether the reserve has been met. If the clock runs out below it, the auction closes <strong>unsold</strong> and you decide what to do.'],
                    ['Price basis', 'Per kg, per MT or for the lot. Bidders must know what they are bidding on.'],
                    ['Starts at / Ends at', 'Times are entered and shown in Indian Standard Time. Give it long enough for buyers to see it — two to three days usually beats a few hours.'],
                ]],

                ['type' => 'h', 'text' => 'Anti-sniping: how the close is protected'],
                ['type' => 'p', 'text' => 'Sniping is bidding in the final seconds so nobody can respond. It depresses prices and drives honest bidders away. The platform stops it automatically.'],
                ['type' => 'settings', 'intro' => 'Under "Anti-sniping &amp; eligibility":', 'rows' => [
                    ['Extend if a bid lands within (seconds)', 'The guarded window at the end of the auction. A bid inside it pushes the close back.'],
                    ['Extend by (seconds)', 'How much time that bid adds.'],
                    ['Maximum extensions', 'A ceiling, so an auction cannot be extended indefinitely.'],
                    ['Maximum bidders', 'Optional cap on how many may take part.'],
                    ['Deposit amount (₹)', 'Optional earnest money, where you want only committed bidders.'],
                    ['Restrict to KYC-verified bidders', 'Strongly recommended for valuable lots. Fewer bidders, but real ones.'],
                ]],
                ['type' => 'p', 'text' => 'The defaults come from the platform settings, so you can usually leave these alone. When an extension happens, every bidder is notified and the countdown on the page updates by itself.'],

                ['type' => 'h', 'text' => 'While the auction is live'],
                ['type' => 'list', 'items' => [
                    'Your auction console shows the current price, the bid history and how many bidders are watching, refreshing on its own.',
                    'Bidder identities may be masked publicly, depending on how the operator configured it. You always see who bid.',
                    'Every bid is checked as it arrives: below the current price, not a multiple of the increment, your own auction, or a repeat of your own last bid — each is refused with the reason.',
                    'Two bids arriving at the same instant are handled one at a time, so exactly one can win at a given price. The second bidder is re-checked against the new price and told the new minimum.',
                ]],
                ['type' => 'warning', 'text' => 'You cannot bid on your own auction, and neither can another account from your business. This is enforced, and attempts are recorded.'],

                ['type' => 'h', 'text' => 'When the clock runs out'],
                ['type' => 'p', 'text' => 'The scheduler closes auctions as they expire. There are three outcomes:'],
                ['type' => 'table', 'head' => ['Outcome', 'What it means', 'What you do'], 'rows' => [
                    ['Awarded', 'Highest bid met the reserve, or there was no reserve.', 'An order is created automatically. Carry on to weighment.'],
                    ['Unsold', 'Bids came in but none met your reserve.', 'Award it to the top bidder anyway if the price is acceptable, relist it, or leave it.'],
                    ['Ended with no bids', 'Nobody bid.', 'Usually the starting price was too high or the photographs too thin. Fix one of those and relist.'],
                ]],
                ['type' => 'p', 'text' => 'You can award an unsold auction to the highest bidder at your discretion from the auction console — this is a deliberate choice, not automatic, because the reserve is yours to waive.'],
                ['type' => 'link', 'to' => 'dashboard/auctions', 'text' => 'My auctions', 'icon' => 'bi-hammer'],
                ['type' => 'note', 'text' => 'Auctions close by a scheduled job. If the scheduler has not been set up on this installation, auctions will not close on time — an operator should see <em>Set up the scheduler</em> in Operator setup.'],
            ],
        ],

        [
            'slug' => 'handle-offers-and-counter-offers',
            'title' => 'Handle offers and counter-offers',
            'summary' => 'Accept, reject or counter. What each does, and how the conversation stays attached to the deal.',
            'minutes' => 5,
            'keywords' => ['offer', 'counter offer', 'negotiate', 'accept', 'reject', 'chat'],
            'body' => [
                ['type' => 'p', 'text' => 'Offers arrive against negotiable and make-an-offer listings. They are in <strong>Offers</strong> in your dashboard, and you are notified as each one comes in.'],
                ['type' => 'link', 'to' => 'dashboard/offers', 'text' => 'Offers received', 'icon' => 'bi-chat-left-dots'],

                ['type' => 'h', 'text' => 'Your three choices'],
                ['type' => 'table', 'head' => ['Action', 'Effect'], 'rows' => [
                    ['Accept', 'An order is created immediately at the offered price and quantity. The negotiation is over — be sure before you click.'],
                    ['Counter', 'You send back your own price. The buyer can accept, reject, or counter again. The whole chain stays linked, so you can see how the price moved.'],
                    ['Reject', 'Closes that offer. The buyer can still make a fresh one, so rejecting is not a door slammed.'],
                ]],

                ['type' => 'h', 'text' => 'How to counter well'],
                ['type' => 'list', 'items' => [
                    'Give a reason in the message. "₹32.50 because this lot is sorted and loading is on us" is accepted far more often than a bare number.',
                    'Counter once, properly. Three rounds of a few paise lose buyers.',
                    'Check the buyer\'s profile and rating before countering hard. A verified buyer who completes orders is worth a little less per tonne than one you have never heard of.',
                ]],

                ['type' => 'h', 'text' => 'Offer expiry'],
                ['type' => 'p', 'text' => 'Offers have a validity set by the platform. Once it passes, the offer is marked <em>Expired</em> automatically and can no longer be accepted. Deal with offers while they are live — an expired offer is a lost deal, not a pending one.'],

                ['type' => 'h', 'text' => 'Messages'],
                ['type' => 'p', 'text' => 'Every listing, offer and order has its own conversation thread, so the discussion stays with the deal instead of scattered across phones. Messages are in <strong>Messages</strong> in your dashboard and the thread updates while you have it open.'],
                ['type' => 'tip', 'text' => 'Agree the important things in the thread, not on a call. If a dispute is ever raised, the thread is the record both sides and the platform can look at.'],
            ],
        ],

        [
            'slug' => 'answer-buyer-requirements-and-rfqs',
            'title' => 'Answer buyer requirements and RFQs',
            'summary' => 'The demand side of the marketplace. Often easier business than waiting for someone to find your listing.',
            'minutes' => 5,
            'keywords' => ['wanted', 'requirement', 'rfq', 'quote', 'quotation', 'tender'],
            'body' => [
                ['type' => 'p', 'text' => 'Buyers post what they need rather than hunting through listings. These are the most under-used pages on the platform by sellers, and the easiest deals to win — the buyer has already said they want the material.'],

                ['type' => 'h', 'text' => 'Wanted requirements'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Read the Wanted list', 'text' => 'Filter by material and location to the ones you can actually serve.'],
                    ['title' => 'Check your matches', 'text' => '<strong>Requirement matches</strong> in your dashboard shows requirements that fit the materials on your business profile — which is why keeping that list accurate pays off.'],
                    ['title' => 'Respond with a price', 'text' => 'Submit your rate, the quantity you can supply and when. Be specific about grade.'],
                    ['title' => 'Negotiate if needed', 'text' => 'The buyer can counter. When one side accepts, an order is created.'],
                ]],
                ['type' => 'link', 'to' => 'wanted', 'text' => 'Browse buyer requirements', 'icon' => 'bi-card-checklist'],

                ['type' => 'h', 'text' => 'RFQs — requests for quotation'],
                ['type' => 'p', 'text' => 'An RFQ is a formal request, usually larger, often with several material lines and a closing date. Quoting is a little more work and the deals are correspondingly bigger.'],
                ['type' => 'steps', 'items' => [
                    ['title' => 'Open the RFQ', 'text' => 'Read every line: material, grade, quantity, delivery point and the closing date.'],
                    ['title' => 'Quote line by line', 'text' => 'Price each line. Quote only on what you can genuinely supply — a partial quote is honest and often still wins.'],
                    ['title' => 'State your terms', 'text' => 'Validity, payment terms, whether freight is included. The buyer is comparing several quotes side by side and ambiguity loses.'],
                    ['title' => 'Submit before the window closes', 'text' => 'RFQs close automatically at their deadline. Late is not accepted.'],
                    ['title' => 'Wait for the award', 'text' => 'If you win, an order is created for the exact total you quoted — down to the paisa, with no re-derived rounding.'],
                ]],
                ['type' => 'link', 'to' => 'rfq', 'text' => 'Browse open RFQs', 'icon' => 'bi-clipboard-check'],
                ['type' => 'tip', 'text' => 'Make a habit of checking Wanted and RFQ once a day. Sellers who do win noticeably more business than those who only post listings and wait.'],
            ],
        ],

        [
            'slug' => 'get-your-listings-seen',
            'title' => 'Get your listings seen',
            'summary' => 'Why some listings get ten enquiries and others get none, and what to do about it.',
            'minutes' => 4,
            'keywords' => ['featured', 'promote', 'visibility', 'no enquiries', 'search ranking'],
            'body' => [
                ['type' => 'p', 'text' => 'If a listing has been live for a week with no enquiries, the material is rarely the problem. Work through these in order.'],

                ['type' => 'steps', 'items' => [
                    ['title' => 'Get KYC verified', 'text' => 'The largest single effect on how a listing performs. Verified sellers rank higher and are trusted with larger deals.'],
                    ['title' => 'Add more and better photographs', 'text' => 'Daylight, the whole pile, then close-ups. Listings with four or more real photographs do far better than those with one.'],
                    ['title' => 'Rewrite the title the way a buyer searches', 'text' => 'Material, grade, quantity, city. Not adjectives.'],
                    ['title' => 'Check the grade and category are right', 'text' => 'A listing filed under the wrong material is invisible to the buyers who want it.'],
                    ['title' => 'Compare against the rate page', 'text' => 'Look at Scrap Rates for your material and city. Buyers do. Being 15% above the indicative rate with no explanation reads as not serious.'],
                    ['title' => 'Say what is wrong with the material', 'text' => 'Counter-intuitive but reliable: naming the contamination or the mixed content brings buyers who are fine with it, instead of buyers who walk away at the gate.'],
                ]],

                ['type' => 'h', 'text' => 'Featured listings'],
                ['type' => 'p', 'text' => 'A listing can be promoted to appear above ordinary results and on the home page. There is a fee, set by the operator and shown before you confirm. It is worth it for high-value lots and for material with a short pickup window; it will not rescue a listing with one bad photograph.'],

                ['type' => 'h', 'text' => 'Subscription plans'],
                ['type' => 'p', 'text' => 'Plans may be offered with higher listing limits, more images, better placement or lower commission. What is available, and whether plans are enabled at all, is up to the operator — see the Pricing page.'],
                ['type' => 'link', 'to' => 'pricing', 'text' => 'See plans and fees', 'icon' => 'bi-stars'],
            ],
        ],
    ],
];
