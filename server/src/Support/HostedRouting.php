<?php

namespace Fleetbase\Valhalla\Support;

use Illuminate\Support\Facades\Http;

/**
 * Fleetbase Hosted Routing: the Valhalla service at routing.fleetbase.io that
 * self-hosted installs can use with a license key (free Community tier or a
 * paid one, bought on Fleetbase Cloud at /~/routing).
 *
 * When the configured host is the hosted service, the gateway's `GET /usage`
 * reports the key's plan, limits and today's usage. The admin settings panel
 * shows that, with links to upgrade or manage the subscription on Cloud.
 */
class HostedRouting
{
    public const STATUS_OK           = 'ok';
    public const STATUS_QUOTA        = 'quota_reached';
    public const STATUS_MISSING_KEY  = 'missing_key';
    public const STATUS_INVALID_KEY  = 'invalid_key';
    public const STATUS_UNREACHABLE  = 'unreachable';

    /**
     * Whether a Valhalla host is the Fleetbase-hosted service
     * (routing.fleetbase.io or one of its subdomains).
     */
    public static function isHostedHost(?string $host): bool
    {
        $hostname = strtolower((string) parse_url((string) $host, PHP_URL_HOST));
        $domain   = strtolower((string) config('valhalla.hosted.domain', 'routing.fleetbase.io'));

        return $hostname !== '' && $domain !== '' && ($hostname === $domain || str_ends_with($hostname, '.' . $domain));
    }

    /**
     * The hosted-routing status of a host and key, for the settings panel.
     * Never includes the key.
     */
    public static function status(?string $host, ?string $apiKey): array
    {
        if (!static::isHostedHost($host)) {
            return ['hosted' => false];
        }

        $status = ['hosted' => true] + static::links();

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return $status + ['status' => static::STATUS_MISSING_KEY];
        }

        try {
            $response = Http::timeout((int) config('valhalla.hosted.timeout', 5))
                ->acceptJson()
                ->withHeaders(['X-Api-Key' => trim($apiKey)])
                ->get(rtrim((string) $host, '/') . '/usage');
        } catch (\Throwable $e) {
            return $status + ['status' => static::STATUS_UNREACHABLE];
        }

        if ($response->status() === 401) {
            return $status + ['status' => static::STATUS_INVALID_KEY];
        }

        $usage = $response->successful() ? $response->json() : null;
        if (!is_array($usage)) {
            return $status + ['status' => static::STATUS_UNREACHABLE];
        }

        $usage = array_intersect_key($usage, array_flip([
            'tier', 'plan', 'fallback', 'rps', 'daily_quota', 'used_today', 'remaining_today', 'resets_at',
        ]));
        $quotaReached = isset($usage['remaining_today']) && (int) $usage['remaining_today'] === 0 && (int) ($usage['daily_quota'] ?? 0) > 0;

        return $status + [
            'status' => $quotaReached ? static::STATUS_QUOTA : static::STATUS_OK,
            'usage'  => $usage,
        ];
    }

    /**
     * Where to get a key, upgrade it or manage its subscription on Fleetbase Cloud.
     */
    public static function links(): array
    {
        $console = rtrim((string) config('valhalla.hosted.console_url', 'https://console.fleetbase.io'), '/');

        return [
            'signup_url'  => $console . '/~/routing',
            'upgrade_url' => $console . '/~/routing',
            'manage_url'  => $console . '/settings/billing',
        ];
    }
}
