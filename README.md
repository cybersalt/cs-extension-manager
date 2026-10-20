<img src="packages/com_csextensionmanager/media/images/logo.svg" width="96" alt="Cybersalt Extension Manager logo">

# Cybersalt Extension Manager

Install, update and look after every Cybersalt Joomla extension from one screen. Free.

**[Product page](https://www.cybersalt.com/extensions)** · **[Support](https://www.cybersalt.com/services/support-request-form)** · **[Report a bug](https://github.com/cybersalt/cs-extension-manager/issues)**

The Extensions screen lists every Cybersalt extension and MCP for Joomla add-on: what's installed on this site, what has an update, what each one does, and whether it's free or Pro. Install or update with one click, or tick several and do them in one go. It checks every download before installing it, shows what's new since your version, rolls back to an earlier release if an update misbehaves, and its health check finds the problems that stop updates from arriving and fixes them.

## What it does

- **Every Cybersalt extension in one list**, grouped into "needs attention", "installed", "more Cybersalt extensions" and "MCP for Joomla add-ons". Search, filter by status, kind and licence, or show only the add-ons for extensions already on the site. The stat cards at the top are filters too.
- **One-click install, update, repair and reinstall**, one at a time, ticked in bulk (shift-click for a range) or "Update all". A progress window shows each item as it goes, keeps going past a failure, says why something failed, and can't be closed halfway through.
- **Dependencies sorted out.** Installing an MCP add-on installs MCP for Joomla first if it isn't there. Uninstalling MCP for Joomla tells you which add-ons would stop working before it does anything.
- **Checked downloads.** Packages only come from the Cybersalt store over https, the latest release is checked against the SHA-256 checksum the store publishes, and the zip has to contain the extension you asked for.
- **What's new since your version.** The changelog window marks every release newer than the one installed.
- **Roll back** to any earlier release from the same window.
- **Compatibility first.** An update that needs a newer Joomla or PHP than the site has is shown as such and not offered.
- **Health check with one-click fixes.** Finds missing, switched-off, duplicate, wrong and orphaned update sites, a missing download ID on Pro update sites, half-installed packages, package parts left without their package (they never get updates), switched-off parts, add-ons without MCP for Joomla or without the extension they're for, updates Joomla still lists after you installed them, a lapsed membership, and the store not answering. "Fix all" repairs the update-site bookkeeping in one go; installs and switching things on are left for you to choose.
- **Pro extensions without fuss.** Activate the site with your cybersalt.com account email, paste a download ID, or let it reuse MCP for Joomla's existing Pro activation. The download ID goes onto every Cybersalt update site, so Joomla's own **System → Update → Extensions** screen can download Pro updates too, and a small installer plugin puts it back if the update sites get rebuilt.
- **Home Dashboard count.** A quick icon next to Joomla's own update checks shows how many Cybersalt updates are waiting. It loads after the dashboard, so it never slows it down.
- **Release emails, if you want them.** Click **Email me about new releases** (on the install screen or the Extensions screen) and a daily scheduled task emails you when a new release of a Cybersalt extension on the site is out. It's off until you ask for it. The same task can also install updates for you: patch releases only, patch and minor, or everything. You can exclude extensions, and the Extension Manager never updates itself from a task.
- **History.** Every install, update, rollback, uninstall, switch on/off, health fix, update check and email, with who did it and how long it took. Filters for every column, CSV download, a plain-text dump for support tickets. Installs, updates and uninstalls also go into Joomla's User Actions Log.
- **Permissions.** View, install/update and fix are separate permissions. Installing also needs access to Joomla's own Extensions Manager, so a site that locked that down isn't bypassed.

### How it compares

| | Cybersalt Extension Manager | Regular Labs Extension Manager | Extensions Manager CK |
|---|---|---|---|
| Live catalog (new products appear without updating the manager) | ✔ | ✔ | ✘ (list ships inside the manager) |
| Bulk install / Update all | ✔ | ✔ | ✘ |
| Checksum checked before install | ✔ SHA-256 | ✔ | ✘ |
| Joomla / PHP compatibility check | ✔ both | ✘ | Joomla major only |
| What's new since your version | ✔ | ✘ (full changelog) | ✔ |
| Roll back to an earlier release | ✔ | ✘ | ✘ |
| Dependencies installed first | ✔ | ✘ | ✘ |
| Health check with fixes | ✔ | "broken" state only | ✘ |
| Download ID survives "Rebuild update sites" | ✔ | ✘ | ✔ |
| Search and filters | ✔ | ✘ | ✘ |
| Dashboard update count | ✔ | ✘ | ✘ |
| Scheduled checks, email, auto-update | ✔ | ✘ | ✘ |
| History + User Actions Log | ✔ | User Actions Log | ✘ |

## Requirements

- Joomla 5.x or 6.x, PHP 8.1 or later (Joomla 6 needs PHP 8.3).
- The site must be able to reach `https://www.cybersalt.com`.

## Installation

Install `pkg_csextensionmanager_v{version}.zip` through **System → Install → Extensions**. Then open **Components → Cybersalt Extension Manager**.

The package installs:

| Part | What it's for |
|---|---|
| `com_csextensionmanager` | The Extensions, Health Check and History screens |
| `plg_installer_csextensionmanager` | Adds the download ID to Cybersalt downloads and update checks when it's missing |
| `plg_quickicon_csextensionmanager` | The Home Dashboard update count |
| `plg_task_csextensionmanager` | The scheduled update check (created switched off; "Email me about new releases" turns it on) |

## Configuration

**Components → Cybersalt Extension Manager → Options**

| Tab | Setting | Default |
|---|---|---|
| Account | Download ID (overrides everything else) | empty |
| Account | Use MCP for Joomla's activation | Yes |
| Account | Keep update sites in step (write the ID onto Cybersalt update sites) | Yes |
| Catalog | Check for updates every (hours) | 6 |
| Catalog | Show MCP for Joomla add-ons: all / only for extensions on this site / none | All |
| Catalog | Offer beta releases | No |
| Catalog | Connection timeout | 10 s |
| Updates | Email summaries to | whoever clicked "Email me about new releases" (else Super Users with system emails) |
| Updates | Never auto-update | none |
| History | Keep history for (days) | 365 |
| History | Also write to User Actions Log | Yes |
| Support | Support name, email, page | Cybersalt |

What the scheduled task does (check only, or install patch / minor / all updates) and how often it runs is set on the task itself under **System → Scheduled Tasks**.

## Where the list comes from

The manager talks to the Cybersalt Release Manager on cybersalt.com:

1. `api.packages` (when the store has it): every published package and its latest version in one request.
2. Otherwise the bundled list in `admin/catalog/seed.json` plus one `api.updatexml` request per product (all at once), which carries the version, download, SHA-256 and Joomla/PHP requirements.
3. MCP for Joomla add-ons from `api.catalog&catalog=cs-mcp-for-j`.

The result is cached for the configured number of hours. When a request fails, the last good details for that item are kept and the screen says which checks failed.

## Development

```
packages/
├── com_csextensionmanager/          Component (admin only)
│   ├── admin/catalog/seed.json      Products known without api.packages
│   ├── admin/src/Helper/            All the logic (see CLAUDE.md)
│   ├── admin/tmpl/                  extensions / health / logs views
│   └── media/                       admin.css, admin.js, logos
├── plg_installer_csextensionmanager/
├── plg_quickicon_csextensionmanager/
├── plg_task_csextensionmanager/
└── pkg_csextensionmanager/          Package manifest, script.php (enables plugins, seeds ACL, creates the task, install card)
tests/                               php tests/run.php (feed parsing, platform matching, update size)
tools/check-language.py              Fails when code uses a language key no .ini defines
```

The [Joomla-Brain](https://github.com/cybersalt/Joomla-Brain) conventions apply (`.joomla-brain` submodule).

## Building

```powershell
.\build-package.ps1
```

Checks for BOMs, version agreement across the five manifests, empty folders and valid JSON, then builds `pkg_csextensionmanager_v{version}_{timestamp}.zip` with 7-Zip and prints its SHA-256. CI runs `php -l` on PHP 8.1–8.4, phpcs (PSR-12 with Joomla's exclusions), xmllint, the language-key check and the unit tests.

## License

GNU General Public License version 2 or later. See [LICENSE](LICENSE).

## Author

[Cybersalt Consulting Ltd.](https://cybersalt.com) · support@cybersalt.com
