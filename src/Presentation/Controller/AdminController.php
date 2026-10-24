<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Domain\Repository\InventoryAuditRepositoryInterface;
use Vapehaus\Execution\Admin\AdminCreateCategoryExecution;
use Vapehaus\Execution\Admin\AdminCreateProductExecution;
use Vapehaus\Execution\Admin\AdminCreateVariantExecution;
use Vapehaus\Execution\Admin\AdminGetDashboardExecution;
use Vapehaus\Execution\Admin\AdminListCustomersExecution;
use Vapehaus\Execution\Admin\AdminListOrdersExecution;
use Vapehaus\Execution\Admin\AdminUpdateOrderStatusExecution;
use Vapehaus\Execution\Admin\AdminUpdateStockExecution;
use Vapehaus\Execution\Order\AdminShipOrderExecution;
use Vapehaus\Execution\Order\GetOrderDetailExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class AdminController
{
    private AdminGetDashboardExecution $dashboardExecution;
    private AdminCreateCategoryExecution $createCategoryExecution;
    private AdminCreateProductExecution $createProductExecution;
    private AdminCreateVariantExecution $createVariantExecution;
    private AdminUpdateStockExecution $updateStockExecution;
    private AdminListOrdersExecution $listOrdersExecution;
    private GetOrderDetailExecution $orderDetailExecution;
    private AdminUpdateOrderStatusExecution $updateOrderStatusExecution;
    private AdminListCustomersExecution $listCustomersExecution;
    private ?AdminShipOrderExecution $shipOrderExecution;
    private ?InventoryAuditRepositoryInterface $auditRepo;

    public function __construct(
        AdminGetDashboardExecution $dashboardExecution,
        AdminCreateCategoryExecution $createCategoryExecution,
        AdminCreateProductExecution $createProductExecution,
        AdminCreateVariantExecution $createVariantExecution,
        AdminUpdateStockExecution $updateStockExecution,
        AdminListOrdersExecution $listOrdersExecution,
        GetOrderDetailExecution $orderDetailExecution,
        AdminUpdateOrderStatusExecution $updateOrderStatusExecution,
        AdminListCustomersExecution $listCustomersExecution,
        ?AdminShipOrderExecution $shipOrderExecution = null,
        ?InventoryAuditRepositoryInterface $auditRepo = null
    ) {
        $this->dashboardExecution = $dashboardExecution;
        $this->createCategoryExecution = $createCategoryExecution;
        $this->createProductExecution = $createProductExecution;
        $this->createVariantExecution = $createVariantExecution;
        $this->updateStockExecution = $updateStockExecution;
        $this->listOrdersExecution = $listOrdersExecution;
        $this->orderDetailExecution = $orderDetailExecution;
        $this->updateOrderStatusExecution = $updateOrderStatusExecution;
        $this->listCustomersExecution = $listCustomersExecution;
        $this->shipOrderExecution = $shipOrderExecution;
        $this->auditRepo = $auditRepo;
    }

    public function getDashboard(Request $request): Response
    {
        $metrics = $this->dashboardExecution->execute();
        return ApiResponse::success($metrics);
    }

    public function createCategory(Request $request): Response
    {
        $category = $this->createCategoryExecution->execute($request->getBody());
        return ApiResponse::success($category, [], 201);
    }

    public function createProduct(Request $request): Response
    {
        $product = $this->createProductExecution->execute($request->getBody());
        return ApiResponse::success($product, [], 201);
    }

    /**
     * @param array{id: string} $params
     */
    public function createVariant(Request $request, array $params): Response
    {
        $variant = $this->createVariantExecution->execute($params['id'], $request->getBody());
        return ApiResponse::success($variant, [], 201);
    }

    /**
     * @param array{id: string} $params
     */
    public function updateStock(Request $request, array $params): Response
    {
        $variant = $this->updateStockExecution->execute($params['id'], $request->getBody());
        return ApiResponse::success($variant);
    }

    public function getOrders(Request $request): Response
    {
        $filters = $request->getQueryParams();
        $result = $this->listOrdersExecution->execute($filters);

        return ApiResponse::success($result['orders'], [
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
        ]);
    }

    /**
     * @param array{id: string} $params
     */
    public function getOrderDetail(Request $request, array $params): Response
    {
        $order = $this->orderDetailExecution->execute($params['id'], null, true);
        return ApiResponse::success($order);
    }

    /**
     * @param array{id: string} $params
     */
    public function updateOrderStatus(Request $request, array $params): Response
    {
        $newStatus = (string) $request->getBodyParam('status', '');
        $order = $this->updateOrderStatusExecution->execute($params['id'], $newStatus);
        return ApiResponse::success($order);
    }

    /**
     * @param array{id: string} $params
     */
    public function shipOrder(Request $request, array $params): Response
    {
        if ($this->shipOrderExecution === null) {
            return ApiResponse::error('NOT_IMPLEMENTED', 'Ship order execution is not configured.');
        }
        $order = $this->shipOrderExecution->execute($params['id'], $request->getBody());
        return ApiResponse::success($order);
    }

    public function getCustomers(Request $request): Response
    {
        $page = (int) $request->getQueryParam('page', 1);
        $limit = (int) $request->getQueryParam('limit', 50);

        $result = $this->listCustomersExecution->execute($page, $limit);

        return ApiResponse::success($result['customers'], [
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
        ]);
    }

    public function getInventoryLogs(Request $request): Response
    {
        if ($this->auditRepo === null) {
            return ApiResponse::success([]);
        }
        $page = max(1, (int) $request->getQueryParam('page', 1));
        $limit = min(100, max(1, (int) $request->getQueryParam('limit', 50)));
        $offset = ($page - 1) * $limit;

        $logs = $this->auditRepo->findAll($limit, $offset);
        $total = $this->auditRepo->count();

        return ApiResponse::success(
            array_map(fn ($l) => $l->toArray(), $logs),
            ['total' => $total, 'page' => $page, 'limit' => $limit]
        );
    }
}
