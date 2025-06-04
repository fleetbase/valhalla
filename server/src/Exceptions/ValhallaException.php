<?php

namespace Fleetbase\Valhalla\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Thrown when a Valhalla API request fails.
 */
class ValhallaException extends \Exception
{
    protected int $statusCode;
    protected ?array $errorData;

    public function __construct(string $endpoint, Response $response)
    {
        $this->statusCode = $response->status();

        // Attempt to parse JSON error data
        try {
            $this->errorData = $response->json();
        } catch (\Throwable $e) {
            $this->errorData = null;
        }

        $message = sprintf(
            'Valhalla API [%s] request failed [%d]: %s',
            $endpoint,
            $this->statusCode,
            $response->body()
        );

        parent::__construct($message, $this->statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorData(): ?array
    {
        return $this->errorData;
    }
}
