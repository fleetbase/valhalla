import RouteOptimizationInterfaceService from '@fleetbase/fleetops-engine/services/route-optimization-interface';
import { isArray } from '@ember/array';
import { debug } from '@ember/debug';
import polyline from '@fleetbase/ember-core/utils/polyline';

export default class ValhallaService extends RouteOptimizationInterfaceService {
    name = 'Valhalla';

    async computeRoute(waypoints = [], options = {}) {
        const locations = (isArray(waypoints) ? waypoints : [])
            .map(([lat, lng], index, arr) => ({
                lat: Number(lat),
                lon: Number(lng),
                type: index === 0 || index === arr.length - 1 ? 'break' : 'through',
            }))
            .filter((location) => Number.isFinite(location.lat) && Number.isFinite(location.lon));

        if (locations.length < 2) {
            throw new Error('At least 2 waypoints are required to compute a route.');
        }

        const result = await this.#request('route', { locations, costing: options.costing ?? 'auto' }, options);
        return this.#normalizeRouteResult(result, waypoints);
    }

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
            const normalizedRoute = this.#normalizeRouteResult(
                result,
                sortedWaypoints.map((wp) => [wp.place.latitude, wp.place.longitude])
            );

            return {
                sortedWaypoints,
                route: normalizedRoute.coordinates,
                trip: {
                    distance: normalizedRoute.summary.totalDistance,
                    duration: normalizedRoute.summary.totalTime,
                    locations: result?.trip?.locations ?? [],
                },
                result,
                engine: 'valhalla',
            };
        } catch (err) {
            debug(`[Valhalla] Error optimizing route : ${err.message}`);
            throw err;
        }
    }

    #request(path, data = {}, options = {}) {
        return this.fetch.post(path, data, { namespace: 'valhalla/int/v1', ...options });
    }

    #normalizeRouteResult(result, waypoints = []) {
        const legs = isArray(result?.trip?.legs) ? result.trip.legs : [];
        const coordinates = [];

        legs.forEach((leg) => {
            const decoded = leg?.shape ? polyline.decode(leg.shape, 6) : [];
            decoded.forEach((coord) => coordinates.push(coord));
        });

        return {
            engine: 'valhalla',
            waypoints,
            coordinates,
            bounds: this.#boundsFromCoordinates(coordinates),
            summary: {
                totalDistance: result?.trip?.summary?.length ?? 0,
                totalTime: result?.trip?.summary?.time ?? 0,
            },
            legs,
            raw: result,
        };
    }

    #boundsFromCoordinates(coordinates = []) {
        if (!coordinates.length) {
            return [
                [0, 0],
                [0, 0],
            ];
        }

        const lats = coordinates.map(([lat]) => lat);
        const lngs = coordinates.map(([, lng]) => lng);

        return [
            [Math.min(...lats), Math.min(...lngs)],
            [Math.max(...lats), Math.max(...lngs)],
        ];
    }
}
