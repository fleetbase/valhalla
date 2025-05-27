<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix(config('valhalla.api.routing.prefix', 'starter'))->namespace('Fleetbase\Valhalla\Http\Controllers')->group(
    function ($router) {
        /*
        |--------------------------------------------------------------------------
        | Valhalla API Routes
        |--------------------------------------------------------------------------
        |
        | Primary internal routes for console.
        */
        $router->prefix(config('valhalla.api.routing.internal_prefix', 'int'))->group(
            function ($router) {
                $router->group(
                    ['prefix' => 'v1', 'middleware' => ['fleetbase.protected']],
                    function ($router) {
                        // $router->fleetbaseRoutes('resource');
                    }
                );
            }
        );
    }
);
