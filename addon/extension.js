export default {
    setupExtension(app, universe) {
        // Register Valhalla Route Optimization
        universe.whenEngineLoaded('@fleetbase/fleetops-engine', this.registerValhalla);
    },

    async registerValhalla(fleetopsEngine, universe) {
        const valhallaEngine = await universe.extensionManager.ensureEngineLoaded('@fleetbase/valhalla-engine');
        const routeOptimization = fleetopsEngine.lookup('service:route-optimization');
        const valhalla = valhallaEngine.lookup('service:valhalla');
        if (routeOptimization && valhalla) {
            routeOptimization.register('valhalla', valhalla);
        }
    },
};
