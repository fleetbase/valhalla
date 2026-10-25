<?php

namespace Fleetbase\Valhalla\Support;

use Fleetbase\Models\Setting;
use Fleetbase\Support\Utils as FleetbaseUtils;

class Utils extends FleetbaseUtils
{
    public static function getOrganizationSettings(array $defaults = []): array
    {
        return Setting::lookupCompany('valhalla', $defaults);
    }

    public static function getSystemSettings(array $defaults = []): array
    {
        return Setting::lookup('valhalla', $defaults);
    }

    public static function resolveSetting(string $key, $defaultValue = null)
    {
        $organizationSettings = static::getOrganizationSettings();
        $organizationValue    = data_get($organizationSettings, $key);
        if (static::hasConfiguredValue($organizationValue) && static::organizationOverrideAllowed($organizationSettings)) {
            return $organizationValue;
        }

        $systemSettings = static::getSystemSettings();
        $systemValue    = data_get($systemSettings, $key);
        if (static::hasConfiguredValue($systemValue)) {
            return $systemValue;
        }

        return $defaultValue;
    }

    /**
     * The Valhalla server for the organization in session: its own host, then
     * the system host, then the environment, then Fleetbase Hosted Routing.
     * A host pointing at the OpenStreetMap community servers counts as unset.
     */
    public static function resolveBaseUri(): string
    {
        foreach ([data_get(static::getOrganizationSettings(), 'api_host'), data_get(static::getSystemSettings(), 'api_host')] as $host) {
            if (static::isUsableHost($host)) {
                return $host;
            }
        }

        return static::resolveConfiguredBaseUri();
    }

    /**
     * The system Valhalla server (admin settings, then the environment, then
     * Fleetbase Hosted Routing), ignoring any organization override.
     */
    public static function resolveSystemBaseUri(): string
    {
        $host = data_get(static::getSystemSettings(), 'api_host');

        return static::isUsableHost($host) ? $host : static::resolveConfiguredBaseUri();
    }

    /**
     * The environment's Valhalla server (VALHALLA_BASE_URI / VALHALLA_HOST),
     * or Fleetbase Hosted Routing when it is unset or a community server.
     */
    public static function resolveConfiguredBaseUri(): string
    {
        $host = config('valhalla.base_uri');

        return static::isUsableHost($host) ? $host : Valhalla::DEFAULT_BASE_URI;
    }

    protected static function isUsableHost($host): bool
    {
        return static::hasConfiguredValue($host) && !Valhalla::isPublicHost($host);
    }

    public static function resolveApiKey(): ?string
    {
        return static::resolveSetting('api_key', config('valhalla.api_key', env('VALHALLA_API_KEY')));
    }

    /**
     * An organization override whose host fails the host policy is ignored as a whole (host and key),
     * so values saved before the policy existed fall back to the system server.
     */
    public static function organizationOverrideAllowed(array $organizationSettings): bool
    {
        $host = data_get($organizationSettings, 'api_host');

        return !static::hasConfiguredValue($host) || HostPolicy::allows($host);
    }

    /**
     * Replace the stored API key with a `has_api_key` flag so responses never carry the key.
     */
    public static function withoutApiKey(array $settings): array
    {
        $settings['has_api_key'] = static::hasConfiguredValue(data_get($settings, 'api_key'));
        unset($settings['api_key']);

        return $settings;
    }

    /**
     * The API key to store after a settings save.
     *
     * The console never receives the stored key, so an empty or missing `api_key` keeps the stored one.
     * `clear_api_key` removes it.
     */
    public static function submittedApiKey(array $input, ?string $storedApiKey): ?string
    {
        if (filter_var(data_get($input, 'clear_api_key', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $submitted = data_get($input, 'api_key');
        if (is_string($submitted) && trim($submitted) !== '') {
            return trim($submitted);
        }

        return static::hasConfiguredValue($storedApiKey) ? $storedApiKey : null;
    }

    protected static function hasConfiguredValue($value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }
}
