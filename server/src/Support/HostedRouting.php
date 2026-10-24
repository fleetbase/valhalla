<?php

namespace Fleetbase\Valhalla\Support;

use Fleetbase\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Fleetbase Hosted Routing: the Valhalla service at routing.fleetbase.io that
 * self-hosted installs can use with a license key (free Community tier or a
 * paid one, bought on Fleetbase Cloud at /~/routing).
 *
 * When the configured host is the hosted service, the gateway's `GET /usage`
 * reports the key's plan, limits and today's usage. The admin settings panel
 * shows that, with links to upgrade or manage the subscription on Cloud.
 *
 * A key the install got at install time (core-api `routing:register`, which
 * records it in the `system.hosted-routing` setting) has no Cloud account
 * until it is claimed. While the gateway reports it as install-owned, the
 * panel's Upgrade link is the claim link that attaches the key to a Cloud
 * account, and an unconfirmed admin email is pointed out.
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

        $registration = static::registration($apiKey);

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
            return static::withRegistration($status, $registration, null) + ['status' => static::STATUS_UNREACHABLE];
        }

        $usage = array_intersect_key($usage, array_flip([
            'tier', 'plan', 'fallback', 'owner', 'rps', 'daily_quota', 'used_today', 'remaining_today', 'resets_at',
        ]));
        $status       = static::withRegistration($status, $registration, $usage);
        $quotaReached = isset($usage['remaining_today']) && (int) $usage['remaining_today'] === 0 && (int) ($usage['daily_quota'] ?? 0) > 0;

        return $status + [
            'status' => $quotaReached ? static::STATUS_QUOTA : static::STATUS_OK,
            'usage'  => $usage,
        ];
    }

    /**
     * The install-time registration recorded for this key, if the saved key is
     * the one it issued (compared by its last four characters).
     */
    public static function registration(string $apiKey): ?array
    {
        $registration = Setting::lookup('system.hosted-routing');
        if (!is_array($registration) || empty($registration['key_hint'])) {
            return null;
        }

        return hash_equals((string) $registration['key_hint'], substr(trim($apiKey), -4)) ? $registration : null;
    }

    /**
     * Add the claim link and email state of an install-time key, while the
     * gateway does not report it as claimed by a Cloud account. The claim
     * link replaces the plain Upgrade link: it attaches the key to the account
     * the admin signs up with, so the upgrade keeps the key.
     */
    protected static function withRegistration(array $status, ?array $registration, ?array $usage): array
    {
        if ($registration === null || ($usage['owner'] ?? null) === 'company') {
            return $status;
        }

        $status['install_key'] = true;
        $status['email']       = $registration['email'] ?? null;
        if ($usage !== null) {
            $status['email_verified'] = ($usage['plan'] ?? null) !== 'routing_unverified';
        }

        if (!empty($registration['claim_url'])) {
            $status['claim_url']   = $registration['claim_url'];
            $status['upgrade_url'] = $registration['claim_url'];
        }

        return $status;
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
