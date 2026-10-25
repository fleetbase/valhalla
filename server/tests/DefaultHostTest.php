<?php

namespace Fleetbase\Valhalla\Tests;

use Fleetbase\Models\Setting;
use Fleetbase\Models\User;
use Fleetbase\Valhalla\Http\Controllers\ValhallaController;
use Fleetbase\Valhalla\Support\Authorization;
use Fleetbase\Valhalla\Support\Utils;
use Fleetbase\Valhalla\Support\Valhalla;
use PHPUnit\Framework\TestCase;

/**
 * Fleetbase Hosted Routing is the default Valhalla server, and the
 * OpenStreetMap community servers are never used, wherever they are set.
 */
class DefaultHostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->delete();
        app('session')->put('company', 'company-1');
        config(['valhalla.base_uri' => Valhalla::DEFAULT_BASE_URI, 'valhalla.api_key' => null]);
    }

    protected function tearDown(): void
    {
        Authorization::resolveUserUsing(null);
        app('session')->flush();

        parent::tearDown();
    }

    public function testTheShippedConfigDefaultsToFleetbaseHostedRouting(): void
    {
        $config = require __DIR__ . '/../config/valhalla.php';

        $this->assertSame('https://valhalla.routing.fleetbase.io', Valhalla::DEFAULT_BASE_URI);
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, $config['base_uri']);
        $this->assertStringNotContainsString('openstreetmap.de', file_get_contents(__DIR__ . '/../config/valhalla.php'));
    }

    public static function publicHosts(): array
    {
        return [
            ['https://valhalla1.openstreetmap.de', true],
            ['https://VALHALLA2.openstreetmap.de/', true],
            ['https://valhalla.routing.fleetbase.io', false],
            ['https://valhalla.example.com', false],
            [null, false],
        ];
    }

    /**
     * @dataProvider publicHosts
     */
    public function testRecognisesTheCommunityServers(?string $host, bool $public): void
    {
        $this->assertSame($public, Valhalla::isPublicHost($host));
    }

    public function testTheEnvironmentHostIsUsedUnlessItIsACommunityServer(): void
    {
        config(['valhalla.base_uri' => 'https://valhalla.example.com']);
        $this->assertSame('https://valhalla.example.com', Utils::resolveConfiguredBaseUri());

        config(['valhalla.base_uri' => 'https://valhalla1.openstreetmap.de']);
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, Utils::resolveConfiguredBaseUri());

        config(['valhalla.base_uri' => '']);
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, Utils::resolveConfiguredBaseUri());
    }

    public function testOrganizationThenSystemThenEnvironmentSkippingCommunityServers(): void
    {
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, Utils::resolveBaseUri());

        Setting::configure('valhalla', ['api_host' => 'https://valhalla1.openstreetmap.de']);
        Setting::configureCompany('valhalla', ['api_host' => 'https://valhalla2.openstreetmap.de']);
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, Utils::resolveBaseUri());
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, Utils::resolveSystemBaseUri());

        Setting::configure('valhalla', ['api_host' => 'https://system.example.com']);
        $this->assertSame('https://system.example.com', Utils::resolveBaseUri());

        Setting::configureCompany('valhalla', ['api_host' => 'https://org.example.com']);
        $this->assertSame('https://org.example.com', Utils::resolveBaseUri());
        // The system host never takes an organization's override.
        $this->assertSame('https://system.example.com', Utils::resolveSystemBaseUri());
    }

    public function testTheClientStartsOnTheDefaultServer(): void
    {
        config(['valhalla.base_uri' => 'https://valhalla1.openstreetmap.de']);
        $baseUri = (new \ReflectionProperty(Valhalla::class, 'baseUri'));
        $baseUri->setAccessible(true);

        $this->assertSame(Valhalla::DEFAULT_BASE_URI, $baseUri->getValue(new Valhalla()));
    }

    public function testTheAdminFormShowsTheServerInUseAndSavesTheDefault(): void
    {
        Authorization::resolveUserUsing(fn () => (new User())->forceFill(['type' => 'admin']));
        $controller = new ValhallaController(new Valhalla());

        Setting::configure('valhalla', ['api_host' => 'https://valhalla1.openstreetmap.de']);
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, $controller->getAdminSettings()->getData(true)['api_host']);

        $controller->saveAdminSettings(new \Illuminate\Http\Request());
        $this->assertSame(Valhalla::DEFAULT_BASE_URI, data_get(Setting::lookup('valhalla'), 'api_host'));
    }
}
