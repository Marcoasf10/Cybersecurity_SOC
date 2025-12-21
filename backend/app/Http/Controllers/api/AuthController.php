<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Client;

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
            return response()->json(["message" => 'Invalid credentials'], 401);
        }

        // Check if the user is soft-deleted
        if ($user->trashed()) {
            return response()->json(["message" => 'User has been deleted'], 401);
        }

        // Check if the user is blocked
        if ($user->blocked) {
            return response()->json(["message" => 'User is blocked and cannot login'], 401);
        }

        try {
            request()->request->add(
                $this->passportAuthenticationData($request->username, $request->password)
            );
            $request = Request::create('/oauth/token', 'POST');
            $response = Route::dispatch($request);
            $errorCode = $response->getStatusCode();
            $auth_server_response = json_decode((string) $response->content(), true);
            return response()->json($auth_server_response, $errorCode);
        } catch (\Exception $e) {
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

        return response()->json(['message' => 'Token revoked'], 200);
    }
}