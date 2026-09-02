<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TableRequest;
use App\Models\Table;
use App\Services\LimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminTableController extends Controller
{
    public function __construct(private readonly LimitService $limits) {}

    public function index(): JsonResponse
    {
        $tables = Table::query()->orderBy('number')->get();

        return ApiResponse::success([
            'items' => $tables->map(fn (Table $table): array => $this->present($table))->all(),
        ]);
    }

    public function store(TableRequest $request): JsonResponse
    {
        $this->limits->assertCanAddTable($request->user()->restaurant);

        $table = Table::create($request->validated() + [
            'nfc_token' => $this->token(),
            'status' => TableStatus::AVAILABLE,
        ]);

        return ApiResponse::success(['table' => $this->present($table)], null, 201);
    }

    public function update(TableRequest $request, int $table): JsonResponse
    {
        $model = Table::query()->findOrFail($table);
        $model->update($request->validated());

        return ApiResponse::success(['table' => $this->present($model->refresh())]);
    }

    /** Tag yo'qolsa yoki nusxa ko'chirilsa — eski token darhol o'lik. */
    public function regenerateToken(int $table): JsonResponse
    {
        $model = Table::query()->findOrFail($table);
        $model->forceFill(['nfc_token' => $this->token()])->save();

        return ApiResponse::success(['table' => $this->present($model->refresh())]);
    }

    public function destroy(int $table): JsonResponse
    {
        Table::query()->findOrFail($table)->delete();

        return ApiResponse::success(null);
    }

    /** @return array<string, mixed> */
    private function present(Table $table): array
    {
        $base = rtrim((string) config('app.customer_url', config('app.url')), '/');

        return [
            'id' => $table->id,
            'number' => $table->number,
            'name' => $table->name,
            'capacity' => $table->capacity,
            'status' => $table->status,
            'is_active' => $table->is_active,
            'nfc_token' => $table->nfc_token,
            // NFC tagga yoziladigan URL (docs/06-SAAS.md §7).
            'nfc_url' => sprintf(
                '%s/r/%s/t/%s',
                $base,
                $table->restaurant->slug,
                $table->nfc_token,
            ),
        ];
    }

    private function token(): string
    {
        return Str::random(64);
    }
}
