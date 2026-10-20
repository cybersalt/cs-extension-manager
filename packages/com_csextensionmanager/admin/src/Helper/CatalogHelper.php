<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * The list of Cybersalt extensions and their latest versions.
 *
 * Built from, in order:
 *
 * 1. `api.packages` on the store, when it has that endpoint (every
 *    published package with its latest version in one request);
 * 2. otherwise the bundled `admin/catalog/seed.json` plus one
 *    `api.updatexml` request per product (all fetched in parallel);
 * 3. plus the MCP for Joomla add-ons from `api.catalog&catalog=cs-mcp-for-j`.
 *
 * The result is cached in `#__csextensionmanager_cache`. When a request
 * fails, the last good data for that item is kept, so one flaky request
 * never makes an extension look uninstallable.
 *
 * @since  0.1.0
 */
final class CatalogHelper
{
    public const GROUP_PRODUCT = 'product';
    public const GROUP_ADDON   = 'addon';

    /**
     * The add-ons' parent package.
     *
     * @var    string
     * @since  0.1.0
     */
    public const ADDON_PARENT_ELEMENT = 'pkg_csmcpforj';

    /**
     * The store catalog slug that lists the MCP add-ons.
     *
     * @var    string
     * @since  0.1.0
     */
    private const ADDON_CATALOG = 'cs-mcp-for-j';

    /**
     * Cache row key. Bump the suffix when the item shape changes.
     *
     * @var    string
     * @since  0.1.0
     */
    private const CACHE_KEY = 'catalog.v1';

    /**
     * @var    array<string, mixed>|null
     * @since  0.1.0
     */
    private static ?array $memo = null;

    /**
     * Stable key for an extension: `type:folder:element`.
     *
     * @param   string  $type     Extension type.
     * @param   string  $folder   Plugin group ('' otherwise).
     * @param   string  $element  Element.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function key(string $type, string $folder, string $element): string
    {
        return strtolower($type) . ':' . ($type === 'plugin' ? strtolower($folder) : '') . ':' . strtolower($element);
    }

    /**
     * The catalog, refreshed from the store when the cache is older than the
     * configured lifetime (or when $refresh is true).
     *
     * @param   boolean  $refresh  Ignore the cache.
     *
     * @return  array{items: array<string, array<string, mixed>>, fetched: int, source: string, errors: string[]}
     *
     * @since   0.1.0
     */
    public static function get(bool $refresh = false): array
    {
        if (!$refresh && self::$memo !== null) {
            return self::$memo;
        }

        $cached = self::getCached();
        $hours  = max(1, min(168, (int) ParamsHelper::get()->get('cache_hours', 6)));

        if (!$refresh && $cached !== null && time() - $cached['fetched'] < $hours * 3600) {
            return self::$memo = $cached;
        }

        $start  = microtime(true);
        $built  = self::build($cached);
        $failed = $built['items'] === [] && $cached !== null;

        if ($failed) {
            // Nothing came back at all: keep showing what we had.
            $cached['errors'] = $built['errors'];
            $cached['source'] = 'cache';
            self::$memo       = $cached;
        } else {
            self::store($built);
            self::$memo = $built;
        }

        if ($refresh || $built['errors'] !== []) {
            LogHelper::log(
                LogHelper::ACTION_REFRESH,
                $built['errors'] === [] ? LogHelper::STATUS_SUCCESS : ($failed ? LogHelper::STATUS_ERROR : LogHelper::STATUS_WARNING),
                '',
                '',
                '',
                '',
                \sprintf('%d extensions from %s', \count($built['items']), $built['source']),
                implode("\n", $built['errors']),
                (int) round((microtime(true) - $start) * 1000)
            );
        }

        return self::$memo;
    }

    /**
     * The cached catalog without touching the network (quickicon, health).
     *
     * @return  array{items: array<string, array<string, mixed>>, fetched: int, source: string, errors: string[]}|null
     *
     * @since   0.1.0
     */
    public static function getCached(): ?array
    {
        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $row = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName(['fetched', 'data']))
                    ->from($db->quoteName('#__csextensionmanager_cache'))
                    ->where($db->quoteName('cache_key') . ' = ' . $db->quote(self::CACHE_KEY))
            )->loadObject();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $data = json_decode((string) $row->data, true);

        if (!\is_array($data) || !isset($data['items']) || !\is_array($data['items'])) {
            return null;
        }

        return [
            'items'   => $data['items'],
            'fetched' => (int) (strtotime((string) $row->fetched . ' UTC') ?: 0),
            'source'  => (string) ($data['source'] ?? 'cache'),
            'errors'  => array_values((array) ($data['errors'] ?? [])),
        ];
    }

    /**
     * One catalog item by key, from the cached catalog (fetched if missing).
     *
     * @param   string  $key  Item key.
     *
     * @return  array<string, mixed>|null
     *
     * @since   0.1.0
     */
    public static function find(string $key): ?array
    {
        $catalog = self::get();

        return $catalog['items'][$key] ?? null;
    }

    /**
     * Forget the cache (next get() goes to the store).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function clear(): void
    {
        self::$memo = null;

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__csextensionmanager_cache'))
                    ->where($db->quoteName('cache_key') . ' = ' . $db->quote(self::CACHE_KEY))
            )->execute();
        } catch (\Throwable $e) {
            // Nothing cached.
        }
    }

    /**
     * Fetch everything from the store.
     *
     * @param   array<string, mixed>|null  $previous  Last cached catalog, for per-item fallback.
     *
     * @return  array{items: array<string, array<string, mixed>>, fetched: int, source: string, errors: string[]}
     *
     * @since   0.1.0
     */
    private static function build(?array $previous): array
    {
        $errors   = [];
        $seed     = self::seed();
        $products = self::fromPackagesEndpoint($seed, $errors);
        $source   = 'packages';

        if ($products === null) {
            $products = self::fromSeed($seed, $previous, $errors);
            $source   = 'feeds';
        }

        $items = $products;

        if (ParamsHelper::get()->get('show_addons', 'all') !== 'none') {
            foreach (self::fromAddonCatalog($previous, $errors) as $key => $addon) {
                // A package listed by api.packages and api.catalog: the add-on entry has more detail.
                $items[$key] = isset($items[$key]) ? array_merge($items[$key], $addon) : $addon;
            }
        }

        return [
            'items'   => $items,
            'fetched' => time(),
            'source'  => $source,
            'errors'  => $errors,
        ];
    }

    /**
     * The whole store in one request (cs-release-manager 1.12.9+).
     *
     * @param   array<string, array<string, mixed>>  $seed    Seed items by element.
     * @param   string[]                             $errors  Collected errors.
     *
     * @return  array<string, array<string, mixed>>|null  Null when the store has no such endpoint.
     *
     * @since   0.1.0
     */
    private static function fromPackagesEndpoint(array $seed, array &$errors): ?array
    {
        $result = StoreClient::getJson(StoreClient::apiUrl('packages', [], 'json'));
        $json   = $result['json'];

        if (!\is_array($json) || !isset($json['packages']) || !\is_array($json['packages'])) {
            // 404 / old store: not an error, just use the feeds.
            return null;
        }

        $items = [];

        foreach ($json['packages'] as $pkg) {
            if (!\is_array($pkg) || empty($pkg['extension_element']) || empty($pkg['extension_type'])) {
                continue;
            }

            // Add-ons come from api.catalog, which knows their target extension.
            if ((string) ($pkg['catalog_slug'] ?? '') === self::ADDON_CATALOG) {
                continue;
            }

            $element = (string) $pkg['extension_element'];
            $display = \is_array($pkg['display'] ?? null) ? $pkg['display'] : [];
            $base    = $seed[$element] ?? [];

            $item = self::blank(
                (string) $pkg['extension_type'],
                (string) ($pkg['extension_folder'] ?? ''),
                $element,
                self::GROUP_PRODUCT
            );

            $item = array_merge($item, [
                'client'         => (int) ($pkg['extension_client'] ?? ($base['client'] ?? 0)),
                'title'          => (string) ($pkg['title'] ?? ($base['title'] ?? $element)),
                'description'    => (string) ($display['short_description'] ?? ($base['description'] ?? ($pkg['description'] ?? ''))),
                'category'       => (string) ($display['category'] ?? ($base['category'] ?? '')),
                'icon'           => self::iconUrl((string) ($display['icon_url'] ?? ''), (string) ($base['icon'] ?? '')),
                'product_url'    => self::httpUrl((string) ($display['product_url'] ?? ($base['product_url'] ?? ''))),
                'docs_url'       => self::httpUrl((string) ($display['docs_url'] ?? ($base['docs_url'] ?? ''))),
                'repo_url'       => self::httpUrl((string) ($display['repo_url'] ?? ($base['repo_url'] ?? ''))),
                'tier'           => (int) ($pkg['is_public'] ?? 1) === 1 ? 'free' : 'pro',
                'self'           => !empty($base['self']) || $element === 'pkg_csextensionmanager',
                'available'      => !empty($pkg['version']),
                'latest_version' => (string) ($pkg['version'] ?? ''),
                'released'       => substr((string) ($pkg['release_date'] ?? ''), 0, 10),
                'download_url'   => (string) ($pkg['download_url'] ?? ''),
                'sha256'         => strtolower((string) ($pkg['sha256'] ?? '')),
                'targetplatform' => (string) ($pkg['targetplatform'] ?? ''),
                'php_minimum'    => (string) ($pkg['min_php_version'] ?? ''),
                'stability'      => (int) ($pkg['is_stable'] ?? 1) === 1 ? 'stable' : 'beta',
            ]);

            $items[$item['key']] = $item;
        }

        // Anything in the seed the store didn't list (e.g. not published yet).
        foreach ($seed as $element => $base) {
            $key = self::key((string) $base['type'], (string) $base['folder'], $element);

            if (!isset($items[$key])) {
                $items[$key] = self::seedItem($base);
            }
        }

        return $items;
    }

    /**
     * Seed list + one update feed per product.
     *
     * @param   array<string, array<string, mixed>>  $seed      Seed items by element.
     * @param   array<string, mixed>|null            $previous  Last cached catalog.
     * @param   string[]                             $errors    Collected errors.
     *
     * @return  array<string, array<string, mixed>>
     *
     * @since   0.1.0
     */
    private static function fromSeed(array $seed, ?array $previous, array &$errors): array
    {
        $dlid  = AccountHelper::getDlid();
        $urls  = [];
        $items = [];

        foreach ($seed as $element => $base) {
            $item               = self::seedItem($base);
            $items[$item['key']] = $item;
            $url                = StoreClient::updateXmlUrl($element);

            // With an ID the store can say "not available to this site" for Pro items.
            if ($dlid !== '') {
                $url = StoreClient::withQueryParam($url, 'dlid', $dlid);
            }

            $urls[$item['key']] = $url;
        }

        foreach (StoreClient::getMany($urls) as $key => $response) {
            $parsed = $response['code'] === 200 ? FeedParser::parseUpdate($response['body']) : null;

            if ($parsed === null) {
                $errors[] = $items[$key]['title'] . ': ' . ($response['error'] ?? 'not update XML');

                // Keep what we knew.
                if (isset($previous['items'][$key])) {
                    $items[$key] = array_merge($items[$key], self::feedFields($previous['items'][$key]));
                }

                continue;
            }

            $items[$key] = self::applyFeed($items[$key], $parsed);
        }

        return $items;
    }

    /**
     * MCP for Joomla add-ons.
     *
     * @param   array<string, mixed>|null  $previous  Last cached catalog.
     * @param   string[]                   $errors    Collected errors.
     *
     * @return  array<string, array<string, mixed>>
     *
     * @since   0.1.0
     */
    private static function fromAddonCatalog(?array $previous, array &$errors): array
    {
        $result = StoreClient::getJson(StoreClient::apiUrl('catalog', ['catalog' => self::ADDON_CATALOG], 'json'));
        $json   = $result['json'];

        if (!\is_array($json) || !isset($json['addons']) || !\is_array($json['addons'])) {
            $errors[] = 'MCP add-on catalog: ' . ($result['error'] ?? 'unexpected response');

            // Keep the add-ons we knew about.
            return array_filter(
                (array) ($previous['items'] ?? []),
                static fn ($item): bool => \is_array($item) && ($item['group'] ?? '') === self::GROUP_ADDON
            );
        }

        $parentKey = self::key('package', '', self::ADDON_PARENT_ELEMENT);
        $items     = [];

        foreach ($json['addons'] as $addon) {
            $ext = \is_array($addon['addon_extension'] ?? null) ? $addon['addon_extension'] : null;

            if ($ext === null || empty($ext['type']) || empty($ext['element'])) {
                continue;
            }

            $item = self::blank((string) $ext['type'], (string) ($ext['folder'] ?? ''), (string) $ext['element'], self::GROUP_ADDON);
            $tier = !empty($addon['requires_pro_membership']) || ($addon['tier'] ?? '') === 'pro' ? 'pro' : 'free';

            $target = null;

            if (\is_array($addon['target_extension'] ?? null) && !empty($addon['target_extension']['element'])) {
                $t      = $addon['target_extension'];
                $target = [
                    'type'         => (string) ($t['type'] ?? ''),
                    'folder'       => (string) ($t['folder'] ?? ''),
                    'element'      => (string) $t['element'],
                    'display_name' => (string) ($t['display_name'] ?? $t['element']),
                    'vendor_name'  => (string) ($t['vendor_name'] ?? ''),
                    'vendor_url'   => self::httpUrl((string) ($t['vendor_url'] ?? '')),
                ];
            }

            $advisory = null;

            if (\is_array($addon['advisory'] ?? null) && !empty($addon['advisory']['message'])) {
                $severity = (string) ($addon['advisory']['severity'] ?? 'info');
                $advisory = [
                    'title'    => (string) ($addon['advisory']['title'] ?? ''),
                    // Plain text only: the catalog is remote data.
                    'message'  => trim(html_entity_decode(strip_tags((string) $addon['advisory']['message']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    'severity' => \in_array($severity, ['info', 'warning', 'danger'], true) ? $severity : 'info',
                ];
            }

            $supersededBy = [];

            foreach ((array) ($addon['superseded_by'] ?? []) as $s) {
                if (\is_array($s) && !empty($s['type']) && !empty($s['element'])) {
                    $supersededBy[] = [
                        'type'         => (string) $s['type'],
                        'folder'       => (string) ($s['folder'] ?? ''),
                        'element'      => (string) $s['element'],
                        'display_name' => (string) ($s['display_name'] ?? $s['element']),
                    ];
                }
            }

            $title = (string) ($addon['name'] ?? $ext['element']);

            $item = array_merge($item, [
                'title'          => $title,
                'description'    => (string) ($addon['short_description'] ?? ''),
                'category'       => 'ai',
                'icon'           => self::iconUrl('', 'mcp-addon.svg'),
                'product_url'    => self::addonPageUrl((string) ($addon['key'] ?? ''), $title),
                'docs_url'       => 'https://docs.cybersalt.com/extensions/mcp-for-j',
                'tier'           => $tier,
                'available'      => (bool) ($addon['available'] ?? true) && (string) ($addon['version'] ?? '') !== '',
                'latest_version' => (string) ($addon['version'] ?? ''),
                'released'       => substr((string) ($addon['released'] ?? ''), 0, 10),
                'download_url'   => (string) ($addon['download_url'] ?? ''),
                'requires'       => [$parentKey],
                'target'         => $target,
                'advisory'       => $advisory,
                'superseded_by'  => $supersededBy,
                'is_new'         => !empty($addon['is_new']),
                'is_experimental' => !empty($addon['is_experimental']),
                'notes'          => trim(strip_tags((string) ($addon['notes'] ?? ''))),
            ]);

            $items[$item['key']] = $item;
        }

        return $items;
    }

    /**
     * Fill an item from a parsed update feed.
     *
     * @param   array<string, mixed>  $item    Catalog item.
     * @param   array<string, mixed>  $parsed  FeedParser::parseUpdate() result.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    public static function applyFeed(array $item, array $parsed): array
    {
        if (empty($parsed['available'])) {
            $item['available'] = false;

            return $item;
        }

        return array_merge($item, [
            'available'      => true,
            'latest_version' => (string) $parsed['version'],
            'download_url'   => (string) $parsed['download_url'],
            'sha256'         => (string) $parsed['sha256'],
            'targetplatform' => (string) $parsed['targetplatform'],
            'php_minimum'    => (string) $parsed['php_minimum'],
            'stability'      => (string) $parsed['stability'],
            'denied'         => (string) $parsed['denied'],
            'info_url'       => self::httpUrl((string) $parsed['info_url']),
            // A paid download URL means the store treats it as Pro.
            'tier'           => str_contains((string) $parsed['download_url'], 'task=api.download') ? 'pro' : ($item['tier'] ?? 'free'),
        ]);
    }

    /**
     * The seed list, keyed by element.
     *
     * @return  array<string, array<string, mixed>>
     *
     * @since   0.1.0
     */
    private static function seed(): array
    {
        $file = JPATH_ADMINISTRATOR . '/components/com_csextensionmanager/catalog/seed.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $seed = [];

        foreach ((array) ($data['items'] ?? []) as $item) {
            if (\is_array($item) && !empty($item['element']) && !empty($item['type'])) {
                $item['folder']             = (string) ($item['folder'] ?? '');
                $seed[(string) $item['element']] = $item;
            }
        }

        return $seed;
    }

    /**
     * A catalog item from a seed entry, before any feed data.
     *
     * @param   array<string, mixed>  $base  Seed entry.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    private static function seedItem(array $base): array
    {
        $item = self::blank((string) $base['type'], (string) $base['folder'], (string) $base['element'], self::GROUP_PRODUCT);

        return array_merge($item, [
            'client'      => (int) ($base['client'] ?? 0),
            'title'       => (string) ($base['title'] ?? $base['element']),
            'description' => (string) ($base['description'] ?? ''),
            'category'    => (string) ($base['category'] ?? ''),
            'icon'        => self::iconUrl('', (string) ($base['icon'] ?? '')),
            'product_url' => self::httpUrl((string) ($base['product_url'] ?? '')),
            'docs_url'    => self::httpUrl((string) ($base['docs_url'] ?? '')),
            'repo_url'    => self::httpUrl((string) ($base['repo_url'] ?? '')),
            'self'        => !empty($base['self']),
        ]);
    }

    /**
     * Every field an item has, with safe defaults.
     *
     * @param   string  $type     Extension type.
     * @param   string  $folder   Plugin group.
     * @param   string  $element  Element.
     * @param   string  $group    GROUP_* constant.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    private static function blank(string $type, string $folder, string $element, string $group): array
    {
        $folder = $type === 'plugin' ? $folder : '';

        return [
            'key'             => self::key($type, $folder, $element),
            'type'            => $type,
            'folder'          => $folder,
            'element'         => $element,
            'client'          => 0,
            'group'           => $group,
            'title'           => $element,
            'description'     => '',
            'category'        => '',
            'icon'            => '',
            'product_url'     => '',
            'docs_url'        => '',
            'repo_url'        => '',
            'tier'            => 'free',
            'self'            => false,
            'available'       => false,
            'latest_version'  => '',
            'released'        => '',
            'download_url'    => '',
            'sha256'          => '',
            'targetplatform'  => '',
            'php_minimum'     => '',
            'stability'       => 'stable',
            'denied'          => '',
            'info_url'        => '',
            'changelog_url'   => StoreClient::changelogUrl($element),
            'requires'        => [],
            'target'          => null,
            'advisory'        => null,
            'superseded_by'   => [],
            'is_new'          => false,
            'is_experimental' => false,
            'notes'           => '',
        ];
    }

    /**
     * The fields that come from the update feed (used to carry them over
     * from the cache when a request fails).
     *
     * @param   array<string, mixed>  $item  Cached item.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    private static function feedFields(array $item): array
    {
        return array_intersect_key($item, array_flip([
            'available', 'latest_version', 'download_url', 'sha256', 'targetplatform',
            'php_minimum', 'stability', 'denied', 'info_url', 'tier',
        ]));
    }

    /**
     * Save to the cache table.
     *
     * @param   array<string, mixed>  $catalog  Built catalog.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function store(array $catalog): void
    {
        try {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $key     = self::CACHE_KEY;
            $fetched = Factory::getDate($catalog['fetched'])->toSql();
            $data    = (string) json_encode(
                ['items' => $catalog['items'], 'source' => $catalog['source'], 'errors' => $catalog['errors']],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__csextensionmanager_cache'))
                    ->where($db->quoteName('cache_key') . ' = :key')
                    ->bind(':key', $key)
            )->execute();

            $db->setQuery(
                $db->createQuery()
                    ->insert($db->quoteName('#__csextensionmanager_cache'))
                    ->columns($db->quoteName(['cache_key', 'fetched', 'data']))
                    ->values(':key, :fetched, :data')
                    ->bind(':key', $key)
                    ->bind(':fetched', $fetched)
                    ->bind(':data', $data)
            )->execute();
        } catch (\Throwable $e) {
            // Uncached just means the next page load asks the store again.
        }
    }

    /**
     * The add-on's page on cybersalt.com (under /extensions/mcp-for-j/).
     *
     * @param   string  $key    Catalog key, e.g. "4seo".
     * @param   string  $title  "MCP for 4SEO".
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function addonPageUrl(string $key, string $title): string
    {
        // Pages whose alias isn't the title minus "MCP for ".
        $exceptions = [
            'convertformsfree' => 'convert-forms',
            'releasemanager'   => 'release-manager',
        ];

        $slug = $exceptions[$key] ?? self::slug((string) preg_replace('/^MCP for\s+/i', '', $title));

        return 'https://www.cybersalt.com/extensions/mcp-for-j' . ($slug !== '' ? '/' . $slug : '');
    }

    /**
     * An icon: a remote https URL, or a bundled file's path relative to the
     * site root (resolved by iconSrc() at render time, so the cache doesn't
     * carry this site's base path).
     *
     * @param   string  $remote  Remote icon URL.
     * @param   string  $local   Bundled file name in media/com_csextensionmanager/images/products.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function iconUrl(string $remote, string $local): string
    {
        if (preg_match('#^https://#i', $remote)) {
            return $remote;
        }

        $local = basename($local);

        if ($local === '' || !preg_match('/^[A-Za-z0-9._-]+\.(svg|png|webp)$/', $local)) {
            $local = 'default.svg';
        }

        return 'media/com_csextensionmanager/images/products/' . $local;
    }

    /**
     * Browser URL for an item's icon.
     *
     * @param   array<string, mixed>  $item  Catalog item.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function iconSrc(array $item): string
    {
        $icon = (string) ($item['icon'] ?? '');

        if (preg_match('#^https://#i', $icon)) {
            return $icon;
        }

        if (!str_starts_with($icon, 'media/com_csextensionmanager/images/products/')) {
            $icon = 'media/com_csextensionmanager/images/products/default.svg';
        }

        return Uri::root(true) . '/' . $icon;
    }

    /**
     * @param   string  $url  URL.
     *
     * @return  string  The URL if it's http(s), '' otherwise.
     *
     * @since   0.1.0
     */
    private static function httpUrl(string $url): string
    {
        return preg_match('#^https?://#i', trim($url)) ? trim($url) : '';
    }

    /**
     * "VMT + ET Templates" → "vmt-et-templates".
     *
     * @param   string  $text  Text.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function slug(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }
}
