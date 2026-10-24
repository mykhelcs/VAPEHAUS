<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Catalog\GetCatalogFacetsExecution;
use Vapehaus\Execution\Catalog\GetProductDetailExecution;
use Vapehaus\Execution\Catalog\ListCategoriesExecution;
use Vapehaus\Execution\Catalog\ListProductsExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class CatalogController
{
    private ListCategoriesExecution $categoriesExecution;
    private ListProductsExecution $productsExecution;
    private GetProductDetailExecution $productDetailExecution;
    private ?GetCatalogFacetsExecution $facetsExecution;

    public function __construct(
        ListCategoriesExecution $categoriesExecution,
        ListProductsExecution $productsExecution,
        GetProductDetailExecution $productDetailExecution,
        ?GetCatalogFacetsExecution $facetsExecution = null
    ) {
        $this->categoriesExecution = $categoriesExecution;
        $this->productsExecution = $productsExecution;
        $this->productDetailExecution = $productDetailExecution;
        $this->facetsExecution = $facetsExecution;
    }

    public function getCategories(Request $request): Response
    {
        $categories = $this->categoriesExecution->execute(true);
        return ApiResponse::success($categories);
    }

    public function getProducts(Request $request): Response
    {
        $filters = $request->getQueryParams();
        $result = $this->productsExecution->execute($filters);

        return ApiResponse::success($result['products'], [
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
        ]);
    }

    public function getFacets(Request $request): Response
    {
        if ($this->facetsExecution === null) {
            return ApiResponse::success([]);
        }
        $facets = $this->facetsExecution->execute();
        return ApiResponse::success($facets);
    }

    /**
     * @param array{identifier: string} $params
     */
    public function getProductDetail(Request $request, array $params): Response
    {
        $product = $this->productDetailExecution->execute($params['identifier']);
        return ApiResponse::success($product);
    }
}
