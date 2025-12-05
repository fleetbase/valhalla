import { RoutingControl } from '@fleetbase/fleetops-engine/services/leaflet-routing-control';

const L = window.L ?? {};
export function initialize(owner) {
    const universe = owner.lookup('service:universe');
    const app = universe.getApplicationInstance();
    const leafletRoutingControl = app.lookup('service:leaflet-routing-control');
    if (leafletRoutingControl) {
        leafletRoutingControl.register(
            'valhalla',
            new RoutingControl({
                name: 'Valhalla',
                router: new L.Routing.Valhalla(),
                formatter: new L.Routing.Valhalla.Formatter(),
            })
        );
    }
}

export default {
    initialize,
};
