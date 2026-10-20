# CLAUDE.md - cs-extension-manager

Project notes for Claude. Layers on top of `~/.claude/CLAUDE.md` and the vault's `CLAUDE.md`.

## What this repo is

Cybersalt Extension Manager: a free Joomla 5/6 package that installs, updates and looks after every Cybersalt extension from one admin screen (the Cybersalt answer to Regular Labs' Extension Manager and Extensions Manager CK). **v0.1.0 unreleased**, built overnight 2026-10-20 at Tim's request.

Vault note: `04.knowledge/cs-extension-manager.md` - history, decisions, open items, the competitor research. Update it on every meaningful change.

## Layout

```
packages/
├── com_csextensionmanager/admin/src/Helper/
│   ├── StoreClient.php      Every URL to cybersalt.com is built/checked here (isStoreUrl). getMany() = curl_multi.
│   ├── FeedParser.php       api.updatexml / api.changelogxml parsing, targetplatform matching. Pure, unit tested.
│   ├── CatalogHelper.php    Builds the catalog: api.packages → else seed.json + updatexml per product; + api.catalog add-ons.
│   │                        Cached in #__csextensionmanager_cache (key catalog.v1). Per-item fallback to last good data.
│   ├── InstalledHelper.php  #__extensions lookups (one query for the whole catalog), package children, labels.
│   ├── StateHelper.php      Catalog + installed → status/compat/entitled/requires_missing/update_type. Hides add-ons per Options.
│   ├── AccountHelper.php    Download ID: manual > own activation > MCP for J's pro_* params. register/linkemail/verifyaccess.
│   │                        syncUpdateSites() stamps the dlid on every store feed (extra_query + location).
│   ├── InstallHelper.php    install/uninstall/setEnabled/changelog. Fresh feed → store-only URL → SHA-256 → manifest identity check.
│   ├── HealthHelper.php     check() + fix(). Fixes are re-validated by issue id; SAFE_FIXES are the only ones Fix all applies.
│   ├── TaskHelper.php       The scheduled task: find/create (off)/enableEmails (Tim 2026-10-20: release emails are opt-in).
│   ├── UpdateRunner.php     Scheduled task body: refresh, optional auto-install by update size, email summary (fingerprinted).
│   ├── LogHelper.php        #__csextensionmanager_log + User Actions Log mirror. setTrigger() for task runs.
│   ├── ParamsHelper.php     Params straight from the DB, save() cleans _system. Every saved key has a hidden field in config.xml.
│   └── DisplayHelper.php    Badges, primary action, script options, support panel.
├── plg_installer_*          onInstallerBeforePackageDownload / BeforeUpdateSiteDownload (5.3+): add dlid when missing.
├── plg_quickicon_*          Quick icon with ajaxurl → extensions.quickicon (count fetched after the dashboard renders).
├── plg_task_*               csextensionmanager.check routine → UpdateRunner.
└── pkg_*                    script.php: enable plugins, seed ACL (Manager/Administrator view only), create the daily task SWITCHED OFF, sync dlid, card with "Email me about new releases" (extensions.enableemails → TaskHelper::enableEmails).
```

## Store API (cs-release-manager on www.cybersalt.com)

- `api.updatexml&element=X[&dlid=]`: one `<update>` (version, downloadurl, sha256, targetplatform, php_minimum, tags). Denied dlid → description "UPDATE NOT AVAILABLE TO THIS SITE: reason" + infourl. Cloudflare caches it 5 min.
- `api.changelogxml&element=X`: every version's notes.
- `api.catalog&catalog=cs-mcp-for-j` (json): the MCP add-ons, with target_extension, tier, advisory, superseded_by.
- `api.packages` (json): added in cs-release-manager **1.12.9** (on the store 2026-10-20), but it only answers once cybersalt.com itself is upgraded from 1.12.7; until then it 404s and the manager falls back to seed.json + updatexml. Spec in `docs/SERVER-API.md`. Add-ons (catalog_slug `cs-mcp-for-j`) are skipped here and come from `api.catalog`.
- `api.register` / `api.linkemail` / `api.verifyaccess`: activation. Anchor package `csmcpforjpro`.
- Free downloads: `api.userdownload`. Paid: `api.download&dlid=`.

## Security model (reviewed 2026-10-20: no HIGH; both MEDIUMs fixed)

- **Installs only from cybersalt.com.** `StoreClient::isTrustedHost()` = https, cybersalt.com / www.cybersalt.com, port 443, no userinfo, no entities/whitespace/backslashes. A dev store is only possible via `define('CSEXTENSIONMANAGER_STORE_URL', …)` in code: **never add it back as an Option** (Options can be granted without installer rights, and whoever sets the store decides what's installed).
- **No automatic redirects.** `StoreClient::httpOptions()` sets `follow_location=false` and curl HTTPS-only protocols (Joomla's curl transport follows redirects to anywhere by default). `InstallHelper::download()` follows hops by hand: https only, and off cybersalt.com only when a SHA-256 will be checked.
- **Fail closed:** no fresh `updatexml` answer, no install. Older versions (rollback) have no published hash, so they can only come from cybersalt.com hosts.
- Requests name a catalog **key** only (`type:folder:element`, regex-checked, must exist in the catalog). Toggle ids must be the item's own or its package children. Health fixes are re-derived from a fresh `check()` by issue id.
- JS: remote text goes through `esc()` / `textContent`; error pages are parsed with `DOMParser` (inert), never `innerHTML`.

## Gotchas found while building

- **`enrich()` on a single item must still resolve dependencies.** The JSON install task enriches just the requested item, so a required parent isn't in the set; StateHelper looks it up directly. (Without that, an add-on was refused right after its parent installed in the same queue.)
- **Atum zeroes `.modal-body` padding** (built for iframe modals). Our modals carry `csem-modal` and the CSS adds it back.
- **Bootstrap ignores `hide()` while a modal is still fading in.** `busy()`/`unbusy()` in admin.js record the wanted state and settle it on `shown.bs.modal`; a stuck busy modal otherwise blocks the confirm modal's buttons.
- **Don't answer a "needs confirmation" with HTTP 409**: the browser logs it as a console error. Answer 200 with `data.needs_confirm`.
- **`BaseController` has no `getApplication()`** in Joomla 6. Use `$this->app`.
- **HTML entities in language strings break once escaped** (`&#8230;` shows literally). Strings that are escaped or go to JS use real Unicode (…, —, →); the files are UTF-8 without BOM.
- **Form field `type=` must match the class file name exactly on Linux** (`Catalogitems` → `CatalogitemsField.php`).
- **Fix all ordering:** re-linking an orphaned feed and then "deleting the orphan" in the same pass would delete the site just linked. `delete_update_site` re-checks the site is still orphaned.
- Installer scripts of the extensions we install echo HTML; `InstallHelper` wraps `Installer::install()` in an output buffer and `sendJson()` clears any buffers before answering.
- Joomla's own `#__updates` rows survive our installs; `forgetUpdates()` deletes them so System → Update doesn't keep offering what's installed.
- After an install: delete `administrator/cache/autoload_psr4.php` and `opcache_reset()`, or new namespaces may not resolve on the next request.

## Testing

Local throwaway Joomla 6.1.4 in WSL: `/home/root/csem-test/site`, http://localhost:8087, login in the vault note `04.knowledge/cs-extension-manager.md` (a clone of the Page Protector test site at :8086; its Page Protector plugin is disabled in the clone). Keep WSL alive with a background `sleep`. Install with `php cli/joomla.php extension:install --path=...` as www-data; run the task with `php cli/joomla.php scheduler:run --id=4`. The site has no sendmail, so task emails fail (logged) and go nowhere.

`php tests/run.php` and `python3 tools/check-language.py` run without Joomla.

## Build

```powershell
.\build-package.ps1
```

## Releasing

Same flow as the other Cybersalt packages (vault `04.knowledge/cybersalt-com/release-manager-publish-flow.md`), but the store needs a **package row for `pkg_csextensionmanager`** first (doesn't exist yet). Then the manager lists and updates itself.

## Versioning / languages

One version per unreleased cycle; the changelog has a single "Unreleased" entry for the version in progress. en-GB only until the first public release, then the other 16 Cybersalt languages.
