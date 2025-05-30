import Engine from '@ember/engine';
import loadInitializers from 'ember-load-initializers';
import Resolver from 'ember-resolver';
import config from './config/environment';
import services from '@fleetbase/ember-core/exports/services';
import { RouterControl } from '@fleetbase/fleetops-engine/services/leaflet-router-control';

const { modulePrefix } = config;
const externalRoutes = ['console', 'extensions'];
const FLEETOPS_ENGINE_NAME = '@fleetbase/fleetops-engine';
const L = window.L ?? {};

export default class ValhallaEngine extends Engine {
    modulePrefix = modulePrefix;
    Resolver = Resolver;
    dependencies = {
        services,
        externalRoutes,
    };
    engineDependencies = [FLEETOPS_ENGINE_NAME];
    /* eslint no-unused-vars: "off" */
    setupExtension = function (app, engine, universe) {
        const routeOptimization = app.lookup('service:route-optimization');
        const valhalla = app.lookup('service:valhalla');
        if (routeOptimization && valhalla) {
            routeOptimization.register('valhalla', valhalla);
        }

        // Register Valhalla Routing Control
        const leafletRouterControl = app.lookup('service:leaflet-router-control');
        if (leafletRouterControl) {
            leafletRouterControl.register(
                'valhalla',
                new RouterControl({
                    name: 'Valhalla',
                    router: new L.Routing.Valhalla(),
                    formatter: new L.Routing.Valhalla.Formatter(),
                })
            );
        }
    };
}

loadInitializers(ValhallaEngine, modulePrefix);
