# Logo - cs-extension-manager

Two variants live in `packages/com_csextensionmanager/media/images/` and install to `media/com_csextensionmanager/images/`:

- **`logo.svg`** - duotone: cobalt `#0102E1` extension tiles, orange `#FE9904` download badge
- **`logo-mono.svg`** - all-cobalt fallback

Both are 1200×1200 intrinsic SVGs (Tabler 24-unit paths wrapped in `scale(50)`), per the cs-* extension-logo convention.

## What it is

Tabler Icons `layout-grid` (MIT licensed) with the top-right tile replaced by a round download badge: "your extensions, and what to install next". Same base-icon-plus-corner-badge construction as the rest of the family.

## Product icons

`media/images/products/` holds a copy of each Cybersalt product's logo (from the vault's `04.knowledge/cybersalt-com/branding/extension-logos/`), named after the product's element so the catalog can show it. `mcp-addon.svg` is used for every MCP for Joomla add-on and `default.svg` (Tabler `puzzle`) for anything without its own. When `api.packages` starts sending an `icon_url`, that wins.

## Source of truth

The canonical copy is in the vault at `04.knowledge/cybersalt-com/branding/extension-logos/cs-extension-manager.svg` (+ `-mono`). Update it there first, then copy here.

## Where it's used

- Post-install card (package `script.php`)
- Branded header on every Options tab (`BrandheaderField`)
- The manager's own card in its catalog
- README
