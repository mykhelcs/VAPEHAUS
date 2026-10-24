<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Rma\RmaExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class RmaController
{
    private RmaExecution $execution;

    public function __construct(RmaExecution $execution)
    {
        $this->execution = $execution;
    }

    public function create(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $rma = $this->execution->create($userId, $request->getBody());
        return ApiResponse::success($rma, [], 201);
    }

    public function listUserReturns(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $returns = $this->execution->listUserReturns($userId);
        return ApiResponse::success($returns);
    }

    public function adminList(Request $request): Response
    {
        $returns = $this->execution->adminList($request->getQueryParams());
        return ApiResponse::success($returns);
    }

    /**
     * @param array{id: string} $params
     */
    public function adminUpdateStatus(Request $request, array $params): Response
    {
        $status = (string) $request->getBodyParam('status', '');
        $notes = $request->getBodyParam('admin_notes');
        $rma = $this->execution->adminUpdateStatus($params['id'], $status, $notes ? (string) $notes : null);
        return ApiResponse::success($rma);
    }
}
