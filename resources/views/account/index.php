<?php
/** @var string $title
 *  @var \App\Models\User|null $user
 *  @var \App\Models\Customer|null $customer
 *  @var int $ordersCount
 *  @var int $totalSpent
 */
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <p class="page-head__eyebrow"><?= e(__('account.title')) ?></p>
            <h1 class="page-head__title"><?= e((string) ($user?->name ?? '')) ?></h1>
        </header>

        <?php foreach (\App\Core\Session::pullFlash() as $flash): ?>
            <div class="alert alert--<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

        <div class="grid-2 stagger">
            <section class="card-surface hover-lift">
                <header class="card-surface__head">
                    <h2 class="card-surface__title"><?= e(__('account.summary_title')) ?></h2>
                </header>
                <div class="card-surface__body">
                    <dl class="summary-list">
                        <div>
                            <dt><?= e(__('account.orders_count')) ?></dt>
                            <dd><?= e((string) $ordersCount) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('account.total_spent')) ?></dt>
                            <dd><?= e(money($totalSpent)) ?></dd>
                        </div>
                    </dl>

                    <a class="btn btn-light btn-block" href="<?= e(url('/account/orders')) ?>">
                        <?= e(__('account.view_orders')) ?>
                    </a>
                </div>
            </section>

            <section class="card-surface hover-lift">
                <header class="card-surface__head">
                    <h2 class="card-surface__title"><?= e(__('account.profile_title')) ?></h2>
                </header>
                <div class="card-surface__body">
                    <form method="post" action="<?= e(url('/account')) ?>" novalidate>
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label class="form-label" for="a-name"><?= e(__('auth.name')) ?></label>
                            <input class="form-control"
                                   type="text"
                                   id="a-name"
                                   name="name"
                                   value="<?= e(old('name', (string) ($user?->name ?? ''))) ?>"
                                   required
                                   <?= error_for('name') !== null ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($error = error_for('name')): ?>
                                <p class="form-error"><?= e($error) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="a-phone"><?= e(__('common.phone')) ?></label>
                            <input class="form-control"
                                   type="tel"
                                   id="a-phone"
                                   name="phone"
                                   value="<?= e(old('phone', (string) ($user?->phone ?? ''))) ?>"
                                   autocomplete="tel">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="a-email"><?= e(__('auth.email')) ?></label>
                            <input class="form-control" type="email" id="a-email" value="<?= e((string) ($user?->email ?? '')) ?>" disabled>
                            <p class="form-help"><?= e(__('account.email_fixed')) ?></p>
                        </div>

                        <button class="btn btn-light btn-block" type="submit">
                            <?= e(__('account.save_profile')) ?>
                        </button>
                    </form>

                    <form method="post" action="<?= e(url('/logout')) ?>" class="logout-form" data-confirm="<?= e(__('account.confirm_logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-block" type="submit">
                            <?= e(__('account.logout')) ?>
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</section>