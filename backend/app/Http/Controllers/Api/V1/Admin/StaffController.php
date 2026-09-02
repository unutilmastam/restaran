<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Enums\WaiterStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffRequest;
use App\Models\User;
use App\Services\LimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Afitsantlar va qo'shimcha adminlar — docs/06-SAAS.md §1 (javob 4).
 *
 * Restoranda bir nechta ADMIN bo'lishi mumkin, lekin `OWNER_ADMIN`
 * bittaligicha qoladi va O'CHIRILMAYDI.
 */
class StaffController extends Controller
{
    public function __construct(private readonly LimitService $limits) {}

    public function index(Request $request): JsonResponse
    {
        $staff = User::query()
            ->when($request->query('role'), fn ($query, $role) => $query->where('role', $role))
            ->orderByRaw("FIELD(role, 'OWNER_ADMIN', 'ADMIN', 'WAITER')")
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'phone', 'role', 'status', 'locale', 'is_active', 'last_free_at']);

        return ApiResponse::success([
            'items' => $staff->map(fn (User $user): array => [
                ...$user->only(['id', 'name', 'username', 'phone', 'locale', 'is_active']),
                'role' => $user->role,
                'status' => $user->status,
                // Frontend "o'chirish" tugmasini shu bo'yicha yashiradi.
                'is_deletable' => $user->role->isDeletable(),
            ])->all(),
        ]);
    }

    public function store(StaffRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['role'] === UserRole::WAITER->value) {
            $this->limits->assertCanAddWaiter($request->user()->restaurant);
        }

        $user = User::create($data + [
            'status' => $data['role'] === UserRole::WAITER->value
                ? WaiterStatus::OFFLINE->value
                : null,
        ]);

        return ApiResponse::success(['staff' => $this->present($user)], null, 201);
    }

    public function update(StaffRequest $request, int $staff): JsonResponse
    {
        $model = User::query()->findOrFail($staff);

        $this->guardOwnerAdmin($model, $request->validated('role'));

        $data = array_filter(
            $request->validated(),
            static fn (mixed $value, string $key): bool => $key !== 'password' || $value !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        $model->update($data);

        return ApiResponse::success(['staff' => $this->present($model->refresh())]);
    }

    public function destroy(int $staff): JsonResponse
    {
        $model = User::query()->findOrFail($staff);

        // docs/06-SAAS.md §1 — restoran o'zini o'zi qulflab qo'ymasin.
        // Model `deleting` hodisasida ham bloklangan, bu — birinchi to'siq.
        if (! $model->role->isDeletable()) {
            throw new BusinessException('OWNER_ADMIN_PROTECTED', 403);
        }

        $model->delete();

        return ApiResponse::success(null);
    }

    /** OWNER_ADMIN rolini faqat SUPER_ADMIN o'zgartira oladi. */
    private function guardOwnerAdmin(User $model, ?string $newRole): void
    {
        if ($model->role === UserRole::OWNER_ADMIN && $newRole !== UserRole::OWNER_ADMIN->value) {
            throw new BusinessException('OWNER_ADMIN_PROTECTED', 403);
        }
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'username', 'phone', 'locale', 'is_active']),
            'role' => $user->role,
            'status' => $user->status,
            'is_deletable' => $user->role->isDeletable(),
        ];
    }
}
