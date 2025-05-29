<?php

namespace Fleetbase\Valhalla\Support;

use Fleetbase\Valhalla\Exceptions\ValhallaException;
use Illuminate\Support\Facades\Http;

/**
 * Simple API wrapper for OpenStreetMap's Valhalla service.
 */
class Valhalla
{
    protected string $baseUri;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUri = config('valhalla.base_uri', 'https://valhalla1.openstreetmap.de');
        $this->apiKey  = config('valhalla.api_key');
    }

    /**
     * Computes a route with directions between points.
     *
     * @param array $payload JSON body with `locations`, `costing`, etc
     *
     * @throws ValhallaException
     */
    public function route(array $payload): array
    {
        return $this->post('route', $payload);
    }

    /**
     * Solves a simple routing optimization (CVRP) in one call.
     *
     * @param array $payload JSON body with `locations`, `costing`, etc
     *
     * @throws ValhallaException
     */
    public function optimizedRoute(array $payload): array
    {
        return $this->post('optimized_route', $payload);
    }

    /**
     * Isochrone endpoint: computes reachable area.
     *
     * @param array $payload JSON body as per Valhalla docs
     *
     * @throws ValhallaException
     */
    public function isochrone(array $payload): array
    {
        return $this->post('isochrone', $payload);
    }

    /**
     * Matrix endpoint: computes source-to-target travel times/distances.
     *
     * @param array $payload JSON body as per Valhalla docs
     *
     * @throws ValhallaException
     */
    public function matrix(array $payload): array
    {
        return $this->post('sources_to_targets', $payload);
    }

    protected function post(string $endpoint, array $payload): array
    {
        $url = rtrim($this->baseUri, '/') . '/' . $endpoint;

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new ValhallaException($endpoint, $response);
        }

        return $response->json();
    }
}
