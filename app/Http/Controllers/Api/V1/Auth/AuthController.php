<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\User;
use App\Services\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private readonly RefreshTokenService $refreshTokens) {}

    /**
     * Public customer self-registration. Staff roles are never created here.
     */
    public function register(RegisterRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_CUSTOMER,
                'status' => User::STATUS_ACTIVE,
            ]);

            Customer::create([
                'user_id' => $user->id,
                'phone' => $request->phone,
                'national_id' => $request->national_id,
                'date_of_birth' => $request->date_of_birth,
                'monthly_income' => $request->monthly_income,
                'employment_status' => $request->employment_status,
            ]);

            return $user;
        });

        return $this->issueTokenResponse($user, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => 'The provided credentials are incorrect.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return response()->json([
                'error' => [
                    'code' => 'account_inactive',
                    'message' => 'This account is not active.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->issueTokenResponse($user);
    }

    public function refresh(Request $request)
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        try {
            [$user, $newRefreshToken] = $this->refreshTokens->rotate($request->refresh_token);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'refresh_token_invalid',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = JWTAuth::fromUser($user);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ]);
    }

    public function logout(Request $request)
    {
        // Invalidate the access token that was presented...
        JWTAuth::invalidate(JWTAuth::getToken());

        // ...and revoke every refresh token for this user, so a stolen
        // refresh token can't outlive an explicit logout.
        $this->refreshTokens->revokeAllForUser($request->user()->id);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    private function issueTokenResponse(User $user, int $status = Response::HTTP_OK)
    {
        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->refreshTokens->issue($user);

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ], $status);
    }
}
