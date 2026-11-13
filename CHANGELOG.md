# Changelog

All notable changes to Cybersalt Extension Manager are documented here. Only released versions are listed, plus the version in progress. People whose ideas or reports shaped a change are credited on that entry.

## [0.1.0] - 2026-11-13

First public release.

### 🚀 New
- **Dashboard.** The manager opens on a dashboard: Extensions, Templates, Health Check and History tiles, what needs updating (with Update all), recent activity, your Cybersalt account, and a few templates that change each day. A section bar at the top of every screen gets you anywhere in one click, and the same entries are in Joomla's side menu.
- **Every Cybersalt extension in one place.** The Extensions screen lists every Cybersalt extension and MCP for J add-on with what's installed here, the latest version, its release date and whether it's free or Pro. One row per extension, sortable by name, status, installed version or release date, with search, filters (status, kind, licence, "only add-ons for extensions on this site") and clickable counts. Click a row to open its details: the full description, the package's parts with on/off switches, and notes.
- **One-click install, update, repair and reinstall**, one at a time or in bulk. Tick rows (or every row the filters show) and use **Install / update selected**, **Reinstall selected** or **Uninstall selected**; each button counts only the rows it applies to. A progress window shows each item live, gives the reason when one fails, and carries on with the rest.
- **Dependencies handled.** Installing an MCP add-on installs CS MCP for J first when it isn't there, add-ons are uninstalled before what they depend on, and uninstalling MCP for J warns which add-ons would stop working.
- **Downloads are checked before installing.** Only https downloads from the Cybersalt store are accepted, the latest release is checked against the SHA-256 checksum in the update feed, and the zip has to contain the extension that was asked for.
- **What's new since your version.** The changelog window highlights every release newer than the one installed, and can install any earlier release to roll back.
- **Compatibility checked first.** Updates this site's Joomla or PHP version can't run are flagged and not offered.
- **Switch parts on and off** from an extension's details. The result shows right beside the switch: no page reload, no jump when you're scrolled down.
- **Health Check with one-click fixes**: missing, switched-off, duplicate, wrong or orphaned update sites; a missing download ID on Pro update sites; half-installed packages; package parts installed without their package (so they never get updates); disabled parts; add-ons without MCP for J or without the extension they're for (with an Uninstall button); updates Joomla still lists after they were installed; a lapsed membership; and the store not answering. "Fix all" repairs the update-site bookkeeping in one go. The Health Check tile is green with nothing to look at, yellow for warnings or notes, and red for real problems.
- **Hide a "Good to know" note** you don't need, such as a part you switched off on purpose. Hidden notes leave the counts and Fix all, and can be shown again from the bottom of the Health Check.
- **Templates.** A Templates screen shows the Joomla templates from Energize Themes and VirtueMart Templates: a thumbnail, who each one is for, the Joomla versions and shop extension it supports, a live demo and its details page. Filter by type (the same template types the two sites use), template site, Joomla version and shop, search, and sort. The list comes from cybersalt.com and is rebuilt every night from both sites, so a new template appears without an Extension Manager update; a copy ships with the extension in case cybersalt.com can't be reached. Nothing is installed from here. (Idea from Bjørn Ove Bremnes.)
- **Pro extensions**: activate the site with your cybersalt.com account email, paste a download ID in Options, or reuse MCP for J's existing Pro activation automatically. The download ID is written onto every Cybersalt update site, so Joomla's own System → Update screen can download Pro updates too, and an installer plugin adds it back if update sites are rebuilt.
- **Home Dashboard quick icon** with the number of Cybersalt updates, fetched after the dashboard loads so it never slows it down.
- **Release emails, opt-in.** "Email me about new releases" switches on a daily scheduled check that emails you when something new is out (off until you ask). The same task can also install updates automatically, limited to patch releases, patch and minor releases, or all. Extensions can be excluded from automatic updates; the Extension Manager never updates itself from a task.
- **History**: every install, update, rollback, uninstall, enable/disable, health fix, update check and email, with filters for every column, CSV download, a text dump for support, delete and clear all. Installs, updates and uninstalls also go to Joomla's User Actions Log.
- Options: download ID, MCP for J activation reuse, how often to check, which MCP add-ons to show, beta releases, timeout, email recipients, auto-update exclusions, history retention, support contact details and permissions (view / install / fix).
- **17 languages**: English, Czech, Dutch, French, German, Greek, Italian, Japanese, Norwegian (Bokmål and Nynorsk), Polish, Portuguese (Brazil), Russian, Simplified Chinese, Spanish, Swedish and Turkish.

### 🔍 Security
- Packages are only installed from cybersalt.com, over https, and nothing is installed when the latest release can't be checked with the store first.
- The store address can't be changed from Options, so permission to change Options never becomes permission to choose what gets installed.
- Every action checks the form token and a permission (view / install / fix); installing also needs access to Joomla's own Extensions Manager.
