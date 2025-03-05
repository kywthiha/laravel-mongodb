<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\MongoDB\Passport\RefreshToken;
use App\Models\MongoDB\Passport\Token;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request and return an access token.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Create a new access token
        $token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * Destroy an authenticated session (Logout and revoke token).
     */
    public function destroy(Request $request): JsonResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Revoke the user's access tokens
            $tokens = $user->tokens;
            foreach ($tokens as $token) {
                $token->revoke();
            }

            // Optionally, revoke refresh tokens
            Token::where('user_id', $user->id)->update(['revoked' => true]);
            RefreshToken::whereIn('access_token_id', $user->tokens->pluck('id'))->update(['revoked' => true]);

            return response()->json(['message' => 'Logged out successfully'], 200);
        }

        return response()->json(['message' => 'User not authenticated'], 401);
    }
}
