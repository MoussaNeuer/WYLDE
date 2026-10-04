<?php
/**
 * Liste des produits.
 *
 * @var array<int, \App\Models\Product> $products
 * @var array<int, \App\Models\Category> $categories
 * @var array<string, mixed> $filters
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string|null $prevUrl
 * @var string|null $nextUrl
 */
use App\Models\Product;

component('toast');

$statusMap = [
    'draft'     => __('admin.product_status.draft'),
    'published' => __('admin.product_status.published'),
    'hidden'    => __('admin.product_status.hidden'),
    'archived'  => __('admin.product_status.archived'),
];

$stockMap = [
    'in'  => 'admin.inventory.ok',
    'low' => 'admin.inventory.low',
    'out' => 'admin.inventory.out',
];

// Index des catégories pour éviter une requête par ligne du tableau.
$categoryNames = [];

foreach ($categories as $category) {
    $categoryNames[(int) $category->id] = $category->name;
}

ob_start();
?>
<label class="admin-field">
    <span><?= e(__('admin.product.name')) ?></span>
    <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>">
</label>

<label class="admin-field">
    <span><?= e(__('admin.product.status')) ?></span>
    <select name="status">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach (['draft', 'published', 'hidden', 'archived'] as $status): ?>
            <option value="<?= e($status) ?>"<?= ($filters['status'] ?? '') === $status ? ' selected' : '' ?>>
                <?= e($statusMap[$status]) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label class="admin-field">
    <span><?= e(__('admin.product.category')) ?></span>
    <select name="category_id">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= e((string) $category->id) ?>"<?= (int) ($filters['category_id'] ?? 0) === (int) $category->id ? ' selected' : '' ?>>
                <?= e($category->name) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label class="admin-field">
    <span><?= e(__('admin.product.label')) ?></span>
    <select name="label">
        <option value=""><?= e(__('common.all')) ?></option>
        <?php foreach (['new', 'bestseller', 'limited'] as $label): ?>
            <option value="<?= e($label) ?>"<?= ($filters['label'] ?? '') === $label ? ' selected' : '' ?>>
                <?= e(__('product.' . $label)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label class="admin-field">
    <span><?= e(__('common.sort')) ?></span>
    <select name="sort">
        <?php foreach ([
            'recent'     => __('admin.sort.recent'),
            'oldest'     => __('admin.sort.oldest'),
            'name'       => __('admin.sort.name'),
            'price_asc'  => __('admin.sort.price_asc'),
            'price_desc' => __('admin.sort.price_desc'),
            'stock'      => __('admin.sort.stock'),
            'featured'   => __('admin.sort.featured'),
        ] as $value => $text): ?>
            <option value="<?= e($value) ?>"<?= ($filters['sort'] ?? 'recent') === $value ? ' selected' : '' ?>><?= e($text) ?></option>
        <?php endforeach; ?>
    </select>
</label>

<label class="admin-check">
    <input type="checkbox" name="low_stock" value="1"<?= !empty($filters['low_stock']) ? ' checked' : '' ?>>
    <?= e(__('admin.kpi.low_stock')) ?>
</label>
<?php
$filterFields = (string) ob_get_clean();
?>
<div class="admin-page">

    <header class="admin-page__head">
        <h1 class="admin-page__title"><?= e(__('admin.products')) ?></h1>
        <div class="admin-page__tools">
            <span class="pill"><?= e((string) $total) ?></span>
            <a href="<?= e(url('/admin/products/create')) ?>" class="btn btn-light btn-sm"><?= e(__('common.create')) ?></a>
        </div>
    </header>

    <div class="admin-cols">
        <?php component('sidebar', [
            'title'  => __('common.filter'),
            'action' => url('/admin/products'),
            'content' => $filterFields,
        ]); ?>

        <?php ob_start(); ?>
        <?php component('admin-table', [
            'title'       => '',
            'rows'        => $products,
            'selectable'  => true,
            'bulkForm'    => url('/admin/products/bulk'),
            'head'        => '<th>' . e(__('admin.product.images')) . '</th>'
                          . '<th>' . e(__('admin.product.name')) . '</th>'
                          . '<th>' . e(__('admin.product.category')) . '</th>'
                          . '<th class="is-end">' . e(__('common.price')) . '</th>'
                          . '<th>' . e(__('admin.product.stock')) . '</th>'
                          . '<th>' . e(__('common.status')) . '</th>'
                          . '<th class="is-end">' . e(__('common.actions')) . '</th>',
            'row'         => static function (Product $product) use ($statusMap, $stockMap, $categoryNames): string {
                $stock = (int) ($product->total_stock ?? 0);
                $level = stock_status($stock)['level'];

                ob_start();
                ?>
                <td>
                    <?php if (($product->image_path ?? null) !== null): ?>
                        <img class="admin-thumb" src="<?= e(upload_url((string) $product->image_path)) ?>" alt="" loading="lazy">
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= e(url('/admin/products/' . $product->id() . '/edit')) ?>"><?= e($product->name) ?></a>
                    <?php if ((int) $product->is_featured === 1): ?>
                        <span class="pill">★</span>
                    <?php endif; ?>
                </td>
                <td><?= e($categoryNames[(int) $product->category_id] ?? '—') ?></td>
                <td class="is-end">
                    <?= e(money($product->effectivePrice())) ?>
                    <?php if ($product->hasDiscount()): ?>
                        <del><?= e(money($product->price)) ?></del>
                    <?php endif; ?>
                </td>
                <td>
                    <?php component('badge', [
                        'label'  => (string) $stock,
                        'status' => $level,
                        'map'    => $stockMap,
                    ]); ?>
                </td>
                <td><?php component('badge', ['status' => (string) $product->status, 'map' => $statusMap]); ?></td>
                <td class="is-end">
                    <div class="admin-table__actions">
                        <a class="btn btn-sm btn-outline-light"
                           href="<?= e(url('/admin/products/' . $product->id() . '/media')) ?>"><?= e(__('admin.media_title')) ?></a>
                        <a class="btn btn-sm btn-outline-light"
                           href="<?= e(url('/admin/products/' . $product->id() . '/edit')) ?>"><?= e(__('common.edit')) ?></a>
                        <form method="post" action="<?= e(url('/admin/products/' . $product->id() . '/delete')) ?>"
                              data-confirm="<?= e(str_replace(':name', $product->name, __('admin.product.confirm_delete'))) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger"><?= e(__('common.delete')) ?></button>
                        </form>
                    </div>
                </td>
                <?php
                return (string) ob_get_clean();
            },
            'emptyTitle'  => __('admin.empty.products'),
            'emptyText'   => ($filters['q'] ?? '') !== '' ? __('admin.empty.search') : null,
            'emptyAction' => url('/admin/products/create'),
            'emptyActionLabel' => __('admin.quick_actions.add_product'),
        ]); ?>
        <?php
        component('pagination', [
            'current' => $page,
            'last'    => $pages,
            'prevUrl' => $prevUrl,
            'nextUrl' => $nextUrl,
        ]);
        echo (string) ob_get_clean();
        ?>
    </div>
</div>