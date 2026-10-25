import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';

export default class OrganizationValhallaSettingsComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked useOwnServer = false;
    @tracked apiKey;
    @tracked apiHost;
    @tracked hasApiKey = false;
    @tracked clearApiKey = false;

    get saveTaskKey() {
        return 'organization:valhalla-settings';
    }

    constructor(owner, args) {
        super(owner, args);
        args?.controller?.registerSaveTask(this.saveTaskKey, this.saveValhallaSettings);
        this.loadValhallaSettings.perform();
    }

    willDestroy() {
        super.willDestroy(...arguments);
        this.controller?.unregisterSaveTask(this.saveTaskKey);
    }

    get controller() {
        return this.args.controller;
    }

    get isActive() {
        return this.controller?.displayEngine === 'valhalla' || this.controller?.optimizationEngine === 'valhalla';
    }

    @task *loadValhallaSettings() {
        try {
            const { api_host, has_api_key } = yield this.fetch.get('settings', {}, { namespace: 'valhalla/int/v1' });
            this.apiKey = null;
            this.apiHost = api_host;
            this.hasApiKey = Boolean(has_api_key);
            this.clearApiKey = false;
            this.useOwnServer = Boolean(this.hasApiKey || (typeof api_host === 'string' && api_host.trim() !== ''));
        } catch (err) {
            debug(`Valhalla : Error fetching organization valhalla settings: ${err.message}`);
        }
    }

    @task *saveValhallaSettings() {
        if (!this.isActive) {
            return true;
        }

        // The stored key is never sent to the console: a blank key keeps it, `clear_api_key` removes it.
        const clearApiKey = !this.useOwnServer || this.clearApiKey;

        try {
            yield this.fetch.post(
                'settings',
                {
                    api_key: clearApiKey ? null : this.apiKey,
                    clear_api_key: clearApiKey,
                    api_host: this.useOwnServer ? this.apiHost : null,
                },
                { namespace: 'valhalla/int/v1' }
            );
            yield this.loadValhallaSettings.perform();
        } catch (err) {
            debug(`Valhalla : Error saving organization valhalla settings: ${err.message}`);
            this.notifications.serverError(err);
        }
    }
}
