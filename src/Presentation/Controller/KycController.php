<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Kyc\KycExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class KycController
{
    private KycExecution $execution;

    public function __construct(KycExecution $execution)
    {
        $this->execution = $execution;
    }

    public function submit(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $kyc = $this->execution->submit($userId, $request->getBody());
        return ApiResponse::success($kyc, [], 201);
    }

    public function getStatus(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $kyc = $this->execution->getStatus($userId);
        return ApiResponse::success($kyc);
    }

    public function adminList(Request $request): Response
    {
        $records = $this->execution->adminList($request->getQueryParams());
        return ApiResponse::success($records);
    }

    /**
     * @param array{id: string} $params
     */
    public function adminReview(Request $request, array $params): Response
    {
        $record = $this->execution->adminReview($params['id'], $request->getBody());
        return ApiResponse::success($record);
    }
}
