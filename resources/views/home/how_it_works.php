<?php

use App\Core\View;

View::section('content');
?>
<section class="py-5 bg-teal-soft">
    <div class="container text-center">
        <h1 class="h3 mb-2">How ScrapX works</h1>
        <p class="text-muted mb-0">
            From listing a lot to the final weighbridge settlement — every step happens on the platform,
            with a record both sides can point to.
        </p>
    </div>
</section>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-6">
            <h4 class="text-teal mb-4"><i class="bi bi-box-seam me-2"></i>Selling scrap</h4>
            <?php foreach ([
                ['Register and verify', 'Sign up with your mobile number, add your business details and upload GST, PAN and address proof. Verified sellers are trusted with larger deals and restricted auctions.'],
                ['List the lot', 'The 9-step sell wizard captures material, grade, quantity, price basis, condition, location, photos and trade terms. Listings with photos attract far more enquiries.'],
                ['Choose how to sell', 'Fixed price, negotiable, make-an-offer, or run a live auction with anti-sniping extensions. You decide who can bid — KYC only, approved bidders, or open.'],
                ['Negotiate', 'Buyers send offers; you accept, reject or counter. Every round is recorded in the offer thread, so nothing rests on a verbal promise.'],
                ['Dispatch and weigh', 'Assign a vehicle, add the LR and e-way bill, then record the weighbridge slip. The order total is recalculated on the actual weight, not the estimate.'],
                ['Get paid and invoice', 'Record UPI, NEFT, RTGS, cheque or cash against the order, raise the GST invoice (CGST+SGST or IGST worked out automatically), and rate the buyer.'],
            ] as $i => [$title, $body]): ?>
                <div class="d-flex gap-3 mb-4">
                    <span class="step-index flex-shrink-0"><?= $i + 1 ?></span>
                    <div>
                        <h6 class="mb-1"><?= e($title) ?></h6>
                        <p class="small text-muted mb-0"><?= e($body) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <a class="btn btn-teal" href="<?= e(url('register')) ?>">Start selling</a>
        </div>

        <div class="col-lg-6">
            <h4 class="text-teal mb-4"><i class="bi bi-cart-check me-2"></i>Buying scrap</h4>
            <?php foreach ([
                ['Search the marketplace', 'Filter by category, material, grade, quantity, price, condition, state, city and distance. Save searches you run often.'],
                ['Or post what you need', 'Post a buying requirement and every seller who deals in that material is notified. For multi-item purchases, raise an RFQ and compare quotes side by side.'],
                ['Inspect before you commit', 'Most sellers allow inspection. Ask questions in the built-in chat — the whole conversation stays attached to the deal.'],
                ['Bid or offer', 'Bid in live auctions with server-validated increments, or send an offer on a fixed-price listing and negotiate.'],
                ['Track the truck', 'Follow the order through pickup, transit and delivery, with the vehicle, driver and e-way bill on record.'],
                ['Pay on actual weight', 'You pay for what the weighbridge says arrived. If something is wrong, raise a dispute from the order and our team reviews the full history.'],
            ] as $i => [$title, $body]): ?>
                <div class="d-flex gap-3 mb-4">
                    <span class="step-index flex-shrink-0"><?= $i + 1 ?></span>
                    <div>
                        <h6 class="mb-1"><?= e($title) ?></h6>
                        <p class="small text-muted mb-0"><?= e($body) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <a class="btn btn-outline-teal" href="<?= e(url('buy')) ?>">Browse scrap for sale</a>
        </div>
    </div>

    <?php if (!empty($page) && !empty($page['content'])): ?>
        <hr class="my-5">
        <div class="cms-content">
            <?= strip_tags((string) $page['content'], '<p><br><b><strong><i><em><ul><ol><li><h2><h3><h4><h5><a><blockquote><table><thead><tbody><tr><th><td><hr>') ?>
        </div>
    <?php endif; ?>

    <hr class="my-5">

    <div class="row g-4">
        <div class="col-md-4">
            <h6><i class="bi bi-shield-check text-teal me-2"></i>Why weighment matters</h6>
            <p class="small text-muted mb-0">
                Scrap is sold on estimate and settled on fact. ScrapX records gross, tare and net weight
                with the slip number and weighbridge name, then recalculates the order — so neither side
                has to argue over a number nobody wrote down.
            </p>
        </div>
        <div class="col-md-4">
            <h6><i class="bi bi-hammer text-teal me-2"></i>Auctions that cannot be sniped</h6>
            <p class="small text-muted mb-0">
                A bid in the closing window extends the auction automatically. Every bid is re-validated
                on the server inside a row-level lock, so two bids can never both win.
            </p>
        </div>
        <div class="col-md-4">
            <h6><i class="bi bi-receipt text-teal me-2"></i>Proper GST paperwork</h6>
            <p class="small text-muted mb-0">
                Invoices carry HSN codes, GSTIN of both parties, state codes, CGST/SGST or IGST split,
                round-off and the amount in words — ready to print or save as PDF.
            </p>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
