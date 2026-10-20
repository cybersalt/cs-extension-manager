<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * One-line ACL gates for every admin controller method.
 *
 * - view    = see the catalog, health checks and history (csextensionmanager.view)
 * - install = install, update, reinstall, uninstall, enable/disable (csextensionmanager.install)
 *             AND core.manage on com_installer, so a site that has locked
 *             down its Extension Manager isn't bypassed through this one
 * - fix     = apply health-check fixes, clear the history (csextensionmanager.fix)
 * - configure = activate an account / change Options (core.options)
 *
 * Super Users pass every check through core.admin.
 *
 * @since  0.1.0
 */
final class PermissionHelper
{
    /**
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function canView(): bool
    {
        return self::authorise('csextensionmanager.view') || self::authorise('core.admin');
    }

    /**
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function canInstall(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user === null) {
            return false;
        }

        if ($user->authorise('core.admin')) {
            return true;
        }

        return (self::authorise('csextensionmanager.install') || self::authorise('core.admin'))
            && (bool) $user->authorise('core.manage', 'com_installer');
    }

    /**
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function canFix(): bool
    {
        return self::authorise('csextensionmanager.fix') || self::authorise('core.admin');
    }

    /**
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function canConfigure(): bool
    {
        return self::authorise('core.options') || self::authorise('core.admin');
    }

    /**
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   0.1.0
     */
    public static function requireView(): void
    {
        if (!self::canView()) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   0.1.0
     */
    public static function requireInstall(): void
    {
        if (!self::canInstall()) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   0.1.0
     */
    public static function requireFix(): void
    {
        if (!self::canFix()) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   0.1.0
     */
    public static function requireConfigure(): void
    {
        if (!self::canConfigure()) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * @param   string  $action  ACL action.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    private static function authorise(string $action): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && (bool) $user->authorise($action, 'com_csextensionmanager');
    }
}
