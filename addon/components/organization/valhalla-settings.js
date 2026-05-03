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
            const { api_key, api_host } = yield this.fetch.get('settings', {}, { namespace: 'valhalla/int/v1' });
            this.apiKey = api_key;
            this.apiHost = api_host;
            this.useOwnServer = Boolean((typeof api_key === 'string' && api_key.trim() !== '') || (typeof api_host === 'string' && api_host.trim() !== ''));
        } catch (err) {
            debug(`Valhalla : Error fetching organization valhalla settings: ${err.message}`);
        }
    }

    @task *saveValhallaSettings() {
        if (!this.isActive) {
            return true;
        }

        try {
            yield this.fetch.post(
                'settings',
                {
                    api_key: this.useOwnServer ? this.apiKey : null,
                    api_host: this.useOwnServer ? this.apiHost : null,
                },
                { namespace: 'valhalla/int/v1' }
            );
        } catch (err) {
            debug(`Valhalla : Error saving organization valhalla settings: ${err.message}`);
            this.notifications.serverError(err);
        }
    }
}
