<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Client;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Get password grant client from DB
     */
    private function getPasswordGrantClient(): Client
    {
        return Client::where('password_client', true)
                     ->where('revoked', false)
                     ->firstOrFail();
    }

    /**
     * Prepare data for /oauth/token
     */
    private function passportAuthenticationData(string $username, string $password): array
    {
        $client = $this->getPasswordGrantClient();

        return [
            'grant_type'    => 'password',
            'client_id'     => $client->id,
            'client_secret' => $client->secret,
            'username'      => $username,
            'password'      => $password,
            'scope'         => '',
        ];
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        // Find the user by username
        $user = User::withTrashed()->where('username', $request->username)->first();

        // Check if the user does not exist
        if (!$user) {
            $this->logAuthFailure(null, $request->username, 'user_not_found', $ip);
            return response()->json(["message" => 'Invalid credentials'], 401);
        }

        // Check if the user is soft-deleted
        if ($user->trashed()) {
            $this->logAuthFailure($user->id, $request->username, 'user_deleted', $ip);
            return response()->json(["message" => 'User has been deleted'], 401);
        }

        // Check if the user is blocked
        if ($user->blocked) {
            $this->logAuthFailure($user->id, $request->username, 'user_blocked', $ip);
            return response()->json(["message" => 'User is blocked and cannot login'], 401);
        }

        try {
            request()->request->add(
                $this->passportAuthenticationData($request->username, $request->password)
            );
            $request = Request::create('/oauth/token', 'POST');
            
            $response = Route::dispatch($request);
            $status = $response->getStatusCode();
            $data = json_decode((string) $response->content(), true);

            if ($status === 200) {
                Log::channel('soc')->info('auth.success', [
                    'event_type' => 'auth.success',
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);
            } else {
                $this->logAuthFailure($user->id, $request->username, 'invalid_password', $ip);
            }

            return response()->json($data, $status);
        } catch (\Exception $e) {

            $this->logAuthFailure(
                $user?->id,
                $request->username,
                'passport_exception',
                $request->ip()
            );

            return response()->json(["message" => 'Authentication has failed!'], 401);
        }
    }
    
    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No authenticated user'], 401);
        }

        $user->tokens()->each(function ($token) {
            $token->revoke();
            $token->delete();
        });

        Log::channel('soc')->info('auth.logout', [
            'event_type' => 'auth.logout',
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json(['message' => 'Token revoked'], 200);
    }

    // ---------- SOC HELPERS ----------

    private function logAuthFailure($userId, $username, $reason, $ip)
    {
        Log::channel('soc')->warning('auth.failed', [
            'event_type' => 'auth.failed',
            'user_id' => $userId,
            'username' => $username,
            'reason' => $reason,
            'ip' => $ip,
            'timestamp' => now()->toIso8601String()
        ]);
    }
}