<?php

use App\Core\Database;
use App\Models\Category;
use App\Services\SettingsService;

$siteName = (string) SettingsService::get('site_name', 'ScrapX');
$footerPages = Database::instance()->select(
    'SELECT slug, title FROM cms_pages WHERE is_published = 1 AND show_in_footer = 1 ORDER BY sort_order LIMIT 12'
);
$footerCategories = Category::featured(8);
$whatsapp = preg_replace('/\D/', '', (string) SettingsService::get('whatsapp_number', ''));
?>
<footer class="bg-dark text-white-50 mt-5 pt-5 pb-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="text-white d-flex align-items-center gap-2 mb-3">
                    <span class="brand-mark bg-teal"><i class="bi bi-recycle"></i></span>
                    <?= e($siteName) ?>
                </h5>
                <p class="small"><?= e((string) SettingsService::get('site_tagline', "India's B2B Scrap Trading Marketplace")) ?></p>
                <p class="small mb-2">
                    Sell scrap, buy scrap, run auctions, post requirements and settle on real weighbridge weight —
                    with verified businesses across India.
                </p>
                <div class="d-flex gap-2 mt-3">
                    <?php foreach ([
                        'facebook_url' => 'bi-facebook',
                        'linkedin_url' => 'bi-linkedin',
                        'twitter_url' => 'bi-twitter-x',
                        'youtube_url' => 'bi-youtube',
                    ] as $key => $icon): ?>
                        <?php if ($link = SettingsService::get($key)): ?>
                            <a class="btn btn-sm btn-outline-light rounded-circle" href="<?= e((string) $link) ?>"
                               target="_blank" rel="noopener noreferrer" aria-label="<?= e(str_replace('_url', '', $key)) ?>">
                                <i class="bi <?= e($icon) ?>"></i>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-white mb-3">Marketplace</h6>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('buy')) ?>">Buy Scrap</a></li>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('dashboard/listings/create')) ?>">Sell Scrap</a></li>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('auctions')) ?>">Live Auctions</a></li>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('wanted')) ?>">Buyer Requirements</a></li>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('rfq')) ?>">RFQ</a></li>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('market-rates')) ?>">Scrap Rates</a></li>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('sellers')) ?>">Sellers</a></li>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('buyers')) ?>">Buyers</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-3">
                <h6 class="text-white mb-3">Popular categories</h6>
                <ul class="list-unstyled small d-grid gap-2">
                    <?php foreach ($footerCategories as $category): ?>
                        <li>
                            <a class="link-secondary text-decoration-none" href="<?= e(url('scrap/' . $category['slug'])) ?>">
                                <?= e($category['name']) ?> Scrap
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-lg-3">
                <h6 class="text-white mb-3">Company</h6>
                <ul class="list-unstyled small d-grid gap-2">
                    <?php foreach ($footerPages as $page): ?>
                        <li>
                            <a class="link-secondary text-decoration-none" href="<?= e(url('page/' . $page['slug'])) ?>">
                                <?= e($page['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><a class="link-secondary text-decoration-none" href="<?= e(url('contact')) ?>">Contact Us</a></li>
                </ul>

                <?php if ($address = SettingsService::get('contact_address')): ?>
                    <p class="small mt-3 mb-1"><i class="bi bi-geo-alt me-1"></i><?= nl2br(e((string) $address)) ?></p>
                <?php endif; ?>
                <?php if ($phone = SettingsService::get('contact_phone')): ?>
                    <p class="small mb-1"><i class="bi bi-telephone me-1"></i><?= e((string) $phone) ?></p>
                <?php endif; ?>
                <?php if ($email = SettingsService::get('contact_email')): ?>
                    <p class="small mb-0"><i class="bi bi-envelope me-1"></i><?= e((string) $email) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 small">
            <span>&copy; <?= gmdate('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
            <span>
                Prices in <?= e((string) SettingsService::get('currency_code', 'INR')) ?> ·
                Times shown in <?= e((string) SettingsService::get('timezone', 'Asia/Kolkata')) ?>
            </span>
        </div>
    </div>
</footer>

<?php if ($whatsapp !== ''): ?>
    <a class="whatsapp-float" target="_blank" rel="noopener noreferrer"
       href="https://wa.me/<?= e(strlen($whatsapp) === 10 ? '91' . $whatsapp : $whatsapp) ?>"
       aria-label="Chat on WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
<?php endif; ?>
