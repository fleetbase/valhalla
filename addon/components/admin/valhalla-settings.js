import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';

export default class AdminValhallaSettingsComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked apiKey;
    @tracked apiHost;

    constructor() {
        super(...arguments);
        this.loadValhallaSettings.perform();
    }

    @task *loadValhallaSettings() {
        try {
            const { api_key, api_host } = yield this.fetch.get('admin-settings', {}, { namespace: 'valhalla/int/v1' });
            this.apiKey = api_key;
            this.apiHost = api_host;
        } catch (err) {
            debug(`Valhalla : Error fetching admin valhalla settings: ${err.message}`);
        }
    }

    @task *saveValhallaSettings() {
        try {
            yield this.fetch.post(
                'admin-settings',
                {
                    api_key: this.apiKey,
                    api_host: this.apiHost,
                },
                { namespace: 'valhalla/int/v1' }
            );
            this.notifications.success('Valhalla settings saved.');
        } catch (err) {
            debug(`Valhalla : Error saving admin valhalla settings: ${err.message}`);
            this.notifications.serverError(err);
        }
    }
}
