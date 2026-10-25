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
    @tracked hasApiKey = false;
    @tracked hasEnvironmentApiKey = false;
    @tracked clearApiKey = false;

    constructor() {
        super(...arguments);
        this.loadValhallaSettings.perform();
    }

    get apiKeyHelpText() {
        if (this.hasApiKey) {
            return 'A system API key is saved. It is never shown; leave this blank to keep it.';
        }

        if (this.hasEnvironmentApiKey) {
            return 'Using the API key from the server environment (VALHALLA_API_KEY). Enter a key to override it.';
        }

        return 'Optional API key for the system-wide Valhalla service.';
    }

    @task *loadValhallaSettings() {
        try {
            const { api_host, has_api_key, has_environment_api_key } = yield this.fetch.get('admin-settings', {}, { namespace: 'valhalla/int/v1' });
            this.apiHost = api_host;
            this.apiKey = null;
            this.hasApiKey = Boolean(has_api_key);
            this.hasEnvironmentApiKey = Boolean(has_environment_api_key);
            this.clearApiKey = false;
        } catch (err) {
            debug(`Valhalla : Error fetching admin valhalla settings: ${err.message}`);
        }
    }

    @task *saveValhallaSettings() {
        try {
            yield this.fetch.post(
                'admin-settings',
                {
                    api_key: this.clearApiKey ? null : this.apiKey,
                    clear_api_key: this.clearApiKey,
                    api_host: this.apiHost,
                },
                { namespace: 'valhalla/int/v1' }
            );
            this.notifications.success('Valhalla settings saved.');
            yield this.loadValhallaSettings.perform();
        } catch (err) {
            debug(`Valhalla : Error saving admin valhalla settings: ${err.message}`);
            this.notifications.serverError(err);
        }
    }
}
