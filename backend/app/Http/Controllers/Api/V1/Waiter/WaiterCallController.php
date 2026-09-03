<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Enums\WaiterCallStatus;
use App\Http\Controllers\Controller;
use App\Models\WaiterCall;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Afitsant chaqiruvlari.
 *
 * ⚠️ SKELETON: mijoz tomonidan chaqiruv YARATISH va uni afitsantga
 * biriktirish PHASE 11 da (docs/03-PHASES.md). Hozir faqat o'qish —
 * ekran bo'sh ro'yxat ko'rsatadi va oqim tayyor bo'lgach ishlaydi.
 */
class WaiterCallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $calls = WaiterCall::query()
            ->whereIn('status', [
                WaiterCallStatus::PENDING->value,
                WaiterCallStatus::ASSIGNED->value,
                WaiterCallStatus::ACCEPTED->value,
            ])
            ->where(function ($query) use ($request): void {
                $query->whereNull('assigned_waiter_id')
                    ->orWhere('assigned_waiter_id', $request->user()->id);
            })
            ->with('table:id,number,name')
            ->latest('id')
            ->get();

        return ApiResponse::success([
            'items' => $calls->map(fn (WaiterCall $call): array => [
                'id' => $call->id,
                'status' => $call->status,
                'message' => $call->message,
                'table' => $call->table === null ? null : ['number' => $call->table->number],
                'created_at' => $call->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
