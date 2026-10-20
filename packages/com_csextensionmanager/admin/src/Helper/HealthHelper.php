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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Health checks for the Cybersalt extensions on this site, each with a
 * one-click fix where one is safe.
 *
 * Issues are recomputed on every request; a fix request only carries the
 * issue id and is re-validated against a fresh run, so nothing in the
 * request decides what gets written.
 *
 * @since  0.1.0
 */
final class HealthHelper
{
    public const SEVERITY_DANGER  = 'danger';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO    = 'info';

    /**
     * Fixes "Fix all" may apply: bookkeeping only, nothing that changes what
     * runs on the site.
     *
     * @var    string[]
     * @since  0.1.0
     */
    public const SAFE_FIXES = [
        'add_update_site',
        'enable_update_site',
        'repair_update_site',
        'dedupe_update_sites',
        'sync_dlid',
        'delete_update_site',
        'purge_updates',
    ];

    /**
     * Run every check.
     *
     * @param   array<string, array<string, mixed>>|null  $items  Enriched catalog items (built when null).
     *
     * @return  array<int, array<string, mixed>>  Issues, most severe first.
     *
     * @since   0.1.0
     */
    public static function check(?array $items = null): array
    {
        $catalog = CatalogHelper::get();
        $items ??= StateHelper::enrich($catalog['items']);
        $issues  = [];
        $account = AccountHelper::getState();

        $installed = array_filter($items, static fn (array $i): bool => (bool) $i['installed']);
        $sites     = self::updateSitesFor(array_map(static fn (array $i): int => (int) $i['extension_id'], $installed));

        foreach ($installed as $item) {
            $id    = (int) $item['extension_id'];
            $title = (string) $item['title'];
            $linked = $sites[$id] ?? [];

            // --- Update sites -------------------------------------------------
            if (!$item['in_package']) {
                $hasFeed = array_filter($linked, static fn (object $s): bool => StoreClient::isStoreUrl((string) $s->location, ['api.updatexml']) && self::feedElement((string) $s->location) === strtolower((string) $item['element'])) !== [];

                if ($linked === []) {
                    $issues[] = self::issue('no_update_site', self::SEVERITY_WARNING, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_NO_UPDATE_SITE', $title), 'add_update_site', $id);
                } else {
                    foreach ($linked as $site) {
                        $location = (string) $site->location;

                        if (!StoreClient::isStoreUrl($location, ['api.updatexml'])) {
                            // With a good feed next to it, the stray one just goes; otherwise it's repointed.
                            $issues[] = self::issue(
                                'foreign_update_site',
                                self::SEVERITY_WARNING,
                                $item,
                                Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_FOREIGN_UPDATE_SITE', $title, $location),
                                $hasFeed ? 'dedupe_update_sites' : 'repair_update_site',
                                $hasFeed ? $id : (int) $site->update_site_id
                            );
                            continue;
                        }

                        $feedElement = self::feedElement($location);

                        if ($feedElement !== strtolower((string) $item['element'])) {
                            $issues[] = self::issue('wrong_update_site', self::SEVERITY_WARNING, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_WRONG_UPDATE_SITE', $title, $feedElement), 'repair_update_site', (int) $site->update_site_id);
                        }

                        if ((int) $site->enabled !== 1) {
                            $issues[] = self::issue('update_site_disabled', self::SEVERITY_WARNING, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_UPDATE_SITE_DISABLED', $title), 'enable_update_site', (int) $site->update_site_id);
                        }

                        if ($item['tier'] === 'pro' && $account['dlid'] !== '' && (int) ParamsHelper::get()->get('sync_update_sites', 1) === 1 && !str_contains((string) $site->extra_query, $account['dlid'])) {
                            $issues[] = self::issue(
                                'dlid_missing',
                                self::SEVERITY_WARNING,
                                $item,
                                Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_DLID_MISSING', $title),
                                'sync_dlid',
                                (int) $site->update_site_id
                            );
                        }
                    }

                    if (\count($linked) > 1) {
                        $issues[] = self::issue('duplicate_update_sites', self::SEVERITY_INFO, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_DUPLICATE_UPDATE_SITES', $title, \count($linked)), 'dedupe_update_sites', $id);
                    }
                }

                if ($item['tier'] === 'pro' && $account['dlid'] === '') {
                    $issues[] = self::issue('no_dlid', self::SEVERITY_DANGER, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_NO_DLID', $title), '', 0, 'index.php?option=com_csextensionmanager&view=extensions#cs-account');
                }
            }

            // --- Package integrity ---------------------------------------------
            if ($item['status'] === StateHelper::STATUS_BROKEN) {
                $issues[] = self::issue('broken', self::SEVERITY_DANGER, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_BROKEN', $title), 'reinstall', 0);
            }

            // --- Disabled parts -----------------------------------------------
            $parts = $item['type'] === 'package' ? $item['children'] : [[
                'id' => $id, 'name' => $title, 'type' => $item['type'], 'enabled' => $item['enabled'],
            ]];

            foreach ($parts as $part) {
                if (!$part['enabled'] && \in_array($part['type'], ['plugin', 'component', 'module'], true) && empty($item['self'])) {
                    $issues[] = self::issue(
                        'disabled_part',
                        self::SEVERITY_INFO,
                        $item,
                        Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_DISABLED_PART', (string) $part['name'], $title),
                        'enable_extension',
                        (int) $part['id']
                    );
                }
            }

            // --- Add-ons --------------------------------------------------------
            if ($item['group'] === CatalogHelper::GROUP_ADDON) {
                if ($item['requires_missing'] !== []) {
                    $issues[] = self::issue('addon_without_parent', self::SEVERITY_DANGER, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_ADDON_NO_PARENT', $title), 'install_parent', 0);
                }

                if ($item['target_installed'] === false && \is_array($item['target'])) {
                    $issues[] = self::issue('addon_target_missing', self::SEVERITY_INFO, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_ADDON_TARGET_MISSING', $title, (string) $item['target']['display_name']), '', 0);
                }

                if ($item['superseded_name'] !== '') {
                    $issues[] = self::issue('superseded', self::SEVERITY_INFO, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_SUPERSEDED', $title, $item['superseded_name']), '', 0);
                }
            }

            // --- Updates this site can't take -----------------------------------
            if ($item['available'] && !$item['compatible'] && version_compare((string) $item['latest_version'], (string) $item['installed_version'], '>')) {
                $issues[] = self::issue('incompatible_update', self::SEVERITY_INFO, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_INCOMPATIBLE_UPDATE', $title, (string) $item['latest_version'], (string) $item['incompatible_reason']), '', 0);
            }
        }

        // --- Packages missing, children left behind -------------------------------
        foreach ($items as $item) {
            if ($item['installed'] || $item['type'] !== 'package' || !$item['available']) {
                continue;
            }

            $orphans = self::orphanChildren((string) $item['element']);

            if ($orphans !== []) {
                $issues[] = self::issue('orphan_children', self::SEVERITY_WARNING, $item, Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_ORPHAN_CHILDREN', implode(', ', $orphans), (string) $item['title']), 'reinstall', 0);
            }
        }

        // --- Store feeds nobody uses --------------------------------------------
        foreach (self::orphanUpdateSites() as $site) {
            $issues[] = [
                'id'       => 'orphan_update_site:' . (int) $site->update_site_id,
                'check'    => 'orphan_update_site',
                'severity' => self::SEVERITY_INFO,
                'item_key' => '',
                'title'    => (string) $site->name,
                'message'  => Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_ORPHAN_UPDATE_SITE', (string) $site->name),
                'fix'      => 'delete_update_site',
                'target'   => (int) $site->update_site_id,
                'link'     => '',
            ];
        }

        // --- Phantom updates in Joomla's update table -----------------------------
        $stale = self::staleUpdateRows($installed);

        if ($stale > 0) {
            $issues[] = [
                'id'       => 'stale_updates',
                'check'    => 'stale_updates',
                'severity' => self::SEVERITY_INFO,
                'item_key' => '',
                'title'    => Text::_('COM_CSEXTENSIONMANAGER_HEALTH_STALE_UPDATES_TITLE'),
                'message'  => Text::plural('COM_CSEXTENSIONMANAGER_HEALTH_STALE_UPDATES', $stale),
                'fix'      => 'purge_updates',
                'target'   => 0,
                'link'     => '',
            ];
        }

        // --- Account ------------------------------------------------------------
        if ($account['status'] === 'lapsed') {
            $issues[] = [
                'id'       => 'account_lapsed',
                'check'    => 'account_lapsed',
                'severity' => self::SEVERITY_WARNING,
                'item_key' => '',
                'title'    => Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_TITLE'),
                'message'  => Text::_('COM_CSEXTENSIONMANAGER_HEALTH_ACCOUNT_LAPSED'),
                'fix'      => '',
                'target'   => 0,
                'link'     => $account['renewal_url'] !== '' ? $account['renewal_url'] : SupportHelper::PLANS_URL,
            ];
        }

        // --- Store reachable? ----------------------------------------------------
        if ($catalog['errors'] !== []) {
            $issues[] = [
                'id'       => 'catalog_errors',
                'check'    => 'catalog_errors',
                'severity' => self::SEVERITY_WARNING,
                'item_key' => '',
                'title'    => Text::_('COM_CSEXTENSIONMANAGER_HEALTH_CATALOG_TITLE'),
                'message'  => Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_CATALOG_ERRORS', implode('; ', \array_slice($catalog['errors'], 0, 5))),
                'fix'      => '',
                'target'   => 0,
                'link'     => '',
            ];
        }

        $rank = [self::SEVERITY_DANGER => 0, self::SEVERITY_WARNING => 1, self::SEVERITY_INFO => 2];
        usort($issues, static fn (array $a, array $b): int => [$rank[$a['severity']], $a['title']] <=> [$rank[$b['severity']], $b['title']]);

        return $issues;
    }

    /**
     * Apply the fix for one issue.
     *
     * @param   array<string, mixed>                 $issue  An issue from check().
     * @param   array<string, array<string, mixed>>  $items  Enriched catalog items.
     *
     * @return  array{ok: bool, message: string}
     *
     * @since   0.1.0
     */
    public static function fix(array $issue, array $items): array
    {
        $item = $items[$issue['item_key']] ?? null;
        $db   = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            switch ($issue['fix']) {
                case 'add_update_site':
                    self::addUpdateSite($db, $item);
                    break;

                case 'enable_update_site':
                    self::updateSite($db, (int) $issue['target'], ['enabled' => 1]);
                    break;

                case 'repair_update_site':
                    $location = StoreClient::updateXmlUrl((string) $item['element']);
                    $dlid     = AccountHelper::getDlid();

                    if ($dlid !== '' && (int) ParamsHelper::get()->get('sync_update_sites', 1) === 1) {
                        $location = StoreClient::withQueryParam($location, 'dlid', $dlid);
                    }

                    self::updateSite($db, (int) $issue['target'], [
                        'location'             => $location,
                        'extra_query'          => $dlid !== '' ? 'dlid=' . $dlid : '',
                        'enabled'              => 1,
                        'last_check_timestamp' => 0,
                    ]);
                    break;

                case 'dedupe_update_sites':
                    self::dedupe($db, (int) $item['extension_id']);
                    break;

                case 'sync_dlid':
                    AccountHelper::syncUpdateSites();
                    break;

                case 'delete_update_site':
                    // An earlier fix in the same run may have linked it back up.
                    $stillOrphan = \in_array((int) $issue['target'], array_map(static fn (object $s): int => (int) $s->update_site_id, self::orphanUpdateSites()), true);

                    if ($stillOrphan) {
                        self::deleteUpdateSite($db, (int) $issue['target']);
                    }
                    break;

                case 'purge_updates':
                    self::purgeStaleUpdates($db, array_filter($items, static fn (array $i): bool => (bool) $i['installed']));
                    break;

                case 'enable_extension':
                    $result = InstallHelper::setEnabled($item, (int) $issue['target'], true);

                    return $result;

                case 'reinstall':
                    $result = InstallHelper::install($item);

                    return ['ok' => $result['ok'], 'message' => $result['message']];

                case 'install_parent':
                    $parentKey = (string) ($item['requires_missing'][0] ?? '');
                    $parent    = $items[$parentKey] ?? CatalogHelper::find($parentKey);

                    if ($parent === null) {
                        return ['ok' => false, 'message' => Text::_('COM_CSEXTENSIONMANAGER_HEALTH_FIX_UNKNOWN')];
                    }

                    $result = InstallHelper::install($parent);

                    return ['ok' => $result['ok'], 'message' => $result['message']];

                default:
                    return ['ok' => false, 'message' => Text::_('COM_CSEXTENSIONMANAGER_HEALTH_FIX_UNKNOWN')];
            }
        } catch (\Throwable $e) {
            LogHelper::log(LogHelper::ACTION_FIX, LogHelper::STATUS_ERROR, (string) ($item['element'] ?? ''), (string) $issue['title'], '', '', (string) $issue['message'], $e->getMessage());

            return ['ok' => false, 'message' => Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_FIX_FAILED', $e->getMessage())];
        }

        $message = Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_FIXED', (string) $issue['message']);
        LogHelper::log(LogHelper::ACTION_FIX, LogHelper::STATUS_SUCCESS, (string) ($item['element'] ?? ''), (string) $issue['title'], '', '', (string) $issue['message'], (string) $issue['fix']);

        return ['ok' => true, 'message' => $message];
    }

    /**
     * Update sites linked to these extensions.
     *
     * @param   int[]  $extensionIds  Extension ids.
     *
     * @return  array<int, object[]>  Extension id => update site rows.
     *
     * @since   0.1.0
     */
    public static function updateSitesFor(array $extensionIds): array
    {
        $extensionIds = array_values(array_filter(array_map('intval', $extensionIds)));

        if ($extensionIds === []) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName(['us.update_site_id', 'us.name', 'us.location', 'us.enabled', 'us.extra_query', 'use.extension_id']))
            ->from($db->quoteName('#__update_sites', 'us'))
            ->join('INNER', $db->quoteName('#__update_sites_extensions', 'use'), $db->quoteName('use.update_site_id') . ' = ' . $db->quoteName('us.update_site_id'))
            ->whereIn($db->quoteName('use.extension_id'), $extensionIds, ParameterType::INTEGER)
            ->order($db->quoteName('us.update_site_id'));

        $result = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $result[(int) $row->extension_id][] = $row;
        }

        return $result;
    }

    /**
     * @param   string                $check     Check id.
     * @param   string                $severity  SEVERITY_* constant.
     * @param   array<string, mixed>  $item      Catalog item.
     * @param   string                $message   Text.
     * @param   string                $fix       Fix code ('' = none).
     * @param   int                   $target    Update site / extension id the fix acts on.
     * @param   string                $link      Where to go instead of a fix.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    private static function issue(string $check, string $severity, array $item, string $message, string $fix, int $target, string $link = ''): array
    {
        return [
            'id'       => $check . ':' . $item['key'] . ':' . $target,
            'check'    => $check,
            'severity' => $severity,
            'item_key' => (string) $item['key'],
            'title'    => (string) $item['title'],
            'message'  => $message,
            'fix'      => $fix,
            'target'   => $target,
            'link'     => $link,
        ];
    }

    /**
     * Children of a Cybersalt package that are installed on their own
     * (package_id 0) while the package itself isn't: they never get updates,
     * because the update server lives on the package.
     *
     * @param   string  $packageElement  e.g. pkg_cspageprotector.
     *
     * @return  string[]  Elements found.
     *
     * @since   0.1.0
     */
    private static function orphanChildren(string $packageElement): array
    {
        $name = (string) preg_replace('/^pkg_/', '', $packageElement);
        $name = str_replace('-', '', $name);

        if ($name === '' || $name === $packageElement) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $com   = 'com_' . $name;
        $query = $db->createQuery()
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('package_id') . ' = 0')
            ->where('(' . $db->quoteName('element') . ' = :com OR (' . $db->quoteName('type') . ' = ' . $db->quote('plugin') . ' AND ' . $db->quoteName('element') . ' = :plg))')
            ->bind(':com', $com)
            ->bind(':plg', $name);

        return array_values(array_unique($db->setQuery($query)->loadColumn() ?: []));
    }

    /**
     * The `element` a store feed URL asks about (lower case, '' if none).
     *
     * @param   string  $location  Update site URL.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function feedElement(string $location): string
    {
        parse_str((string) parse_url(html_entity_decode($location, ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY), $query);

        return strtolower((string) ($query['element'] ?? ''));
    }

    /**
     * Store feeds that no installed extension points at any more.
     *
     * @return  object[]
     *
     * @since   0.1.0
     */
    private static function orphanUpdateSites(): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName(['us.update_site_id', 'us.name', 'us.location']))
            ->from($db->quoteName('#__update_sites', 'us'))
            ->join('LEFT', $db->quoteName('#__update_sites_extensions', 'use'), $db->quoteName('use.update_site_id') . ' = ' . $db->quoteName('us.update_site_id'))
            ->join('LEFT', $db->quoteName('#__extensions', 'e'), $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('use.extension_id'))
            ->where($db->quoteName('us.location') . ' LIKE ' . $db->quote('%com_csreleasemanager%'))
            ->where($db->quoteName('e.extension_id') . ' IS NULL');

        return array_values(array_filter(
            $db->setQuery($query)->loadObjectList() ?: [],
            static fn (object $row): bool => StoreClient::isStoreUrl((string) $row->location, ['api.updatexml'])
        ));
    }

    /**
     * Rows in #__updates for Cybersalt extensions offering a version that is
     * already installed (or older).
     *
     * @param   array<string, array<string, mixed>>  $installed  Installed items.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    private static function staleUpdateRows(array $installed): int
    {
        return \count(self::staleUpdateIds($installed));
    }

    /**
     * @param   array<string, array<string, mixed>>  $installed  Installed items.
     *
     * @return  int[]  update_id values.
     *
     * @since   0.1.0
     */
    private static function staleUpdateIds(array $installed): array
    {
        $byId = [];

        foreach ($installed as $item) {
            $byId[(int) $item['extension_id']] = (string) $item['installed_version'];
        }

        if ($byId === []) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName(['update_id', 'extension_id', 'version']))
            ->from($db->quoteName('#__updates'))
            ->whereIn($db->quoteName('extension_id'), array_keys($byId), ParameterType::INTEGER);

        $stale = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $installedVersion = $byId[(int) $row->extension_id] ?? '';

            if ($installedVersion !== '' && version_compare((string) $row->version, $installedVersion, '<=')) {
                $stale[] = (int) $row->update_id;
            }
        }

        return $stale;
    }

    /**
     * @param   DatabaseInterface                    $db         Database.
     * @param   array<string, array<string, mixed>>  $installed  Installed items.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function purgeStaleUpdates(DatabaseInterface $db, array $installed): void
    {
        $ids = self::staleUpdateIds($installed);

        if ($ids !== []) {
            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__updates'))
                    ->whereIn($db->quoteName('update_id'), $ids, ParameterType::INTEGER)
            )->execute();
        }
    }

    /**
     * Create and link the store feed for an installed item.
     *
     * @param   DatabaseInterface          $db    Database.
     * @param   array<string, mixed>|null  $item  Enriched item.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function addUpdateSite(DatabaseInterface $db, ?array $item): void
    {
        if ($item === null || empty($item['extension_id'])) {
            throw new \RuntimeException('Not installed.');
        }

        // An unlinked store feed for this element is still there: link that one back.
        foreach (self::orphanUpdateSites() as $orphan) {
            if (self::feedElement((string) $orphan->location) === strtolower((string) $item['element'])) {
                $link                 = new \stdClass();
                $link->update_site_id = (int) $orphan->update_site_id;
                $link->extension_id   = (int) $item['extension_id'];

                $db->insertObject('#__update_sites_extensions', $link);
                self::updateSite($db, (int) $orphan->update_site_id, ['enabled' => 1, 'last_check_timestamp' => 0]);
                AccountHelper::syncUpdateSites();

                return;
            }
        }

        $dlid     = AccountHelper::getDlid();
        $sync     = $dlid !== '' && (int) ParamsHelper::get()->get('sync_update_sites', 1) === 1;
        $location = StoreClient::updateXmlUrl((string) $item['element']);

        $site                       = new \stdClass();
        $site->name                 = (string) $item['title'] . ' Updates';
        $site->type                 = 'extension';
        $site->location             = $sync ? StoreClient::withQueryParam($location, 'dlid', $dlid) : $location;
        $site->enabled              = 1;
        $site->last_check_timestamp = 0;
        $site->extra_query          = $sync ? 'dlid=' . $dlid : '';

        $db->insertObject('#__update_sites', $site, 'update_site_id');

        $link                 = new \stdClass();
        $link->update_site_id = (int) $site->update_site_id;
        $link->extension_id   = (int) $item['extension_id'];

        $db->insertObject('#__update_sites_extensions', $link);
    }

    /**
     * @param   DatabaseInterface     $db      Database.
     * @param   int                   $siteId  update_site_id.
     * @param   array<string, mixed>  $values  Columns to set.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function updateSite(DatabaseInterface $db, int $siteId, array $values): void
    {
        $row                 = new \stdClass();
        $row->update_site_id = $siteId;

        foreach ($values as $column => $value) {
            $row->{$column} = $value;
        }

        $db->updateObject('#__update_sites', $row, 'update_site_id');
    }

    /**
     * Keep one update site per extension: an enabled store feed if there is one.
     *
     * @param   DatabaseInterface  $db           Database.
     * @param   int                $extensionId  Extension id.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function dedupe(DatabaseInterface $db, int $extensionId): void
    {
        $sites = self::updateSitesFor([$extensionId])[$extensionId] ?? [];

        if (\count($sites) < 2) {
            return;
        }

        usort($sites, static function (object $a, object $b): int {
            $score = static fn (object $s): int => (StoreClient::isStoreUrl((string) $s->location, ['api.updatexml']) ? 2 : 0) + ((int) $s->enabled === 1 ? 1 : 0);

            return [$score($b), (int) $a->update_site_id] <=> [$score($a), (int) $b->update_site_id];
        });

        foreach (\array_slice($sites, 1) as $site) {
            self::deleteUpdateSite($db, (int) $site->update_site_id, $extensionId);
        }
    }

    /**
     * Remove an update site (or just its link to one extension when it's
     * shared with others).
     *
     * @param   DatabaseInterface  $db           Database.
     * @param   int                $siteId       update_site_id.
     * @param   int                $extensionId  Only unlink from this extension (0 = whole site).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function deleteUpdateSite(DatabaseInterface $db, int $siteId, int $extensionId = 0): void
    {
        $unlink = $db->createQuery()
            ->delete($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('update_site_id') . ' = :sid')
            ->bind(':sid', $siteId, ParameterType::INTEGER);

        if ($extensionId > 0) {
            $unlink->where($db->quoteName('extension_id') . ' = :eid')->bind(':eid', $extensionId, ParameterType::INTEGER);
        }

        $db->setQuery($unlink)->execute();

        $remaining = (int) $db->setQuery(
            $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName('#__update_sites_extensions'))
                ->where($db->quoteName('update_site_id') . ' = :sid')
                ->bind(':sid', $siteId, ParameterType::INTEGER)
        )->loadResult();

        if ($remaining === 0) {
            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__update_sites'))
                    ->where($db->quoteName('update_site_id') . ' = :sid')
                    ->bind(':sid', $siteId, ParameterType::INTEGER)
            )->execute();

            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__updates'))
                    ->where($db->quoteName('update_site_id') . ' = :sid')
                    ->bind(':sid', $siteId, ParameterType::INTEGER)
            )->execute();
        }
    }
}
