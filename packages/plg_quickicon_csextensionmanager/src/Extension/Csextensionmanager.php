<?php

/**
 * @package    plg_quickicon_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Plugin\Quickicon\Csextensionmanager\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

/**
 * "N Cybersalt updates" on the Home Dashboard, next to Joomla's own update
 * icons. The count is fetched after the page loads (mod_quickicon's
 * ajaxurl), so a slow store never slows the dashboard.
 *
 * @since  0.1.0
 */
final class Csextensionmanager extends CMSPlugin implements SubscriberInterface
{
    /**
     * @var    boolean
     * @since  0.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return  array<string, string>
     *
     * @since   0.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return ['onGetIcons' => 'onGetIcons'];
    }

    /**
     * @param   EventInterface  $event  QuickIconsEvent.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function onGetIcons(EventInterface $event): void
    {
        $context = method_exists($event, 'getContext') ? (string) $event->getContext() : (string) $event->getArgument('context', '');
        $user    = $this->getApplication()->getIdentity();

        if ($context !== (string) $this->params->get('context', 'update_quickicon') || $user === null) {
            return;
        }

        if (!$user->authorise('csextensionmanager.view', 'com_csextensionmanager') && !$user->authorise('core.admin')) {
            return;
        }

        $token = Session::getFormToken() . '=1';

        $result   = $event->getArgument('result', []);
        $result[] = [
            [
                'link'    => 'index.php?option=com_csextensionmanager&view=extensions',
                'image'   => 'icon-puzzle-piece',
                'name'    => 'PLG_QUICKICON_CSEXTENSIONMANAGER_CHECKING',
                'ajaxurl' => Uri::base() . 'index.php?option=com_csextensionmanager&task=extensions.quickicon&' . $token,
                'id'      => 'plg_quickicon_csextensionmanager',
                'group'   => 'MOD_QUICKICON_MAINTENANCE',
            ],
        ];

        $event->setArgument('result', $result);
    }
}
