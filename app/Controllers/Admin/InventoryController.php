<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Services\AuditService;
use App\Services\StockService;

/**
 * Stock : tableau des alertes et ajustements par variante.
 */
final class InventoryController extends AdminController
{
    public function index(Request $request): Response
    {
        $level = $request->str('level');

        if (!in_array($level, ['low', 'out', 'all'], true)) {
            $level = 'all';
        }

        $rows = StockService::alertList(
            $level,
            $request->int('category_id') > 0 ? $request->int('category_id') : null
        );

        return $this->view('admin/inventory/index', [
            'title'      => __('admin.inventory.title'),
            'rows'       => $rows,
            'level'      => $level,
            'threshold'  => StockService::threshold(),
            'deltas'     => StockService::presetDeltas(),
            'lowCount'   => StockService::countLow(),
            'outCount'   => StockService::countOut(),
            'categories' => Category::all('`name` ASC'),
        ]);
    }

    public function adjust(Request $request): Response
    {
        $variantId = $this->id($request, 'variantId');
        $delta     = max(-100000, min(100000, $request->int('delta')));

        if ($delta === 0) {
            return $this->redirectWithErrors('/admin/inventory', [
                'delta' => __('validation.positive', ['field' => 'Ajustement']),
            ], $request->all());
        }

        $reason = $request->str('reason');

        $result = StockService::adjust($variantId, $delta, $reason);

        if (!$result['ok']) {
            return $this->redirectWithErrors('/admin/inventory', ['delta' => $result['error']], $request->all());
        }

        return $this->redirectWithSuccess('/admin/inventory', __('flash.stock_adjusted'));
    }
}