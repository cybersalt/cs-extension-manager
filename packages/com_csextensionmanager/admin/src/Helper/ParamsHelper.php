<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Component params, read straight from the database.
 *
 * ComponentHelper keeps a static copy for the whole request and, with caching
 * on, a copy in the `_system` cache group. The activation flow writes params
 * mid-request and reads them back, so this helper always goes to the
 * database and cleans `_system` after a write. Every key written here also
 * has a hidden field in config.xml, or the next Options save drops it.
 *
 * @since  0.1.0
 */
final class ParamsHelper
{
    public const COMPONENT = 'com_csextensionmanager';

    /**
     * @var    Registry|null
     * @since  0.1.0
     */
    private static ?Registry $memo = null;

    /**
     * Component params (memoised for the request, reset on save()).
     *
     * @return  Registry
     *
     * @since   0.1.0
     */
    public static function get(): Registry
    {
        if (self::$memo === null) {
            self::$memo = self::readComponentParams(self::COMPONENT);
        }

        return self::$memo;
    }

    /**
     * Another component's params, straight from the database (used to read
     * MCP for J's activation). Empty Registry when it isn't installed.
     *
     * @param   string  $element  Component element, e.g. com_csmcpforj.
     *
     * @return  Registry
     *
     * @since   0.1.0
     */
    public static function readComponentParams(string $element): Registry
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->createQuery()
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':element', $element);

            return new Registry((string) $db->setQuery($query)->loadResult());
        } catch (\Throwable $e) {
            return new Registry();
        }
    }

    /**
     * Merge values into the component params and save.
     *
     * @param   array<string, mixed>  $values  Keys to set. A null value removes the key.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function save(array $values): void
    {
        $params = self::readComponentParams(self::COMPONENT);

        foreach ($values as $key => $value) {
            if ($value === null) {
                $params->remove($key);
            } else {
                $params->set($key, $value);
            }
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $json = $params->toString();

        $db->setQuery(
            $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote(self::COMPONENT))
                ->bind(':params', $json)
        )->execute();

        self::$memo = $params;
        self::cleanCacheGroup('_system');
    }

    /**
     * Clean one cache group in every place Joomla may keep it: the site cache
     * is /cache on Joomla 5 and administrator/cache on Joomla 6, plus
     * cache_path when it's set.
     *
     * @param   string  $group  Cache group, e.g. `_system` or `com_plugins`.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function cleanCacheGroup(string $group): void
    {
        $bases = array_unique(array_filter([
            (string) Factory::getApplication()->get('cache_path', ''),
            JPATH_SITE . '/cache',
            JPATH_ADMINISTRATOR . '/cache',
        ]));

        foreach ($bases as $base) {
            if (!is_dir($base)) {
                continue;
            }

            try {
                Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                    ->createCacheController('callback', ['defaultgroup' => $group, 'cachebase' => $base])
                    ->clean($group);
            } catch (\Throwable $e) {
                // A stale cache only delays the change; the database is already right.
            }
        }
    }
}
