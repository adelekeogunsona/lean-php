<?php

declare(strict_types=1);

namespace App\Controllers;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class HealthController
{
    public function index(Request $request): Response
    {
        return Response::json([
            'status' => 'ok',
            'timestamp' => date('c'),
            'uptime' => $this->getUptime(),
        ]);
    }

    private function getUptime(): string
    {
        // Simple uptime based on file modification time
        $startTime = filemtime(__DIR__ . '/../../public/index.php');
        $uptime = time() - $startTime;

        return sprintf('%d seconds', $uptime);
    }
}
