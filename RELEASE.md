> v0.0.5 ~ "Locked-down settings, Fleetbase Hosted Routing by default, and release CI"

---
## Highlights

- **Security: the Valhalla settings are locked down.** Before, any logged-in user could read the system API key and change the system host. Since fleetbase/fleetops#406, that host serves every organization's order distance and ETAs.
  - Only system administrators can read or change the system settings.
  - Organization settings need `fleet-ops update routing-settings`, or `view routing-settings` to read them.
  - The API key is never sent back to the console. A blank key keeps the stored one, and "Remove the saved API key" clears it. (#9)
- **Security: organization hosts must be public addresses.** A host that points at loopback, a private network or cloud metadata is refused on save (422). If one was saved earlier, it is ignored at request time and the system server is used. `VALHALLA_ALLOW_PRIVATE_HOSTS=true` turns this off for installs that run Valhalla on their own network. (#10)
- **Fleetbase Hosted Routing is the default server.** `https://valhalla.routing.fleetbase.io` replaces `valhalla1.openstreetmap.de`. A host set to the OpenStreetMap community servers, anywhere, now resolves to the default instead, because their usage policy does not allow production traffic. The hosted server needs a key: a free Community key (2,500 routes a day) or a paid tier, from Fleetbase Cloud at `/~/routing`, or minted at install by fleetbase/core-api#325. (#11)
- **The map route control goes through the server.** It posts to `valhalla/int/v1/route`, so it uses the configured server and key. The key never reaches the browser. (#11)
- **Plan and usage in Admin → Fleet-Ops → Routing.** When the host is Fleetbase Hosted Valhalla, the Valhalla panel shows one quiet line: the key's plan, today's usage, and Upgrade and Manage links. A second small line appears only when the key is missing, refused, over quota, back on the free tier or awaiting email confirmation. (#11)
- **Release CI like Fleet-Ops.**
  - PRs into `release/v*` run the Ember and PHP CI.
  - The Ember CI lints.
  - A `pnpm-workspace.yaml` (`allowBuilds`) fixes `ERR_PNPM_IGNORED_BUILDS` under pnpm 11, which had failed every Ember CI run on `main`.
  - PHP tests run again (#9).
- **Dependencies:** `@fleetbase/ember-core` 0.3.26 and `@fleetbase/ember-ui` 0.4.6, the same as Fleet-Ops.

---
## Upgrading
- No migrations.
- Without a key, `valhalla.routing.fleetbase.io` returns 401. Fleet-Ops then falls back to straight-line estimates, and the panel shows "Get a free key". To keep using your own Valhalla server, set its host in Admin → Fleet-Ops → Routing, or set `VALHALLA_HOST`.
- Organization settings now need the Fleet-Ops `routing-settings` permissions. Run `php artisan fleetbase:create-permissions` if they aren't seeded.

---
## Need help?
- [GitHub Discussions](https://github.com/fleetbase/fleetbase/discussions)
- [Discord](https://discord.gg/HnTqQ6zAVn)
