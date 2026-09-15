<?php

use App\Core\View;

View::section('content');
?>
<div class="container py-5 text-center">
    <i class="bi bi-wifi-off text-muted" style="font-size:4rem"></i>
    <h1 class="h3 mt-3">You are offline</h1>
    <p class="text-muted">
        ScrapX needs a connection for live prices, bids and orders — those are never served from cache,
        because a stale auction price would be worse than no price.
    </p>
    <button class="btn btn-teal" onclick="location.reload()">Try again</button>
</div>
<?php View::endSection(); ?>
