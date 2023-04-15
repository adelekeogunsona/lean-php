<?php

declare(strict_types=1);

namespace App\Controllers;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;
use LeanPHP\DB\DB;
use LeanPHP\Validation\Validator;

class UserController
{
    public function index(Request $request): Response
    {
        // Get pagination parameters
        $limit = min((int) ($request->query('limit') ?? 20), 100); // Max 100 per page
        $offset = (int) ($request->query('offset') ?? 0);

        // Get total count
        $totalResult = DB::select('SELECT COUNT(*) as count FROM users');
        $total = (int) $totalResult[0]['count'];

        // Get users with pagination
        $users = DB::select('
            SELECT id, name, email, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ', [$limit, $offset]);

        // Build pagination links
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        $links = [];

        // Next link
        if ($offset + $limit < $total) {
            $nextOffset = $offset + $limit;
            $links[] = '<' . $baseUrl . '/v1/users?limit=' . $limit . '&offset=' . $nextOffset . '>; rel="next"';
        }

        // Previous link
        if ($offset > 0) {
            $prevOffset = max(0, $offset - $limit);
            $links[] = '<' . $baseUrl . '/v1/users?limit=' . $limit . '&offset=' . $prevOffset . '>; rel="prev"';
        }

        // First link
        if ($offset > 0) {
            $links[] = '<' . $baseUrl . '/v1/users?limit=' . $limit . '&offset=0>; rel="first"';
        }

        // Last link
        if ($offset + $limit < $total) {
            $lastOffset = max(0, $total - $limit);
            $links[] = '<' . $baseUrl . '/v1/users?limit=' . $limit . '&offset=' . $lastOffset . '>; rel="last"';
        }

        $response = Response::json([
            'users' => $users,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($users),
            ],
        ]);

        // Add pagination headers
        $response->header('X-Total-Count', (string) $total);

        if (!empty($links)) {
            $response->header('Link', implode(', ', $links));
        }

        return $response;
    }

    public function show(Request $request): Response
    {
        $params = $request->params();
        $id = (int) $params['id'];

        $users = DB::select('SELECT id, name, email, created_at FROM users WHERE id = ?', [$id]);

        if (empty($users)) {
            return Problem::make(404, 'Not Found', "User {$id} not found");
        }

        return Response::json([
            'user' => $users[0],
        ]);
    }

    public function create(Request $request): Response
    {
        $data = $request->json() ?? [];

        // Validate input
        $validator = Validator::make($data, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'scopes' => 'string|max:255', // Optional comma-separated scopes
        ]);

        $validator->validate();

        // Check if email already exists
        $existing = DB::select('SELECT id FROM users WHERE email = ?', [$data['email']]);
        if (!empty($existing)) {
            return Problem::make(422, 'Validation Failed', 'Email already exists', '/problems/validation', [
                'email' => ['The email has already been taken.']
            ]);
        }

        // Hash password with Argon2id
        $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID);

        // Default scopes if not provided
        $scopes = $data['scopes'] ?? 'users.read';

        // Insert new user
        DB::execute('
            INSERT INTO users (name, email, password, scopes, created_at)
            VALUES (?, ?, ?, ?, datetime("now"))
        ', [
            $data['name'],
            $data['email'],
            $hashedPassword,
            $scopes,
        ]);

        // Get the created user
        $users = DB::select('SELECT id, name, email, scopes, created_at FROM users WHERE email = ?', [$data['email']]);
        $user = $users[0];

        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';

        return Response::json([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'scopes' => explode(',', $user['scopes']),
                'created_at' => $user['created_at'],
            ],
        ], 201)->header('Location', $baseUrl . '/v1/users/' . $user['id']);
    }
}
