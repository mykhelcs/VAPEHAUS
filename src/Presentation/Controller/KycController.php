<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Kyc\KycExecution;
use Vapehaus\Execution\Kyc\UploadKycDocumentExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class KycController
{
    private KycExecution $execution;
    private ?UploadKycDocumentExecution $uploadExecution;

    public function __construct(
        KycExecution $execution,
        ?UploadKycDocumentExecution $uploadExecution = null
    ) {
        $this->execution = $execution;
        $this->uploadExecution = $uploadExecution;
    }

    public function upload(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        if ($this->uploadExecution === null) {
            $this->uploadExecution = new UploadKycDocumentExecution();
        }

        $result = $this->uploadExecution->execute($userId, $request->getBody());
        return ApiResponse::success($result, [], 201);
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
