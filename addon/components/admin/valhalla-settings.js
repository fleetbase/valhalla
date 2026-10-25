import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';

const HOSTED_PLAN_NAMES = {
    routing_unverified: 'Community (free, email not confirmed)',
    unverified: 'Community (free, email not confirmed)',
    routing_community: 'Community (free)',
    routing_starter: 'Starter',
    routing_growth: 'Growth',
    routing_scale: 'Scale',
    community: 'Community (free)',
    internal: 'Fleetbase Cloud',
};

export default class AdminValhallaSettingsComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked apiKey;
    @tracked apiHost;
    @tracked hasApiKey = false;
    @tracked hasEnvironmentApiKey = false;
    @tracked clearApiKey = false;
    @tracked hosted = null;

    /**
     * Fleetbase Hosted Routing (routing.fleetbase.io) status for the saved host and key:
     * plan, limits and today's usage, plus links to get, upgrade or manage the key on Cloud.
     */
    get hostedSummary() {
        const usage = this.hosted?.usage;
        if (!usage) {
            return null;
        }

        const plan = HOSTED_PLAN_NAMES[usage.plan] ?? HOSTED_PLAN_NAMES[usage.tier] ?? usage.tier;
        const used = Number(usage.used_today ?? 0).toLocaleString();
        const quota = Number(usage.daily_quota ?? 0);

        return {
            plan,
            usage: quota > 0 ? `${used} of ${quota.toLocaleString()} routes used today` : `${used} routes today, no daily limit`,
            fallback: Boolean(usage.fallback),
            isFree: ['routing_community', 'routing_unverified'].includes(usage.plan) || ['community', 'unverified'].includes(usage.tier),
        };
    }

    /**
     * A key this install got at install time and that no Cloud account has
     * claimed yet. Its Upgrade link (`upgrade_url`) is the claim link, and
     * there is no subscription to manage until it is claimed.
     */
    get isInstallKey() {
        return Boolean(this.hosted?.install_key);
    }

    get emailNotice() {
        if (!this.isInstallKey || this.hosted?.email_verified !== false) {
            return null;
        }

        const to = this.hosted.email ? ` sent to ${this.hosted.email}` : '';

        return `Confirm the email${to} to raise this key to 2,500 routes a day. Until then it has 500.`;
    }

    get isHostedKeyMissing() {
        return this.hosted?.status === 'missing_key';
    }

    get hostedMessage() {
        switch (this.hosted?.status) {
            case 'missing_key':
                return 'Fleetbase Hosted Routing needs a license key. Get a free one (2,500 routes a day) on Fleetbase Cloud.';
            case 'invalid_key':
                return 'Fleetbase Hosted Routing refused this key: it is wrong or was revoked. Routes, distances and ETAs fall back to straight-line estimates.';
            case 'unreachable':
                return 'Fleetbase Hosted Routing could not be reached to check this key. Routing keeps working if the key is valid.';
            case 'quota_reached':
                return "Today's route quota is used up. Until 00:00 UTC, distances and ETAs fall back to straight-line estimates.";
            default:
                return null;
        }
    }

    constructor() {
        super(...arguments);
        this.loadValhallaSettings.perform();
    }

    @task *loadHostedStatus() {
        try {
            this.hosted = yield this.fetch.get('admin-hosted-status', {}, { namespace: 'valhalla/int/v1' });
        } catch (err) {
            this.hosted = null;
            debug(`Valhalla : Error fetching hosted routing status: ${err.message}`);
        }
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

        yield this.loadHostedStatus.perform();
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
