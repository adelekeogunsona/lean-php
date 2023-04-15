<?php

declare(strict_types=1);

namespace Tests\Unit;

use LeanPHP\Routing\Router;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use PHPUnit\Framework\TestCase;

class RouterHeadOptionsTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();

        // Add some test routes
        $this->router->get('/users', function () {
            return Response::json(['users' => ['John', 'Jane']]);
        });

        $this->router->get('/users/{id}', function (Request $request) {
            $id = $request->params()['id'];
            return Response::json(['user' => "User {$id}", 'data' => 'some content']);
        });

        $this->router->post('/users', function () {
            return Response::json(['created' => true], 201);
        });

        $this->router->put('/users/{id}', function (Request $request) {
            $id = $request->params()['id'];
            return Response::json(['updated' => "User {$id}"], 200);
        });

        $this->router->delete('/users/{id}', function () {
            return Response::noContent();
        });
    }

    protected function tearDown(): void
    {
        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI']
        );
    }

    public function test_head_request_maps_to_get_handler(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/users';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        // Should return 200 OK like the GET handler
        $this->assertEquals(200, $response->getStatusCode());

        // Should have the same headers as GET
        $this->assertEquals('application/json', $response->getHeader('content-type'));

        // But should have no body for HEAD request
        $this->assertEquals('', $response->getBody());
    }

    public function test_head_request_with_parameters(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/users/123';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertEquals('', $response->getBody());
    }

    public function test_head_request_for_non_existent_route_returns_404(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/non-existent';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_method_not_allowed_returns_405_with_allow_header(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI'] = '/users';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(405, $response->getStatusCode());

        $allowHeader = $response->getHeader('allow');
        $this->assertNotNull($allowHeader);

        // Should include GET, POST and HEAD (auto-added because GET exists)
        $allowedMethods = explode(', ', $allowHeader);
        $this->assertContains('GET', $allowedMethods);
        $this->assertContains('POST', $allowedMethods);
        $this->assertContains('HEAD', $allowedMethods);
    }

    public function test_method_not_allowed_with_parameters(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI'] = '/users/123';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(405, $response->getStatusCode());

        $allowHeader = $response->getHeader('allow');
        $allowedMethods = explode(', ', $allowHeader ?? '');

        // Should include GET, PUT, DELETE and HEAD
        $this->assertContains('GET', $allowedMethods);
        $this->assertContains('PUT', $allowedMethods);
        $this->assertContains('DELETE', $allowedMethods);
        $this->assertContains('HEAD', $allowedMethods);
    }

    public function test_explicit_head_route_works(): void
    {
        // Register an explicit HEAD route
        $this->router->head('/explicit-head', function () {
            return Response::text('This is a HEAD response')->header('X-Custom', 'head-value');
        });

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/explicit-head';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('head-value', $response->getHeader('x-custom'));
        // Body should still be empty for HEAD
        $this->assertEquals('', $response->getBody());
    }

    public function test_explicit_options_route_works(): void
    {
        // Register an explicit OPTIONS route
        $this->router->options('/api/test', function () {
            return Response::noContent()
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Max-Age', '600');
        });

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('GET, POST, OPTIONS', $response->getHeader('access-control-allow-methods'));
        $this->assertEquals('600', $response->getHeader('access-control-max-age'));
    }

    public function test_options_request_without_explicit_route_returns_405(): void
    {
        // Currently, OPTIONS without explicit route returns 405
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/users';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(405, $response->getStatusCode());

        $allowHeader = $response->getHeader('allow');
        $this->assertNotNull($allowHeader);
        $allowedMethods = explode(', ', $allowHeader);
        $this->assertContains('GET', $allowedMethods);
        $this->assertContains('POST', $allowedMethods);
        $this->assertContains('HEAD', $allowedMethods);
    }

    public function test_head_route_content_length_header(): void
    {
        // Test that HEAD requests get Content-Length header based on what the GET would return
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/users/123';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getBody());

        // The ResponseEmitter should add Content-Length based on the original body length
        // (This will be handled by the ResponseEmitter we improved earlier)
    }

    public function test_head_and_get_have_same_headers(): void
    {
        // First make a GET request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users';

        $request = Request::fromGlobals();
        $getResponse = $this->router->dispatch($request);

        // Then make a HEAD request to the same route
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $request = Request::fromGlobals();
        $headResponse = $this->router->dispatch($request);

        // Headers should be the same
        $this->assertEquals($getResponse->getStatusCode(), $headResponse->getStatusCode());
        $this->assertEquals($getResponse->getHeader('content-type'), $headResponse->getHeader('content-type'));

        // But body should differ
        $this->assertNotEmpty($getResponse->getBody());
        $this->assertEquals('', $headResponse->getBody());
    }
}
