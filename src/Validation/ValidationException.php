<?php

declare(strict_types=1);

namespace LeanPHP\Validation;

use Exception;
use LeanPHP\Http\Response;

class ValidationException extends Exception
{
    private Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
        parent::__construct('Validation failed', 422);
    }

    /**
     * Get the validation response.
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}
