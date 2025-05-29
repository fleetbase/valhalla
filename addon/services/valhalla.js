import RouteOptimizationInterfaceService from '@fleetbase/fleetops-engine/services/route-optimization-interface';
import { debug } from '@ember/debug';

export default class ValhallaService extends RouteOptimizationInterfaceService {
    name = 'Valhalla';

    async optimize({ order, waypoints, coordinates }, options = {}) {
        const driverAssigned = order.driver_assigned;
        const driverPosition = driverAssigned?.location?.coordinates; // [lon,lat] | undefined
        const locations = (driverPosition ? [driverPosition, ...coordinates] : [...coordinates]).map(([lon, lat]) => {
            return { lat, lon };
        });
        const hasDriverStart = Boolean(driverPosition);

        try {
            const result = await this.#request('optimized-route', { locations, costing: 'auto' }, options);

            // Pair each Valhalla waypoint with its Waypoint model
            // Valhalla returns locations in the array sorted by optimization and a property `original_index`
            const modelsByInputIndex = hasDriverStart ? [null, ...waypoints] : waypoints;
            const pairs = result.trip.locations.map((wp) => ({
                model: modelsByInputIndex[wp.original_index],
                wp,
            }));

            // Drop the driver start if present
            const payloadPairs = hasDriverStart ? pairs.slice(1) : pairs;

            // Extract the Ember models (null-safe)
            const sortedWaypoints = payloadPairs.map((p) => p.model).filter(Boolean);

            return { sortedWaypoints, result };
        } catch (err) {
            debug(`[Valhalla] Error optimizing route : ${err.message}`);
            throw err;
        }
    }

    #request(path, data = {}, options = {}) {
        return this.fetch.post(path, data, { namespace: 'valhalla/int/v1', ...options });
    }
}
