<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\ProductSize;
use App\Services\AuditService;

/**
 * Catalogue des tailles.
 *
 * C'est ici que l'admin définit les tailles proposées : le formulaire
 * produit n'affiche que celles-ci et le sélecteur public suit le même
 * ordre. Une taille encore utilisée par une variante ne peut être ni
 * renommée ni supprimée, sinon le produit concerned deviendrait
 * impossible à enregistrer.
 */
final class SizeController extends AdminController
{
    /** Jeu de départ proposé quand le catalogue est vide. */
    private const PRESET = ['F', 'S', 'M', 'L', 'XL', 'XXL'];

    public function index(Request $request): Response
    {
        $sizes = [];

        foreach (ProductSize::ordered() as $size) {
            $sizes[] = [
                'size'    => $size,
                'usage'   => $size->usageCount(),
                'products'=> $size->productCount(),
            ];
        }

        return $this->view('admin/sizes/index', [
            'title'    => __('admin.sizes.title'),
            'sizes'    => $sizes,
            'presets'  => self::PRESET,
            'total'    => ProductSize::total(),
        ]);
    }

    /** Ajout d'une taille unique. */
    public function store(Request $request): Response
    {
        $label = ProductSize::normalize($request->str('label'));

        if ($label === '') {
            return $this->redirectWithErrors('/admin/sizes', [
                'label' => __('validation.required', ['field' => __('admin.sizes.label')]),
            ], $request->all());
        }

        if (ProductSize::labelExists($label)) {
            return $this->redirectWithErrors('/admin/sizes', [
                'label' => __('admin.sizes.error_exists', ['label' => $label]),
            ], $request->all());
        }

        ProductSize::create([
            'label'      => $label,
            'sort_order' => ProductSize::nextSortOrder(),
        ]);

        AuditService::log(AuditService::ACTION_SIZE_CREATE, 'product_sizes', null, ['label' => $label]);

        return $this->redirectWithSuccess('/admin/sizes', __('flash.size_saved'));
    }

    /**
     * Ajout du jeu de tailles courant en une fois.
     *
     * Utile au premier démarrage : sans catalogue, le formulaire produit
     * n'a rien à proposer. Les tailles déjà présentes sont ignorées.
     */
    public function storePreset(Request $request): Response
    {
        $added = 0;

        foreach (self::PRESET as $label) {
            if (ProductSize::labelExists($label)) {
                continue;
            }

            ProductSize::create([
                'label'      => $label,
                'sort_order' => ProductSize::nextSortOrder(),
            ]);

            $added++;
        }

        AuditService::log(AuditService::ACTION_SIZE_CREATE, 'product_sizes', null, [
            'preset' => self::PRESET,
            'added'  => $added,
        ]);

        return $this->redirectWithSuccess('/admin/sizes', __('flash.size_preset_added', ['count' => $added]));
    }

    /** Renommage. Refusé si une variante porte encore cette taille. */
    public function update(Request $request): Response
    {
        $size = ProductSize::findOrFail($this->id($request));
        $old  = $size->label;
        $label = ProductSize::normalize($request->str('label'));

        if ($label === '') {
            return $this->redirectWithErrors('/admin/sizes', [
                'label' => __('validation.required', ['field' => __('admin.sizes.label')]),
            ], $request->all());
        }

        if ($label !== $old && ProductSize::labelExists($label)) {
            return $this->redirectWithErrors('/admin/sizes', [
                'label' => __('admin.sizes.error_exists', ['label' => $label]),
            ]);
        }

        if ($label !== $old && $size->usageCount() > 0) {
            return $this->redirectWithErrors('/admin/sizes', [
                'label' => __('admin.sizes.error_in_use', [
                    'count' => $size->productCount(),
                    'label' => $old,
                ]),
            ]);
        }

        $sortOrder = $request->has('sort_order') ? (int) $request->int('sort_order') : (int) $size->sort_order;

        $size->label      = $label;
        $size->sort_order = max(0, $sortOrder);
        $size->save();

        AuditService::log(AuditService::ACTION_SIZE_UPDATE, 'product_sizes', (int) $size->id(), [
            'from'       => $old,
            'to'         => $label,
            'sort_order' => $size->sort_order,
        ]);

        return $this->redirectWithSuccess('/admin/sizes', __('flash.size_saved'));
    }

    /** Suppression. Refusée tant qu'un produit utilise la taille. */
    public function destroy(Request $request): Response
    {
        $size = ProductSize::findOrFail($this->id($request));
        $used = $size->usageCount();

        if ($used > 0) {
            return $this->redirectWithErrors('/admin/sizes', [
                'label' => __('admin.sizes.error_in_use', [
                    'count' => $size->productCount(),
                    'label' => $size->label,
                ]),
            ]);
        }

        $label = $size->label;

        $size->delete();

        AuditService::log(AuditService::ACTION_SIZE_DELETE, 'product_sizes', null, ['label' => $label]);

        return $this->redirectWithSuccess('/admin/sizes', __('flash.size_deleted'));
    }
}
