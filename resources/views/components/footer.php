<?php
/**
 * Pied de page de la boutique.
 */
$year = date('Y');
$credit = site_credit();
?>
<footer class="site-footer">
    <div class="container">
        <div class="site-footer__grid">

            <div class="site-footer__brand">
                <p class="site-footer__logo">
                    <img src="<?= e(asset('assets/images/logo/logo.svg')) ?>"
                         alt="<?= e(config('app.name', 'WYLDE')) ?>" width="70" height="28">
                </p>
                <p class="site-footer__tagline"><?= e(__('app.tagline')) ?></p>
            </div>

            <nav class="site-footer__col" aria-label="<?= e(__('nav.shop')) ?>">
                <h2 class="site-footer__heading"><?= e(__('nav.shop')) ?></h2>
                <ul>
                    <li><a href="<?= e(url('/shop')) ?>"><?= e(__('nav.shop')) ?></a></li>
                    <li><a href="<?= e(url('/shop?label=new')) ?>"><?= e(__('nav.new')) ?></a></li>
                    <li><a href="<?= e(url('/shop?label=bestseller')) ?>"><?= e(__('nav.best')) ?></a></li>
                </ul>
            </nav>

            <nav class="site-footer__col" aria-label="<?= e(__('page.about')) ?>">
                <h2 class="site-footer__heading"><?= e(__('page.about')) ?></h2>
                <ul>
                    <li><a href="<?= e(url('/about')) ?>"><?= e(__('nav.about')) ?></a></li>
                    <li><a href="<?= e(url('/contact')) ?>"><?= e(__('nav.contact')) ?></a></li>
                </ul>
            </nav>

            <nav class="site-footer__col" aria-label="<?= e(__('page.privacy')) ?>">
                <h2 class="site-footer__heading"><?= e(__('page.privacy')) ?></h2>
                <ul>
                    <li><a href="<?= e(url('/privacy')) ?>"><?= e(__('page.privacy')) ?></a></li>
                    <li><a href="<?= e(url('/terms')) ?>"><?= e(__('page.terms')) ?></a></li>
                </ul>
            </nav>
        </div>

        <div class="site-footer__bottom">
            <p>&copy; <?= e($year) ?> <?= e(config('app.name', 'WYLDE')) ?>. <?= e(__('page.rights_reserved')) ?>.</p>

            <div class="site-footer__meta">
                <?php if ($credit['name'] !== ''): ?>
                    <p class="site-credit">
                        <?= e(__('page.credit_by')) ?>
                        <?php if ($credit['url'] !== null): ?>
                            <a href="<?= e($credit['url']) ?>" rel="noopener nofollow"
                               target="_blank"><?= e($credit['name']) ?></a>
                        <?php else: ?>
                            <strong><?= e($credit['name']) ?></strong>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>
