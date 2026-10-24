<?php

namespace Fleetbase\Valhalla\Tests;

use Fleetbase\Models\Setting;
use Fleetbase\Models\User;
use Fleetbase\Valhalla\Http\Controllers\ValhallaController;
use Fleetbase\Valhalla\Support\Authorization;
use Fleetbase\Valhalla\Support\HostedRouting;
use Fleetbase\Valhalla\Support\Valhalla;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;

class HostedRoutingStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->delete();
        config(['valhalla.api_key' => null, 'valhalla.base_uri' => 'https://valhalla1.openstreetmap.de']);
        Http::swap(new \Illuminate\Http\Client\Factory());
    }

    protected function tearDown(): void
    {
        Authorization::resolveUserUsing(null);
        Authorization::resolvePermissionsUsing(null);

        parent::tearDown();
    }

    public static function hosts(): array
    {
        return [
            'root'           => ['https://routing.fleetbase.io', true],
            'prefixed'       => ['https://routing.fleetbase.io/valhalla', true],
            'subdomain'      => ['https://valhalla.routing.fleetbase.io', true],
            'mixed case'     => ['HTTPS://Valhalla.Routing.Fleetbase.IO/', true],
            'public server'  => ['https://valhalla1.openstreetmap.de', false],
            'look-alike'     => ['https://evilrouting.fleetbase.io.example.com', false],
            'suffix trick'   => ['https://notrouting.fleetbase.io', false],
            'no host'        => ['', false],
        ];
    }

    /**
     * @dataProvider hosts
     */
    public function testRecognisesOnlyTheHostedService(string $host, bool $hosted): void
    {
        $this->assertSame($hosted, HostedRouting::isHostedHost($host));
    }

    public function testAnotherServerIsNotHostedAndIsNeverCalled(): void
    {
        Http::fake();

        $this->assertSame(['hosted' => false], HostedRouting::status('https://valhalla.example.com', 'secret'));
        Http::assertNothingSent();
    }

    public function testTheHostedServiceWithoutAKeyPointsToTheSignupPage(): void
    {
        Http::fake();

        $status = HostedRouting::status('https://valhalla.routing.fleetbase.io', '  ');

        $this->assertSame('missing_key', $status['status']);
        $this->assertSame('https://console.fleetbase.io/~/routing', $status['signup_url']);
        $this->assertSame('https://console.fleetbase.io/settings/billing', $status['manage_url']);
        Http::assertNothingSent();
    }

    public function testReportsThePlanAndUsageWithoutTheKeyOrAccount(): void
    {
        Http::fake(['valhalla.routing.fleetbase.io/usage' => Http::response([
            'account'   => 'company-1', 'tier' => 'community', 'plan' => 'routing_community', 'fallback' => false,
            'rps'       => 5, 'burst' => 10, 'daily_quota' => 2500, 'used_today' => 120, 'remaining_today' => 2380,
            'resets_at' => '2026-10-25T00:00:00Z', 'source' => 'license',
        ])]);

        $status = HostedRouting::status('https://valhalla.routing.fleetbase.io/', 'fbrl_secret');

        $this->assertSame('ok', $status['status']);
        $this->assertSame([
            'tier'        => 'community', 'plan' => 'routing_community', 'fallback' => false, 'rps' => 5,
            'daily_quota' => 2500, 'used_today' => 120, 'remaining_today' => 2380, 'resets_at' => '2026-10-25T00:00:00Z',
        ], $status['usage']);
        $this->assertStringNotContainsString('fbrl_secret', json_encode($status));
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://valhalla.routing.fleetbase.io/usage'
            && $request->header('X-Api-Key') === ['fbrl_secret']);
    }

    public function testFlagsAnExhaustedQuota(): void
    {
        Http::fake(['*' => Http::response(['tier' => 'community', 'daily_quota' => 2500, 'used_today' => 2500, 'remaining_today' => 0])]);

        $this->assertSame('quota_reached', HostedRouting::status('https://routing.fleetbase.io/valhalla', 'fbrl_x')['status']);
    }

    public function testAnUnlimitedTierIsNeverReportedAsExhausted(): void
    {
        Http::fake(['*' => Http::response(['tier' => 'internal', 'daily_quota' => 0, 'used_today' => 9000, 'remaining_today' => null])]);

        $this->assertSame('ok', HostedRouting::status('https://routing.fleetbase.io', 'fbr_live_x')['status']);
    }

    public function testReportsARefusedKey(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid or revoked API key'], 401)]);

        $this->assertSame('invalid_key', HostedRouting::status('https://routing.fleetbase.io', 'fbrl_x')['status']);
    }

    public static function unreachable(): array
    {
        return [
            'server error'   => [fn () => Http::response(['error' => 'authentication temporarily unavailable'], 503)],
            'not json'       => [fn () => Http::response('<html></html>', 200)],
            'connection'     => [fn () => throw new ConnectionException('timed out')],
        ];
    }

    /**
     * @dataProvider unreachable
     */
    public function testReportsTheServiceAsUnreachable(\Closure $response): void
    {
        Http::fake(['*' => fn () => $response()]);

        $this->assertSame('unreachable', HostedRouting::status('https://routing.fleetbase.io', 'fbrl_x')['status']);
    }

    public function testTheConsoleLinksFollowConfiguration(): void
    {
        config(['valhalla.hosted.console_url' => 'https://console.example.test/']);

        $this->assertSame([
            'signup_url'  => 'https://console.example.test/~/routing',
            'upgrade_url' => 'https://console.example.test/~/routing',
            'manage_url'  => 'https://console.example.test/settings/billing',
        ], HostedRouting::links());
    }

    public function testTheAdminEndpointIsForSystemAdministratorsOnly(): void
    {
        Authorization::resolveUserUsing(fn () => (new User())->forceFill(['type' => 'user']));
        Authorization::resolvePermissionsUsing(fn () => true);

        $this->assertSame(401, $this->call('getAdminHostedStatus')->getStatusCode());
    }

    public function testTheAdminEndpointUsesTheSavedHostAndKeyThenTheEnvironment(): void
    {
        Authorization::resolveUserUsing(fn () => (new User())->forceFill(['type' => 'admin']));
        Http::fake(['*' => Http::response(['tier' => 'starter', 'daily_quota' => 25000, 'used_today' => 1, 'remaining_today' => 24999])]);

        Setting::configure('valhalla', ['api_host' => 'https://valhalla.routing.fleetbase.io', 'api_key' => 'fbrl_saved']);
        $saved = $this->call('getAdminHostedStatus')->getData(true);
        $this->assertSame('ok', $saved['status']);
        Http::assertSent(fn (HttpRequest $request) => $request->header('X-Api-Key') === ['fbrl_saved']);

        Setting::query()->delete();
        config(['valhalla.base_uri' => 'https://routing.fleetbase.io/valhalla', 'valhalla.api_key' => 'fbrl_env']);
        $this->assertSame('starter', $this->call('getAdminHostedStatus')->getData(true)['usage']['tier']);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://routing.fleetbase.io/valhalla/usage'
            && $request->header('X-Api-Key') === ['fbrl_env']);

        config(['valhalla.base_uri' => 'https://valhalla1.openstreetmap.de']);
        $this->assertSame(['hosted' => false], $this->call('getAdminHostedStatus')->getData(true));
    }

    /**
     * Run a controller action through the middleware it registers, as the router would.
     */
    protected function call(string $method)
    {
        $controller = new ValhallaController(new Valhalla());
        $request    = Request::create('/valhalla/int/v1/admin-hosted-status', 'GET');
        $pipeline   = fn () => $controller->{$method}($request);

        foreach (array_reverse($controller->getMiddleware()) as $middleware) {
            if (in_array($method, $middleware['options']['only'] ?? [], true)) {
                $next     = $pipeline;
                $pipeline = fn () => $middleware['middleware']($request, fn () => $next());
            }
        }

        try {
            return $pipeline();
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }
}
