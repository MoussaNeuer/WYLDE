<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use App\Validators\CategoryValidator;

/**
 * Catégories : liste à plat (sans hiérarchie récursive en V1), CRUD.
 */
final class CategoryController extends AdminController
{
    public function index(Request $request): Response
    {
        $categories = Database::select(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM `categories` c
             LEFT JOIN `products` p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        return $this->view('admin/categories/index', [
            'title'      => __('admin.categories'),
            'categories' => $categories,
            'statuses'   => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = new CategoryValidator($request->all());

        $validator->validate();

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/categories', $validator->errors(), $request->all());
        }

        $data = $validator->categoryData();

        if ($data['slug'] === '') {
            $data['slug'] = unique_slug('categories', Product::slugify($data['name']));
        }

        $category = Category::create($data);

        AuditService::log(AuditService::ACTION_CATEGORY_CREATE, 'categories', (int) $category->id(), [
            'name' => $data['name'],
        ]);

        return $this->redirectWithSuccess('/admin/categories', __('flash.category_saved'));
    }

    public function update(Request $request): Response
    {
        $category = Category::findOrFail($this->id($request));

        $validator = new CategoryValidator($request->all(), (int) $category->id());

        $validator->validate();

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/categories', $validator->errors(), $request->all());
        }

        $data = $validator->categoryData();

        if ($data['slug'] === '') {
            $data['slug'] = (string) $category->slug;
        }

        $category->fill($data);
        $category->save();

        AuditService::log(AuditService::ACTION_CATEGORY_UPDATE, 'categories', (int) $category->id(), [
            'name' => $data['name'],
        ]);

        return $this->redirectWithSuccess('/admin/categories', __('flash.category_saved'));
    }

    public function destroy(Request $request): Response
    {
        $category = Category::findOrFail($this->id($request));

        $products = $category->productCount();

        if ($products > 0) {
            return $this->redirectWithErrors('/admin/categories', [
                'delete' => __('admin.category.error_not_empty', [
                    'count' => $products,
                ]),
            ], ['name' => $category->name]);
        }

        // Ne pas retarder la suppression, mais tracer les produits orphelins :
        // le FK les bascule en category_id NULL.
        $orphans = Product::count('`category_id` = :id', ['id' => $category->id()]);

        $name = $category->name;

        $category->delete();

        AuditService::log(AuditService::ACTION_CATEGORY_DELETE, 'categories', (int) $category->id(), [
            'name'    => $name,
            'orphans' => $orphans,
        ]);

        return $this->redirectWithSuccess('/admin/categories', __('flash.category_deleted'));
    }
}