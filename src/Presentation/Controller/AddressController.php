<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Address\AddressExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class AddressController
{
    private AddressExecution $execution;

    public function __construct(AddressExecution $execution)
    {
        $this->execution = $execution;
    }

    public function list(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $addresses = $this->execution->list($userId);
        return ApiResponse::success($addresses);
    }

    public function create(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $address = $this->execution->create($userId, $request->getBody());
        return ApiResponse::success($address, [], 201);
    }

    /**
     * @param array{id: string} $params
     */
    public function setDefault(Request $request, array $params): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $address = $this->execution->setDefault($userId, $params['id']);
        return ApiResponse::success($address);
    }

    /**
     * @param array{id: string} $params
     */
    public function delete(Request $request, array $params): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $this->execution->delete($userId, $params['id']);
        return ApiResponse::success(['deleted' => true, 'id' => $params['id']]);
    }
}
