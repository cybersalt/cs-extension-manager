<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Joins the catalog with what's installed: status, compatibility,
 * entitlement, dependencies and package contents for every item.
 *
 * @since  0.1.0
 */
final class StateHelper
{
    public const STATUS_UPDATE      = 'update';
    public const STATUS_CURRENT     = 'current';
    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_BROKEN      = 'broken';

    /**
     * Add install state to every item, and drop add-ons the Options say to hide.
     *
     * @param   array<string, array<string, mixed>>  $items  Catalog items.
     *
     * @return  array<string, array<string, mixed>>
     *
     * @since   0.1.0
     */
    public static function enrich(array $items): array
    {
        $descriptors = [];

        foreach ($items as $item) {
            $descriptors[] = ['type' => $item['type'], 'folder' => $item['folder'], 'element' => $item['element']];

            if (\is_array($item['target'] ?? null)) {
                $descriptors[] = $item['target'];
            }

            foreach ((array) ($item['superseded_by'] ?? []) as $s) {
                $descriptors[] = $s;
            }
        }

        $rows       = InstalledHelper::findMany($descriptors);
        $packageIds = [];

        foreach ($items as $item) {
            $row = $rows[$item['key']] ?? null;

            if ($row !== null && $item['type'] === 'package') {
                $packageIds[] = (int) $row->extension_id;
            }
        }

        $children    = InstalledHelper::childrenOf($packageIds);
        $includeBeta = (int) ParamsHelper::get()->get('include_beta', 0) === 1;
        $account     = AccountHelper::getState();
        $result      = [];

        foreach ($items as $key => $item) {
            $row = $rows[$key] ?? null;

            $item['installed']         = $row !== null;
            $item['extension_id']      = $row !== null ? (int) $row->extension_id : 0;
            $item['in_package']        = $row !== null && (int) $row->package_id > 0;
            $item['enabled']           = $row !== null && (int) $row->enabled === 1;
            $item['installed_version'] = $row !== null ? InstalledHelper::version($row) : '';
            $item['children']          = [];

            if ($row !== null && $item['type'] === 'package') {
                foreach ($children[(int) $row->extension_id] ?? [] as $child) {
                    $item['children'][] = [
                        'id'      => (int) $child->extension_id,
                        'name'    => InstalledHelper::label($child),
                        'type'    => (string) $child->type,
                        'folder'  => (string) $child->folder,
                        'element' => (string) $child->element,
                        'enabled' => (int) $child->enabled === 1,
                        'version' => InstalledHelper::version($child),
                    ];
                }
            }

            // Target extension (add-ons) and "already covered by" products.
            $item['target_installed'] = null;

            if (\is_array($item['target'] ?? null)) {
                $t                        = $item['target'];
                $item['target_installed'] = isset($rows[CatalogHelper::key((string) $t['type'], (string) ($t['folder'] ?? ''), (string) $t['element'])]);
            }

            $item['superseded_name'] = '';

            foreach ((array) ($item['superseded_by'] ?? []) as $s) {
                if (isset($rows[CatalogHelper::key((string) $s['type'], (string) ($s['folder'] ?? ''), (string) $s['element'])])) {
                    $item['superseded_name'] = (string) $s['display_name'];
                    break;
                }
            }

            // Compatibility with this site.
            [$item['compatible'], $item['incompatible_reason']] = self::compatibility($item);

            // Beta builds only count as updates when Options allow them.
            $item['beta_hidden'] = ($item['stability'] ?? 'stable') !== 'stable' && !$includeBeta;

            $item['entitled']       = AccountHelper::isEntitled($item);
            $item['needs_download_id'] = ($item['tier'] ?? 'free') === 'pro' && $account['dlid'] === '';
            $item['status']         = self::status($item);
            $item['update_type']    = $item['status'] === self::STATUS_UPDATE
                ? self::updateType((string) $item['installed_version'], (string) $item['latest_version'])
                : '';

            $result[$key] = $item;
        }

        // Dependencies: which required items aren't installed. A required item
        // outside this set (enrich() on a single item) is looked up directly.
        foreach ($result as $key => $item) {
            $missing = [];

            foreach ((array) ($item['requires'] ?? []) as $requiredKey) {
                $requiredKey = (string) $requiredKey;

                if (isset($result[$requiredKey])) {
                    $present = !empty($result[$requiredKey]['installed']);
                } else {
                    [$type, $folder, $element] = array_pad(explode(':', $requiredKey, 3), 3, '');
                    $present                   = InstalledHelper::find($type, $folder, $element) !== null;
                }

                if (!$present) {
                    $missing[] = $requiredKey;
                }
            }

            $result[$key]['requires_missing'] = $missing;
        }

        return self::filterAddons($result);
    }

    /**
     * Counts for the stat cards.
     *
     * @param   array<string, array<string, mixed>>  $items  Enriched items.
     *
     * @return  array<string, int>
     *
     * @since   0.1.0
     */
    public static function counts(array $items): array
    {
        $counts = ['all' => 0, 'installed' => 0, 'update' => 0, 'available' => 0, 'broken' => 0, 'pro' => 0, 'addons' => 0];

        foreach ($items as $item) {
            $counts['all']++;
            $counts['installed'] += $item['installed'] ? 1 : 0;
            $counts['update']    += $item['status'] === self::STATUS_UPDATE ? 1 : 0;
            $counts['available'] += $item['status'] === self::STATUS_AVAILABLE ? 1 : 0;
            $counts['broken']    += $item['status'] === self::STATUS_BROKEN ? 1 : 0;
            $counts['pro']       += $item['tier'] === 'pro' ? 1 : 0;
            $counts['addons']    += $item['group'] === CatalogHelper::GROUP_ADDON ? 1 : 0;
        }

        return $counts;
    }

    /**
     * major / minor / patch, for "auto-update patch releases only".
     *
     * @param   string  $from  Installed version.
     * @param   string  $to    Latest version.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function updateType(string $from, string $to): string
    {
        $a = array_map('intval', array_pad(explode('.', (string) preg_replace('/[^0-9.].*$/', '', $from)), 3, '0'));
        $b = array_map('intval', array_pad(explode('.', (string) preg_replace('/[^0-9.].*$/', '', $to)), 3, '0'));

        if ($b[0] !== $a[0]) {
            return 'major';
        }

        return $b[1] !== $a[1] ? 'minor' : 'patch';
    }

    /**
     * Can this site run the latest version?
     *
     * @param   array<string, mixed>  $item  Catalog item.
     *
     * @return  array{0: bool, 1: string}  [compatible, reason]
     *
     * @since   0.1.0
     */
    public static function compatibility(array $item): array
    {
        $platform = (string) ($item['targetplatform'] ?? '');

        if ($platform !== '' && !FeedParser::matchesPlatform($platform)) {
            return [false, Text::sprintf('COM_CSEXTENSIONMANAGER_INCOMPATIBLE_JOOMLA', JVERSION)];
        }

        $php = (string) ($item['php_minimum'] ?? '');

        if ($php !== '' && version_compare(PHP_VERSION, $php, '<')) {
            return [false, Text::sprintf('COM_CSEXTENSIONMANAGER_INCOMPATIBLE_PHP', $php, PHP_VERSION)];
        }

        return [true, ''];
    }

    /**
     * @param   array<string, mixed>  $item  Item with install fields.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function status(array $item): string
    {
        if (!$item['installed']) {
            return $item['available'] ? self::STATUS_AVAILABLE : self::STATUS_UNAVAILABLE;
        }

        // A package row with no version or no children left is half-installed.
        if ($item['installed_version'] === '' || ($item['type'] === 'package' && $item['children'] === [])) {
            return self::STATUS_BROKEN;
        }

        if (
            $item['available']
            && !$item['beta_hidden']
            && $item['latest_version'] !== ''
            && version_compare((string) $item['latest_version'], (string) $item['installed_version'], '>')
        ) {
            return self::STATUS_UPDATE;
        }

        return self::STATUS_CURRENT;
    }

    /**
     * Options → "Show MCP add-ons": all / only for extensions on this site / none.
     * Installed add-ons are always shown.
     *
     * @param   array<string, array<string, mixed>>  $items  Enriched items.
     *
     * @return  array<string, array<string, mixed>>
     *
     * @since   0.1.0
     */
    private static function filterAddons(array $items): array
    {
        $mode = (string) ParamsHelper::get()->get('show_addons', 'all');

        if ($mode === 'all') {
            return $items;
        }

        return array_filter($items, static function (array $item) use ($mode): bool {
            if ($item['group'] !== CatalogHelper::GROUP_ADDON || $item['installed']) {
                return true;
            }

            return $mode === 'relevant' && $item['target_installed'] === true;
        });
    }
}
