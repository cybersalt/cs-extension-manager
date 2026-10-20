# Changelog

All notable changes to Cybersalt Extension Manager are documented here. Only released versions are listed, plus the version in progress. People whose ideas or reports shaped a change are credited on that entry.

## [0.1.0] - Unreleased

### 🚀 New
- **Every Cybersalt extension in one place.** The Extensions screen lists every Cybersalt extension and MCP for Joomla add-on with what's installed here, the latest version, its release date and whether it's free or Pro. Sections for "needs attention", "installed", "more Cybersalt extensions" and "MCP add-ons", with search, filters (status, kind, licence, "only add-ons for extensions on this site") and clickable stat cards. Filters are remembered per browser.
- **One-click install, update, repair and reinstall**, one at a time or in bulk ("Update all", or tick several with shift-click ranges). A lock-out window shows each item's progress live and the reason when one fails, carries on with the rest, and can't be closed halfway.
- **Dependencies handled.** Installing an MCP add-on installs MCP for Joomla first when it isn't there, and uninstalling MCP for Joomla warns which add-ons would stop working.
- **Downloads are checked before installing.** Only https downloads from the Cybersalt store are accepted, the latest release is checked against the SHA-256 checksum in the update feed, and the zip has to contain the extension that was asked for.
- **What's new since your version.** The changelog window highlights every release newer than the one installed.
- **Roll back to any earlier release** from the changelog window ("Install another version").
- **Compatibility checked first.** Updates this site's Joomla or PHP version can't run are flagged and not offered.
- **Health check with one-click fixes**: missing, switched-off, duplicate, wrong or orphaned update sites; a missing download ID on Pro update sites; half-installed packages; package parts installed without their package (so they never get updates); disabled parts; add-ons without MCP for Joomla or without the extension they're for; updates Joomla still lists after they were installed; a lapsed membership; and the store not answering. "Fix all" repairs the update-site bookkeeping in one go.
- **Pro extensions**: activate the site with a cybersalt.com account email, paste a download ID in Options, or reuse MCP for Joomla's existing Pro activation automatically. The download ID is written onto every Cybersalt update site, so Joomla's own System → Update screen can download Pro updates too, and an installer plugin adds it back if update sites are rebuilt.
- **Home Dashboard quick icon** with the number of Cybersalt updates, fetched after the dashboard loads so it never slows it down.
- **Scheduled update checks** (Joomla Scheduler task, created on install): check daily and email a summary when something new is available, or install updates automatically, limited to patch releases, patch and minor releases, or all. Extensions can be excluded from automatic updates; the Extension Manager never updates itself from a task.
- **History**: every install, update, rollback, uninstall, enable/disable, health fix, update check and email, with the standard Cybersalt log viewer (filters for every column, CSV download, text dump for support, delete and clear all). Installs, updates and uninstalls also go to Joomla's User Actions Log.
- Enable or disable each part of an installed package from its card.
- Options: download ID, MCP for Joomla activation reuse, how often to check, which MCP add-ons to show, beta releases, timeout, store address, email recipients, auto-update exclusions, history retention, support contact details and permissions (view / install / fix).
