<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ⚠️ `price`, `total`, `subtotal`, `discount` QABUL QILINMAYDI.
 *
 * Frontend ularni yuborsa ham `validated()` ga tushmaydi va
 * `OrderService` ga umuman yetib bormaydi — narx DB'dan qayta
 * hisoblanadi (CLAUDE.md §2.6, §2.7).
 *
 * Xuddi shunday `restaurant_id`, `table_id`, `session_id`, `waiter_id`,
 * `status` ham qabul qilinmaydi (docs/01-ARCHITECTURE.md §13).
 */
class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_order_uuid' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_order_uuid' => __('validation.attributes.client_order_uuid'),
            'items' => __('validation.attributes.items'),
        ];
    }

    /** @return list<array{product_id: int, quantity: int, note: string|null}> */
    public function lines(): array
    {
        return array_map(
            static fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'note' => $item['note'] ?? null,
            ],
            $this->validated('items'),
        );
    }
}
