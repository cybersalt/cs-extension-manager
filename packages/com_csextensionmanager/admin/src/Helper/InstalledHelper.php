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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * What is installed on this site, read from #__extensions.
 *
 * @since  0.1.0
 */
final class InstalledHelper
{
    /**
     * Columns read for every extension row.
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const COLUMNS = ['extension_id', 'package_id', 'name', 'type', 'element', 'folder', 'client_id', 'enabled', 'protected', 'locked', 'manifest_cache'];

    /**
     * One extension row.
     *
     * @param   string  $type     Extension type.
     * @param   string  $folder   Plugin group ('' for other types).
     * @param   string  $element  Element.
     *
     * @return  object|null
     *
     * @since   0.1.0
     */
    public static function find(string $type, string $folder, string $element): ?object
    {
        $rows = self::findMany([['type' => $type, 'folder' => $folder, 'element' => $element]]);

        return $rows[CatalogHelper::key($type, $folder, $element)] ?? null;
    }

    /**
     * Rows for many descriptors in one query.
     *
     * @param   array<int, array{type: string, folder?: string, element: string}>  $descriptors  What to look for.
     *
     * @return  array<string, object>  Keyed by CatalogHelper::key(). Missing extensions are absent.
     *
     * @since   0.1.0
     */
    public static function findMany(array $descriptors): array
    {
        $elements = array_values(array_unique(array_filter(array_map(
            static fn (array $d): string => (string) ($d['element'] ?? ''),
            $descriptors
        ))));

        if ($elements === []) {
            return [];
        }

        $db    = self::db();
        $query = $db->createQuery()
            ->select($db->quoteName(self::COLUMNS))
            ->from($db->quoteName('#__extensions'))
            ->whereIn($db->quoteName('element'), $elements, ParameterType::STRING);

        $byKey = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $folder = (string) $row->type === 'plugin' ? (string) $row->folder : '';
            $key    = CatalogHelper::key((string) $row->type, $folder, (string) $row->element);

            // Site and admin copies of a module/template share an element; keep the first.
            $byKey[$key] ??= $row;
        }

        $result = [];

        foreach ($descriptors as $d) {
            $key = CatalogHelper::key((string) $d['type'], (string) ($d['folder'] ?? ''), (string) $d['element']);

            if (isset($byKey[$key])) {
                $result[$key] = $byKey[$key];
            }
        }

        return $result;
    }

    /**
     * An extension row by id.
     *
     * @param   int  $id  extension_id.
     *
     * @return  object|null
     *
     * @since   0.1.0
     */
    public static function byId(int $id): ?object
    {
        $db    = self::db();
        $query = $db->createQuery()
            ->select($db->quoteName(self::COLUMNS))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * Child extensions of an installed package.
     *
     * @param   int  $packageId  The package's extension_id.
     *
     * @return  object[]
     *
     * @since   0.1.0
     */
    public static function children(int $packageId): array
    {
        if ($packageId <= 0) {
            return [];
        }

        $db    = self::db();
        $query = $db->createQuery()
            ->select($db->quoteName(self::COLUMNS))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('package_id') . ' = :pid')
            ->bind(':pid', $packageId, ParameterType::INTEGER)
            ->order($db->quoteName(['type', 'folder', 'element']));

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Child rows for many packages at once.
     *
     * @param   int[]  $packageIds  Package extension ids.
     *
     * @return  array<int, object[]>  Package id => children.
     *
     * @since   0.1.0
     */
    public static function childrenOf(array $packageIds): array
    {
        $packageIds = array_values(array_filter(array_map('intval', $packageIds)));

        if ($packageIds === []) {
            return [];
        }

        $db    = self::db();
        $query = $db->createQuery()
            ->select($db->quoteName(self::COLUMNS))
            ->from($db->quoteName('#__extensions'))
            ->whereIn($db->quoteName('package_id'), $packageIds, ParameterType::INTEGER)
            ->order($db->quoteName(['type', 'folder', 'element']));

        $result = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $result[(int) $row->package_id][] = $row;
        }

        return $result;
    }

    /**
     * Version from a row's manifest_cache.
     *
     * @param   object  $row  Extension row.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function version(object $row): string
    {
        $cache = json_decode((string) ($row->manifest_cache ?? ''), true);

        return \is_array($cache) ? trim((string) ($cache['version'] ?? '')) : '';
    }

    /**
     * Human name of a row: the translated manifest name when the language
     * file is loaded, the raw name otherwise.
     *
     * @param   object  $row  Extension row.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function label(object $row): string
    {
        $name = (string) $row->name;
        $lang = Factory::getApplication()->getLanguage();

        if ($lang->hasKey($name)) {
            return $lang->_($name);
        }

        // Plugins, modules and components keep their strings in their own .sys.ini.
        $source = match ((string) $row->type) {
            'plugin'    => 'plg_' . $row->folder . '_' . $row->element,
            'component', 'module', 'package' => (string) $row->element,
            default     => '',
        };

        if ($source !== '') {
            $base = (string) $row->type === 'plugin' ? JPATH_PLUGINS . '/' . $row->folder . '/' . $row->element : JPATH_ADMINISTRATOR;
            $lang->load($source . '.sys', JPATH_ADMINISTRATOR, null, false, true)
                || $lang->load($source . '.sys', $base, null, false, true);

            if ($lang->hasKey($name)) {
                return $lang->_($name);
            }
        }

        return $name;
    }

    /**
     * @return  DatabaseInterface
     *
     * @since   0.1.0
     */
    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
