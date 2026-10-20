# Store API the Extension Manager uses

All on cs-release-manager at `https://www.cybersalt.com/index.php?option=com_csreleasemanager&task=api.<task>&format=raw|json`.

| Task | Used for | Notes |
|---|---|---|
| `api.packages` (json) | The whole catalog in one request | **New, on branch `feature/api-packages` of cs-release-manager (commit a1dd15f), not deployed yet.** Until it answers, the manager falls back to `seed.json` + `api.updatexml`. |
| `api.updatexml&element=X[&dlid=]` | Latest version, download URL, SHA-256, Joomla/PHP requirements; fetched fresh before every install | With a dlid the store says "UPDATE NOT AVAILABLE TO THIS SITE: reason" for Pro items the account doesn't cover. Cached 5 minutes by Cloudflare. |
| `api.changelogxml&element=X` | Changelog window and the version list for rollbacks | |
| `api.catalog&catalog=cs-mcp-for-j` (json) | MCP for Joomla add-ons: target extension, tier, advisory, superseded_by | |
| `api.userdownload&element=X&version=V` | Free downloads | |
| `api.download&element=X&version=V&dlid=` | Paid downloads | |
| `api.register` / `api.linkemail` (POST json) | "Activate this site" | Anchor package `csmcpforjpro`, same as MCP for Joomla |
| `api.verifyaccess&dlid=` (json) | Account status, entitled elements | Re-checked once a day, and on "Check for updates" |

## api.packages response

```json
{
  "schema_version": 1,
  "generated_at": "2026-10-20T09:00:00Z",
  "source": "cs-release-manager",
  "packages": [
    {
      "title": "Cybersalt Page Protector",
      "alias": "cybersalt-page-protector",
      "extension_type": "package",
      "extension_element": "pkg_cspageprotector",
      "extension_folder": "",
      "extension_client": 0,
      "is_public": 1,
      "catalog_slug": "",
      "version": "0.2.1",
      "release_date": "2026-10-20",
      "is_stable": 1,
      "min_joomla_version": "5.0",
      "max_joomla_version": "6",
      "min_php_version": "8.1",
      "targetplatform": "(5|6)\\.[0-9]+",
      "sha256": "2991…b780",
      "download_url": "https://www.cybersalt.com/index.php?option=com_csreleasemanager&format=raw&element=pkg_cspageprotector&task=api.userdownload&version=0.2.1",
      "update_xml_url": "…&task=api.updatexml",
      "changelog_url": "…&task=api.changelogxml",
      "display": {
        "short_description": "Puts a proof-of-work check in front of the pages and modules you choose…",
        "icon_url": "https://www.cybersalt.com/images/extensions/page-protector.svg",
        "docs_url": "https://docs.cybersalt.com/extensions/page-protector",
        "product_url": "https://www.cybersalt.com/extensions/page-protector",
        "repo_url": "https://github.com/cybersalt/cs-page-protector",
        "category": "security"
      }
    }
  ]
}
```

- Packages without a published stable version (e.g. the `csmcpforjpro` membership anchor) aren't listed.
- `display` comes from optional keys in the package's **Catalog metadata** JSON (Package edit form). Anything missing falls back to the manager's `seed.json`, then to the package title.
- Packages whose `catalog_slug` is `cs-mcp-for-j` are skipped by the manager; it takes add-ons from `api.catalog`, which knows their target extension.
- An `icon_url` must be https; anything else falls back to the bundled logo.

## To switch it on

1. Merge `feature/api-packages` into cs-release-manager (it only adds `ApiController::packages()` plus two private helpers; the 1.12.8 work in progress doesn't touch that file).
2. Release and install it on cybersalt.com.
3. Optionally fill in `display` keys in each package's catalog metadata.

No Extension Manager release is needed: it starts using the endpoint on its next catalog refresh.
