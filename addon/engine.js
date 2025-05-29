import Engine from '@ember/engine';
import loadInitializers from 'ember-load-initializers';
import Resolver from 'ember-resolver';
import config from './config/environment';
import services from '@fleetbase/ember-core/exports/services';

const { modulePrefix } = config;
const externalRoutes = ['console', 'extensions'];
const FLEETOPS_ENGINE_NAME = '@fleetbase/fleetops-engine';

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
    };
}

loadInitializers(ValhallaEngine, modulePrefix);
