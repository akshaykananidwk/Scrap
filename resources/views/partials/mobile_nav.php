<?php

use App\Core\Auth;

// Bottom navigation for phones — the primary way most scrap traders use the site.
$items = [
    ['url' => '/', 'icon' => 'bi-house', 'label' => 'Home', 'match' => '/'],
    ['url' => '/buy', 'icon' => 'bi-search', 'label' => 'Buy', 'match' => '/buy'],
    ['url' => '/dashboard/listings/create', 'icon' => 'bi-plus-circle-fill', 'label' => 'Sell', 'match' => '/dashboard/listings/create', 'primary' => true],
    ['url' => '/auctions', 'icon' => 'bi-hammer', 'label' => 'Auctions', 'match' => '/auctions'],
    ['url' => Auth::check() ? '/dashboard' : '/login', 'icon' => 'bi-person', 'label' => Auth::check() ? 'Account' : 'Sign in', 'match' => '/dashboard'],
];
?>
<nav class="mobile-nav d-lg-none">
    <?php foreach ($items as $item): ?>
        <a href="<?= e(url(ltrim($item['url'], '/'))) ?>"
           class="mobile-nav-item <?= active_nav($item['match']) ?> <?= !empty($item['primary']) ? 'primary' : '' ?>">
            <i class="bi <?= e($item['icon']) ?>"></i>
            <span><?= e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
