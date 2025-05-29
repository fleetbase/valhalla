<?php

namespace Fleetbase\Valhalla\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Valhalla\Exceptions\ValhallaException;
use Fleetbase\Valhalla\Support\Valhalla;
use Illuminate\Http\Request;

class ValhallaController extends Controller
{
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
}
