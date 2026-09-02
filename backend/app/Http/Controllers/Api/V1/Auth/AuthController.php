<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RestaurantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** docs/05-PHASE0-PLAN.md §3.3 — Sanctum token auth. */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        // Login paytida kontekst hali yo'q — foydalanuvchini topish
        // uchun scope ochiladi, keyin DARHOL yopiladi.
        RestaurantContext::allowCrossRestaurant();

        $user = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($request): void {
                $login = (string) $request->validated('login');

                $query->where('username', $login)
                    ->orWhere('email', $login)
                    ->orWhere('phone', $login);
            })
            ->first();

        RestaurantContext::reset();

        $secret = $request->validated('password') ?? $request->validated('pin');
        $hash = $request->validated('password') !== null ? $user?->password : $user?->pin;

        // Foydalanuvchi topilmasa ham Hash::check chaqiriladi — javob
        // vaqti bir xil qoladi va login mavjudligini aniqlab bo'lmaydi.
        if ($user === null || $hash === null || ! Hash::check((string) $secret, $hash)) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        // Restoran obunasi tugagan bo'lsa faqat ADMIN kira oladi —
        // u to'lov sahifasini ko'rishi kerak (docs/06-SAAS.md §4).
        if ($user->role === UserRole::WAITER
            && $user->restaurant !== null
            && ! $user->restaurant->subscription_status->isOperational()) {
            throw new BusinessException('SUBSCRIPTION_EXPIRED', 403);
        }

        $token = $user->createToken(
            'api',
            [$user->role->value],
            now()->addDays(30),
        )->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => $this->profile($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => $this->profile($request->user())]);
    }

    public function locale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:uz,ru'],
        ]);

        $request->user()->forceFill(['locale' => $validated['locale']])->save();

        return ApiResponse::success(['user' => $this->profile($request->user())]);
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'locale' => $user->locale,
            'status' => $user->status,
            'restaurant' => $user->restaurant === null ? null : [
                'name' => $user->restaurant->name,
                'slug' => $user->restaurant->slug,
                'logo' => $user->restaurant->logo,
                'currency' => $user->restaurant->currency,
                'subscription_status' => $user->restaurant->subscription_status,
                'expires_at' => $user->restaurant->expires_at?->toIso8601String(),
            ],
        ];
    }
}
