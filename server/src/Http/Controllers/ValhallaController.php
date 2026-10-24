<?php

namespace Fleetbase\Valhalla\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Models\Setting;
use Fleetbase\Valhalla\Exceptions\ValhallaException;
use Fleetbase\Valhalla\Support\HostPolicy;
use Fleetbase\Valhalla\Support\Utils;
use Fleetbase\Valhalla\Support\Valhalla;
use Fleetbase\Valhalla\Traits\AuthorizesMethods;
use Illuminate\Http\Request;

class ValhallaController extends Controller
{
    use AuthorizesMethods;

    /**
     * The Valhalla service instance.
     */
    protected Valhalla $valhalla;

    /**
     * Inject the Valhalla support class.
     */
    public function __construct(Valhalla $valhalla)
    {
        $this->valhalla = $valhalla;

        // The system settings set the Valhalla server every organization falls back to,
        // so only system administrators may read or change them. Organization overrides
        // sit on the Fleet-Ops routing settings page and use its permission.
        $this->authorizeMethods([
            'getSettings'       => ['fleet-ops view routing-settings', 'fleet-ops update routing-settings'],
            'saveSettings'      => 'fleet-ops update routing-settings',
            'getAdminSettings'  => 'admin',
            'saveAdminSettings' => 'admin',
        ]);
    }

    /**
     * Compute a turn-by-turn route.
     *
     * POST /valhalla/route
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function route(Request $request)
    {
        $payload = $request->all();
        $this->applyRuntimeSettings();

        try {
            $data = $this->valhalla->route($payload);

            return response()->json($data);
        } catch (ValhallaException $e) {
            $error = $e->getErrorData();

            return response()->error(
                $error['error'] ?? $e->getMessage(),
                $e->getStatusCode() ?? 400
            );
        } catch (\Exception $e) {
            return response()->error(
                config('app.debug') ? $e->getMessage() : 'Valhalla API request failed.'
            );
        }
    }

    /**
     * Solve an optimized route (CVRP).
     *
     * POST /valhalla/optimized_route
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function optimizedRoute(Request $request)
    {
        $payload = $request->all();
        $this->applyRuntimeSettings();

        try {
            $data = $this->valhalla->optimizedRoute($payload);

            return response()->json($data);
        } catch (ValhallaException $e) {
            $error = $e->getErrorData();

            return response()->error(
                $error['error'] ?? $e->getMessage(),
                $e->getStatusCode() ?? 400
            );
        } catch (\Exception $e) {
            return response()->error(
                config('app.debug') ? $e->getMessage() : 'Valhalla API request failed.'
            );
        }
    }

    /**
     * Compute an isochrone (reachable area).
     *
     * POST /valhalla/isochrone
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function isochrone(Request $request)
    {
        $payload = $request->all();
        $this->applyRuntimeSettings();

        try {
            $data = $this->valhalla->isochrone($payload);

            return response()->json($data);
        } catch (ValhallaException $e) {
            $error = $e->getErrorData();

            return response()->error(
                $error['error'] ?? $e->getMessage(),
                $e->getStatusCode() ?? 400
            );
        } catch (\Exception $e) {
            return response()->error(
                config('app.debug') ? $e->getMessage() : 'Valhalla API request failed.'
            );
        }
    }

    /**
     * Compute travel matrix from sources to targets.
     *
     * POST /valhalla/sources_to_targets
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function matrix(Request $request)
    {
        $payload = $request->all();
        $this->applyRuntimeSettings();

        try {
            $data = $this->valhalla->matrix($payload);

            return response()->json($data);
        } catch (ValhallaException $e) {
            $error = $e->getErrorData();

            return response()->error(
                $error['error'] ?? $e->getMessage(),
                $e->getStatusCode() ?? 400
            );
        } catch (\Exception $e) {
            return response()->error(
                config('app.debug') ? $e->getMessage() : 'Valhalla API request failed.'
            );
        }
    }

    /**
     * Get the organization's Valhalla override. The API key is reported as `has_api_key`, never returned.
     *
     * GET /valhalla/int/v1/settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSettings()
    {
        $settings = Utils::getOrganizationSettings([
            'api_host' => null,
            'api_key'  => null,
        ]);

        return response()->json(Utils::withoutApiKey($settings));
    }

    /**
     * Get the system Valhalla settings. The API key is reported as `has_api_key`, never returned.
     *
     * GET /valhalla/int/v1/admin-settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAdminSettings()
    {
        $settings = Utils::withoutApiKey(Utils::getSystemSettings([
            'api_host' => config('valhalla.base_uri', env('VALHALLA_BASE_URI', 'https://valhalla1.openstreetmap.de')),
            'api_key'  => null,
        ]));

        $environmentApiKey                     = config('valhalla.api_key', env('VALHALLA_API_KEY'));
        $settings['has_environment_api_key']   = is_string($environmentApiKey) && trim($environmentApiKey) !== '';

        return response()->json($settings);
    }

    /**
     * Save the organization's Valhalla override. An empty `api_key` keeps the stored key; `clear_api_key` removes it.
     * The host must pass the host policy (public http(s) addresses only), or the request fails with 422.
     *
     * POST /valhalla/int/v1/settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSettings(Request $request)
    {
        $apiHost = $request->input('api_host');
        if (is_string($apiHost) && trim($apiHost) !== '' && ($violation = HostPolicy::violation($apiHost))) {
            return response()->json(['errors' => [$violation]], 422);
        }

        $stored = Utils::getOrganizationSettings();

        Setting::configureCompany('valhalla', [
            'api_host' => $request->input('api_host'),
            'api_key'  => Utils::submittedApiKey($request->all(), data_get($stored, 'api_key')),
        ]);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Valhalla settings succesfully saved.',
        ]);
    }

    /**
     * Save the system Valhalla settings. An empty `api_key` keeps the stored key; `clear_api_key` removes it.
     *
     * POST /valhalla/int/v1/admin-settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveAdminSettings(Request $request)
    {
        $stored = Utils::getSystemSettings();

        Setting::configure('valhalla', [
            'api_host' => $request->input('api_host', config('valhalla.base_uri', env('VALHALLA_BASE_URI', 'https://valhalla1.openstreetmap.de'))),
            'api_key'  => Utils::submittedApiKey($request->all(), data_get($stored, 'api_key')),
        ]);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Valhalla settings succesfully saved.',
        ]);
    }

    protected function applyRuntimeSettings(): void
    {
        $this->valhalla
            ->setBaseUri(Utils::resolveBaseUri())
            ->setApiKey(Utils::resolveApiKey());
    }
}
