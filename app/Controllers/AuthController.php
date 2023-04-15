<?php

declare(strict_types=1);

namespace App\Controllers;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;
use LeanPHP\DB\DB;
use LeanPHP\Auth\Token;
use LeanPHP\Validation\Validator;

class AuthController
{
    public function login(Request $request): Response
    {
        $data = $request->json() ?? [];

        // Validate input
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $validator->validate();

        $email = $data['email'];
        $password = $data['password'];

        // Find user by email
        $users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);

        if (empty($users)) {
            return Problem::make(401, 'Unauthorized', 'Invalid email or password');
        }

        $user = $users[0];

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return Problem::make(401, 'Unauthorized', 'Invalid email or password');
        }

        // Issue token with user scopes
        $scopes = !empty($user['scopes']) ? explode(',', $user['scopes']) : ['users.read'];
        $ttl = (int) ($_ENV['AUTH_TOKEN_TTL'] ?? 900); // Default 15 minutes

        $claims = [
            'sub' => (string) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'scopes' => $scopes,
        ];

        $token = Token::issue($claims, $ttl);

        return Response::json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'scopes' => $scopes,
            ],
        ]);
    }
}
