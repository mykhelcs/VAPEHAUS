<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Review\ReviewExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class ReviewController
{
    private ReviewExecution $execution;

    public function __construct(ReviewExecution $execution)
    {
        $this->execution = $execution;
    }

    /**
     * @param array{productId: string} $params
     */
    public function create(Request $request, array $params): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $review = $this->execution->create($userId, $params['productId'], $request->getBody());
        return ApiResponse::success($review, [], 201);
    }

    /**
     * @param array{productId: string} $params
     */
    public function listForProduct(Request $request, array $params): Response
    {
        $result = $this->execution->listForProduct($params['productId']);
        return ApiResponse::success($result['reviews'], ['rating_summary' => $result['rating_summary']]);
    }

    public function adminList(Request $request): Response
    {
        $reviews = $this->execution->adminList($request->getQueryParams());
        return ApiResponse::success($reviews);
    }

    /**
     * @param array{id: string} $params
     */
    public function adminModerate(Request $request, array $params): Response
    {
        $status = (string) $request->getBodyParam('status', 'approved');
        $review = $this->execution->adminModerate($params['id'], $status);
        return ApiResponse::success($review);
    }
}
