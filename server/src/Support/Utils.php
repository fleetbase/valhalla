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
        $organizationValue = data_get($organizationSettings, $key);
        if (static::hasConfiguredValue($organizationValue)) {
            return $organizationValue;
        }

        $systemSettings = static::getSystemSettings();
        $systemValue = data_get($systemSettings, $key);
        if (static::hasConfiguredValue($systemValue)) {
            return $systemValue;
        }

        return $defaultValue;
    }

    public static function resolveBaseUri(): string
    {
        return static::resolveSetting('api_host', config('valhalla.base_uri', env('VALHALLA_BASE_URI', 'https://valhalla1.openstreetmap.de')));
    }

    public static function resolveApiKey(): ?string
    {
        return static::resolveSetting('api_key', config('valhalla.api_key', env('VALHALLA_API_KEY')));
    }

    protected static function hasConfiguredValue($value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }
}
