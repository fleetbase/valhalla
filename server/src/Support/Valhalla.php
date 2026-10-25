<?php

namespace Fleetbase\Valhalla\Support;

use Fleetbase\Valhalla\Exceptions\ValhallaException;
use Illuminate\Support\Facades\Http;

/**
 * Simple API wrapper for a Valhalla routing server.
 */
class Valhalla
{
    /**
     * The default server: Fleetbase Hosted Routing. It needs a license key (a
     * free Community key, or a paid tier) from Fleetbase Cloud at /~/routing.
     */
    public const DEFAULT_BASE_URI = 'https://valhalla.routing.fleetbase.io';

    /**
     * The OpenStreetMap community demo servers. Their usage policy does not
     * allow production traffic, so a host pointing at them counts as unset
     * and the default is used instead (Fleet-Ops' ValhallaClient refuses them too).
     */
    public const PUBLIC_HOSTS = ['valhalla1.openstreetmap.de', 'valhalla2.openstreetmap.de'];

    protected string $baseUri;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUri = Utils::resolveConfiguredBaseUri();
        $this->apiKey  = config('valhalla.api_key');
    }

    /**
     * Whether a host is one of the OpenStreetMap community demo servers.
     */
    public static function isPublicHost(?string $host): bool
    {
        $hostname = strtolower((string) parse_url((string) $host, PHP_URL_HOST));

        return in_array($hostname, static::PUBLIC_HOSTS, true);
    }

    public function setBaseUri(?string $baseUri)
    {
        if ($baseUri) {
            $this->baseUri = $baseUri;
        }

        return $this;
    }

    public function setApiKey(?string $apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
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
        $url     = rtrim($this->baseUri, '/') . '/' . $endpoint;
        $request = Http::timeout(30)->withHeaders(['Content-Type' => 'application/json']);

        if ($this->apiKey) {
            $request = $request->withQueryParameters(['api_key' => $this->apiKey]);
        }

        $response = $request->post($url, $payload);

        if (!$response->successful()) {
            throw new ValhallaException($endpoint, $response);
        }

        return $response->json();
    }
}
