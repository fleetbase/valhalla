<?php

namespace Fleetbase\Valhalla\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;

class ValhallaResourceController extends FleetbaseController
{
    /**
     * The package namespace used to resolve from.
     */
    public string $namespace = '\Fleetbase\Valhalla';
}
