export function initialize(owner) {
    const valhallaRoutingService = owner.lookup('service:valhalla-routing');
    if (valhallaRoutingService) {
        valhallaRoutingService.initialize();
    }
}

export default {
    name: 'load-valhalla-routing',
    initialize,
};
