import { ExtensionComponent } from '@fleetbase/ember-core/contracts';

export default {
    setupExtension(app, universe) {
        // Register Valhalla Route Optimization
        universe.whenEngineLoaded('@fleetbase/fleetops-engine', this.registerValhalla);

        // Register settings components
        universe.registerRenderableComponent('fleet-ops:template:settings:routing', new ExtensionComponent('@fleetbase/valhalla-engine', 'organization/valhalla-settings'));
        universe.registerRenderableComponent('fleet-ops:component:admin:routing-settings', new ExtensionComponent('@fleetbase/valhalla-engine', 'admin/valhalla-settings'));
    },

    async registerValhalla(fleetopsEngine, universe) {
        const valhallaEngine = await universe.extensionManager.ensureEngineLoaded('@fleetbase/valhalla-engine');
        const routeOptimization = fleetopsEngine.lookup('service:route-optimization');
        const routeEngine = fleetopsEngine.lookup('service:route-engine');
        const valhalla = valhallaEngine.lookup('service:valhalla');
        if (routeOptimization && valhalla) {
            routeOptimization.register('valhalla', valhalla);
        }
        if (routeEngine && valhalla) {
            routeEngine.register('valhalla', valhalla, { display: true });
        }
    },
};
