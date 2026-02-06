<?php

namespace App\Http\Controllers\Auth;

use App\Facades\Activity;
use App\Models\Passkey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PasskeyController extends Controller
{
    /**
     * Get registration options for creating a new passkey
     */
    public function registerOptions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Generate a random challenge
        $challenge = Str::random(32);
        
        // Store challenge in session for verification
        session(['passkey_challenge' => base64_encode($challenge)]);

        // Get app URL for relying party
        $rpId = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        $options = [
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => config('app.name', 'Pelican Panel'),
                'id' => $rpId,
            ],
            'user' => [
                'id' => base64_encode($user->uuid),
                'name' => $user->username,
                'displayName' => $user->username,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'cross-platform',
                'requireResidentKey' => false,
                'userVerification' => 'preferred',
            ],
            'excludeCredentials' => $user->passkeys()->get()->map(function ($passkey) {
                return [
                    'type' => 'public-key',
                    'id' => $passkey->credential_id,
                ];
            })->toArray(),
        ];

        return response()->json($options);
    }

    /**
     * Register a new passkey
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'credential' => 'required|array',
            'credential.id' => 'required|string',
            'credential.rawId' => 'required|string',
            'credential.type' => 'required|string',
            'credential.response' => 'required|array',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $credential = $request->input('credential');
        $name = $request->input('name', 'Passkey');

        // Basic validation - in production, you'd want to fully verify the attestation
        // For now, we'll just store the credential
        
        try {
            $passkey = $user->passkeys()->create([
                'name' => $name,
                'credential_id' => $credential['id'],
                'public_key_data' => json_encode($credential['response']),
                'counter' => 0,
            ]);

            Activity::event('user:passkey.created')
                ->property('name', $name)
                ->property('credential_id', $credential['id'])
                ->log();

            return response()->json([
                'success' => true,
                'passkey' => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'created_at' => $passkey->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register passkey: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authentication options for logging in with a passkey
     */
    public function authenticateOptions(Request $request): JsonResponse
    {
        // Generate a random challenge
        $challenge = Str::random(32);
        
        // Store challenge in session for verification
        session(['passkey_auth_challenge' => base64_encode($challenge)]);

        // Get app URL for relying party
        $rpId = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        $options = [
            'challenge' => base64_encode($challenge),
            'timeout' => 60000,
            'rpId' => $rpId,
            'userVerification' => 'preferred',
            'allowCredentials' => [], // Allow any registered passkey
        ];

        return response()->json($options);
    }

    /**
     * Authenticate using a passkey
     */
    public function authenticate(Request $request): JsonResponse
    {
        // This would require full WebAuthn verification
        // For now, return not implemented
        return response()->json([
            'success' => false,
            'message' => 'Passkey authentication not yet fully implemented',
        ], 501);
    }
}
