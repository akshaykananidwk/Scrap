<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * CMS pages, FAQs, notification templates, cron jobs and subscription plans.
 * All of it is editable from the admin panel — this is just a sane starting point.
 */
final class ContentSeeder
{
    public const PAGES = [
        ['about', 'About Us', "<h2>About ScrapX</h2><p>ScrapX is a business-to-business marketplace that connects scrap sellers, buyers, traders, aggregators, recyclers and manufacturers across India. Sellers list material with photographs, weight and grade; verified buyers discover it through search, auctions and requirement matching.</p><p>Every deal on the platform follows a transparent path: listing or requirement, offer or bid, order, weighment, logistics, payment, invoice and review.</p><p><em>Administrators can edit this page from Admin → Content → Pages.</em></p>", 1],
        ['contact', 'Contact Us', "<h2>Talk to us</h2><p>Use the contact form on this page for sales, verification or support questions. Registered users can also raise a support dispute directly from an order.</p>", 1],
        ['terms', 'Terms & Conditions', "<h2>Terms of Use</h2><p>By registering on ScrapX you agree to trade honestly, describe material accurately, honour accepted bids and offers, and settle payments as agreed with your counterparty.</p><h3>Marketplace role</h3><p>ScrapX is a marketplace that introduces buyers and sellers. Unless explicitly stated, the platform is not a party to the sale contract between them.</p><h3>Accounts</h3><p>One business, one account. Accounts found operating multiple identities to manipulate bidding may be suspended.</p><p><em>Replace this placeholder text with your own legal terms before going live.</em></p>", 1],
        ['privacy', 'Privacy Policy', "<h2>Privacy Policy</h2><p>We collect the business information you provide during registration and KYC, activity data required to operate the marketplace, and technical data such as IP address and device information used for security and fraud prevention.</p><p>KYC documents are visible only to you and to authorised verification staff. We never display your GSTIN, PAN or documents publicly.</p><p><em>Replace this placeholder text with your own privacy policy before going live.</em></p>", 1],
        ['refund-policy', 'Refund Policy', "<h2>Refunds</h2><p>Platform fees such as featured listing charges are non-refundable once the promotion has started. Deal-level payments are settled directly between buyer and seller; disputes about material, weight or payment must be raised through the dispute system within 7 days of delivery.</p>", 1],
        ['auction-rules', 'Auction Rules', "<h2>Auction Rules</h2><ul><li>Every bid is a binding commitment to buy at that price.</li><li>Bids must beat the current highest bid by at least the published increment.</li><li>A bid placed inside the auto-extension window extends the auction, so the close time can move.</li><li>Bids cannot be retracted. Contact support immediately if you bid in error.</li><li>If the reserve price is not met, the seller is not obliged to sell.</li><li>Winning without completing the purchase can result in suspension.</li></ul>", 1],
        ['buyer-rules', 'Buyer Guidelines', "<h2>For Buyers</h2><ul><li>Inspect material before lifting wherever the seller offers inspection.</li><li>Agree loading and transport responsibility in writing before pickup.</li><li>Always record weighment with a slip; the platform settles on actual weight.</li><li>Pay through traceable methods and record the payment against the order.</li></ul>", 1],
        ['seller-rules', 'Seller Guidelines', "<h2>For Sellers</h2><ul><li>Photograph the actual material — stock photographs are grounds for removal.</li><li>State grade, moisture, attachments and contamination honestly.</li><li>Keep quantity and availability up to date; withdraw sold listings.</li><li>Respond to enquiries quickly — response rate is shown on your public profile.</li></ul>", 1],
        ['kyc-policy', 'KYC Policy', "<h2>KYC Verification</h2><p>Businesses trading on ScrapX submit GST certificate, PAN, business registration and an address proof. Verification is performed by our KYC team, usually within two business days.</p><p>Verified businesses receive a badge, rank higher in search, and can participate in KYC-restricted auctions.</p>", 1],
        ['commission-policy', 'Commission Policy', "<h2>Platform Commission</h2><p>ScrapX charges a commission on successfully completed deals. The current rate, together with any buyer or seller fee, is shown on every order before it is confirmed and on the commission ledger in your dashboard.</p><p>Commission is calculated on the settled order value after weighment, plus GST as applicable.</p>", 1],
        ['how-it-works', 'How It Works', "<h2>How ScrapX works</h2><h3>Selling</h3><ol><li>Post your material with photos, quantity and grade.</li><li>Choose fixed price, negotiable, make-offer or auction.</li><li>Receive bids or offers from verified buyers.</li><li>Accept the best one — an order is created automatically.</li><li>Record weighment, arrange transport, collect payment, raise the invoice.</li></ol><h3>Buying</h3><ol><li>Search live listings or post a requirement.</li><li>Bid, offer or request a quotation.</li><li>Negotiate in chat, confirm the order.</li><li>Lift the material, settle on actual weight, pay and review.</li></ol>", 0],
    ];

    public const FAQS = [
        ['general', 'Is ScrapX free to use?', 'Registration, browsing and posting requirements are free. The platform charges a commission on completed deals and optional fees for featured listings — the current rates are on the Commission Policy page.'],
        ['general', 'Who can register?', 'Any scrap business in India: sellers, buyers, traders, dealers, aggregators, recyclers, manufacturers, factories, contractors, e-waste dealers and transporters. One account can act as both buyer and seller.'],
        ['kyc', 'Why do I need KYC?', 'KYC protects genuine businesses from fake buyers and sellers. Verified businesses get a badge, better search placement and access to KYC-restricted auctions.'],
        ['kyc', 'Which documents are required?', 'GST certificate, PAN, business registration proof and an address proof. Bank proof is required only if you use platform-mediated payments.'],
        ['auction', 'How does auto-extension work?', 'If a bid arrives within the extension window before the scheduled close (2 minutes by default), the auction is extended so no one can win by sniping in the last second. The number of extensions is capped by the seller.'],
        ['auction', 'Can I cancel a bid?', 'No. Every bid is binding. Contact support immediately if you made a genuine mistake — the seller has to agree to any cancellation.'],
        ['auction', 'What is a reverse auction?', 'The buyer posts a requirement and sellers compete by lowering their price. The lowest qualified offer at close wins.'],
        ['trading', 'How is the final amount calculated?', 'Orders are settled on actual weighbridge weight. The platform computes: actual weight × rate, plus GST, plus any transport or loading charges agreed on the order.'],
        ['trading', 'What if the weight does not match?', 'Record the weighbridge slip against the order. The settlement recalculates automatically and both parties see the difference. If you disagree, raise a dispute from the order page.'],
        ['payments', 'Does ScrapX hold my money?', 'Not by default. Buyers and sellers settle directly and record the payment against the order. Escrow and wallet features are built into the architecture and can be enabled by the platform operator.'],
    ];

    /** [event, channel, name, subject, body, variables] */
    public const TEMPLATES = [
        ['welcome', 'email', 'Welcome email', 'Welcome to {{site_name}}, {{name}}', "<p>Hi {{name}},</p><p>Your {{site_name}} account is ready. You can now list scrap material, post requirements and bid in live auctions.</p><p><a href=\"{{link}}\">Go to your dashboard</a></p>", 'name,site_name,link'],
        ['otp', 'sms', 'OTP SMS', null, '{{code}} is your {{site_name}} verification code. It is valid for {{minutes}} minutes. Do not share it with anyone.', 'code,site_name,minutes'],
        ['otp', 'email', 'OTP email', 'Your {{site_name}} verification code', "<p>Your verification code is <strong>{{code}}</strong>.</p><p>It expires in {{minutes}} minutes.</p>", 'code,site_name,minutes'],
        ['password_reset', 'email', 'Password reset', 'Reset your {{site_name}} password', "<p>We received a request to reset your password.</p><p><a href=\"{{link}}\">Set a new password</a></p><p>This link expires in 60 minutes. If you did not request it, ignore this email.</p>", 'link,site_name'],
        ['kyc_approved', 'email', 'KYC approved', 'Your business is now verified on {{site_name}}', "<p>Good news {{name}} — {{business}} is now KYC verified.</p><p>Your listings will show the verified badge and you can bid in restricted auctions.</p>", 'name,business,site_name'],
        ['kyc_rejected', 'email', 'KYC rejected', 'KYC verification needs attention', "<p>Hi {{name}},</p><p>We could not verify {{business}}. Reason: {{reason}}</p><p>Please re-upload the corrected documents from your dashboard.</p>", 'name,business,reason'],
        ['listing_approved', 'email', 'Listing approved', 'Your listing is live: {{title}}', "<p>Your listing <strong>{{title}}</strong> is now live on {{site_name}}.</p><p><a href=\"{{link}}\">View listing</a></p>", 'title,link,site_name'],
        ['new_bid', 'email', 'New bid received', 'New bid on {{title}}', "<p>A new bid of <strong>{{amount}}</strong> was placed on {{title}}.</p><p><a href=\"{{link}}\">View auction</a></p>", 'title,amount,link'],
        ['outbid', 'sms', 'Outbid SMS', null, 'You have been outbid on {{title}}. Current highest bid is {{amount}}. Bid again on {{site_name}}.', 'title,amount,site_name'],
        ['outbid', 'email', 'Outbid email', 'You have been outbid on {{title}}', "<p>Your bid on <strong>{{title}}</strong> has been beaten. The current highest bid is {{amount}}.</p><p><a href=\"{{link}}\">Place a higher bid</a></p>", 'title,amount,link'],
        ['auction_won', 'email', 'Auction won', 'You won the auction: {{title}}', "<p>Congratulations — you won <strong>{{title}}</strong> at {{amount}}.</p><p>Order {{order}} has been created. Please coordinate pickup and payment with the seller.</p><p><a href=\"{{link}}\">View order</a></p>", 'title,amount,order,link'],
        ['auction_lost', 'email', 'Auction lost', 'Auction closed: {{title}}', "<p>The auction for <strong>{{title}}</strong> has closed and your bid was not the highest.</p><p>Plenty more material is listed — <a href=\"{{link}}\">browse live auctions</a>.</p>", 'title,link'],
        ['new_offer', 'email', 'Offer received', 'New offer on {{title}}', "<p>You received an offer of <strong>{{amount}}</strong> for {{quantity}} on {{title}}.</p><p><a href=\"{{link}}\">Review the offer</a></p>", 'title,amount,quantity,link'],
        ['offer_accepted', 'email', 'Offer accepted', 'Your offer was accepted', "<p>Your offer of <strong>{{amount}}</strong> on {{title}} was accepted. Order {{order}} has been created.</p><p><a href=\"{{link}}\">View order</a></p>", 'title,amount,order,link'],
        ['new_rfq', 'email', 'New RFQ', 'New RFQ you can quote on: {{title}}', "<p>A buyer published an RFQ matching your business: <strong>{{title}}</strong>.</p><p>Quotes close {{closes}}. <a href=\"{{link}}\">Submit your quote</a></p>", 'title,closes,link'],
        ['rfq_quote', 'email', 'Quote received', 'New quote on your RFQ {{title}}', "<p>{{seller}} submitted a quote of <strong>{{amount}}</strong> on your RFQ {{title}}.</p><p><a href=\"{{link}}\">Compare quotes</a></p>", 'title,seller,amount,link'],
        ['requirement_offer', 'email', 'Requirement offer', 'A seller responded to your requirement', "<p>{{seller}} can supply {{quantity}} at <strong>{{amount}}</strong> for your requirement {{title}}.</p><p><a href=\"{{link}}\">View offer</a></p>", 'title,seller,quantity,amount,link'],
        ['order_created', 'email', 'Order created', 'Order {{order}} created', "<p>Order <strong>{{order}}</strong> has been created for {{title}}.</p><p>Value: {{amount}}</p><p><a href=\"{{link}}\">Open order</a></p>", 'order,title,amount,link'],
        ['order_status', 'email', 'Order status update', 'Order {{order}} is now {{status}}', "<p>Order <strong>{{order}}</strong> status changed to <strong>{{status}}</strong>.</p><p><a href=\"{{link}}\">Open order</a></p>", 'order,status,link'],
        ['payment_received', 'email', 'Payment recorded', 'Payment recorded for order {{order}}', "<p>A payment of <strong>{{amount}}</strong> has been recorded against order {{order}}.</p><p><a href=\"{{link}}\">View payment</a></p>", 'order,amount,link'],
        ['payment_pending', 'email', 'Payment pending', 'Payment pending for order {{order}}', "<p>Order <strong>{{order}}</strong> is awaiting payment of {{amount}}.</p><p><a href=\"{{link}}\">Record payment</a></p>", 'order,amount,link'],
        ['delivery_update', 'email', 'Delivery update', 'Delivery update for order {{order}}', "<p>Delivery status for order <strong>{{order}}</strong> is now <strong>{{status}}</strong>.</p><p>Vehicle: {{vehicle}}</p><p><a href=\"{{link}}\">Track order</a></p>", 'order,status,vehicle,link'],
        ['dispute_update', 'email', 'Dispute update', 'Dispute {{dispute}} updated', "<p>Dispute <strong>{{dispute}}</strong> is now <strong>{{status}}</strong>.</p><p><a href=\"{{link}}\">View dispute</a></p>", 'dispute,status,link'],
    ];

    /** [key, name, description, interval_minutes] */
    public const CRON_JOBS = [
        ['auction_lifecycle', 'Auction lifecycle', 'Starts scheduled auctions, closes expired ones and awards winners', 1],
        ['auction_alerts', 'Auction alerts', 'Sends ending-soon alerts to watchers and bidders', 5],
        ['expire_offers', 'Expire offers', 'Marks offers past their validity as expired', 15],
        ['expire_listings', 'Expire listings and requirements', 'Archives listings and requirements past their expiry date', 60],
        ['close_rfqs', 'Close RFQs', 'Closes RFQs whose quote window has ended', 15],
        ['notification_queue', 'Notification queue', 'Delivers queued email, SMS and WhatsApp notifications', 1],
        ['update_check', 'Update check', 'Checks GitHub for a newer application version', 1440],
        ['auto_backup', 'Automatic backup', 'Creates a scheduled backup when enabled', 1440],
        ['cleanup', 'Housekeeping', 'Prunes rate limits, expired OTPs, old backups and stale sessions', 360],
        ['refresh_stats', 'Refresh statistics', 'Recalculates category counts, ratings and business metrics', 120],
    ];

    /** [name, audience, price, period, listing_limit, auction_limit, rfq_limit, featured, commission_discount] */
    public const PLANS = [
        ['Free', 'both', '0.00', 'monthly', 5, 1, 3, 0, '0.000', 'Get started on ScrapX at no cost'],
        ['Seller Pro', 'seller', '1999.00', 'monthly', 50, 15, 0, 3, '0.250', 'For active sellers running regular auctions'],
        ['Buyer Pro', 'buyer', '1499.00', 'monthly', 0, 0, 50, 0, '0.250', 'For buyers sourcing material continuously'],
        ['Trader Elite', 'both', '4999.00', 'monthly', 200, 60, 200, 10, '0.500', 'Unlimited-scale trading with the lowest commission'],
    ];

    public static function run(Database $db): void
    {
        foreach (self::PAGES as $i => [$slug, $title, $content, $inFooter]) {
            if ($db->first('SELECT id FROM cms_pages WHERE slug = :s', ['s' => $slug]) !== null) {
                continue;
            }
            $db->insert('cms_pages', [
                'slug' => $slug,
                'title' => $title,
                'content' => $content,
                'meta_title' => $title,
                'meta_description' => substr(strip_tags($content), 0, 280),
                'show_in_footer' => $inFooter,
                'show_in_header' => $slug === 'how-it-works' ? 1 : 0,
                'sort_order' => $i,
                'is_published' => 1,
                'is_system' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::FAQS as $i => [$category, $question, $answer]) {
            if ($db->first('SELECT id FROM faqs WHERE question = :q', ['q' => $question]) !== null) {
                continue;
            }
            $db->insert('faqs', [
                'question' => $question,
                'answer' => $answer,
                'category' => $category,
                'sort_order' => $i,
                'is_published' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::TEMPLATES as [$event, $channel, $name, $subject, $body, $variables]) {
            $db->upsert('email_templates', [
                'event' => $event,
                'channel' => $channel,
                'name' => $name,
                'subject' => $subject,
                'body' => $body,
                'variables' => $variables,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['name', 'variables', 'updated_at']);
        }

        foreach (self::CRON_JOBS as [$key, $name, $description, $interval]) {
            $db->upsert('cron_jobs', [
                'job_key' => $key,
                'name' => $name,
                'description' => $description,
                'interval_minutes' => $interval,
                'is_enabled' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['name', 'description', 'updated_at']);
        }

        foreach (self::PLANS as $i => [$name, $audience, $price, $period, $listings, $auctions, $rfqs, $featured, $discount, $description]) {
            $slug = slugify($name);
            if ($db->first('SELECT id FROM plans WHERE slug = :s', ['s' => $slug]) !== null) {
                continue;
            }
            $planId = $db->insert('plans', [
                'name' => $name,
                'slug' => $slug,
                'audience' => $audience,
                'description' => $description,
                'price' => $price,
                'billing_period' => $period,
                'listing_limit' => $listings,
                'auction_limit' => $auctions,
                'rfq_limit' => $rfqs,
                'featured_credits' => $featured,
                'commission_discount' => $discount,
                'is_active' => 1,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $features = [
                ['listings', 'Active listings', $listings > 0 ? (string) $listings : 'Not included', $listings > 0],
                ['auctions', 'Auctions per month', $auctions > 0 ? (string) $auctions : 'Not included', $auctions > 0],
                ['rfqs', 'RFQs per month', $rfqs > 0 ? (string) $rfqs : 'Not included', $rfqs > 0],
                ['featured', 'Featured listing credits', $featured > 0 ? (string) $featured : '0', $featured > 0],
                ['commission', 'Commission discount', $discount > 0 ? $discount . '%' : 'Standard rate', $discount > 0],
                ['support', 'Priority support', $price > 0 ? 'Included' : 'Standard', $price > 0],
            ];
            foreach ($features as $sort => [$key, $label, $value, $included]) {
                $db->insert('subscription_features', [
                    'plan_id' => $planId,
                    'feature_key' => $key,
                    'feature_label' => $label,
                    'feature_value' => $value,
                    'is_included' => $included ? 1 : 0,
                    'sort_order' => $sort,
                ]);
            }
        }
    }
}
