<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Biznes qoidasi buzilganda tashlanadi. Har doim `error_code` bilan keladi,
 * shuning uchun javob avtomatik ravishda ru+uz xabarga aylanadi.
 *
 * Misol: throw new BusinessException('ORDER_NOT_DELIVERED', 409);
 */
class BusinessException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly mixed $payload = null,
    ) {
        parent::__construct($errorCode);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->errorCode, $this->status, $this->payload);
    }
}
