<?php

/**
 * @package    plg_installer_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Plugin\Installer\Csextensionmanager\Extension;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\AccountHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StoreClient;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Safety net for Joomla's own updater: when a Cybersalt download or update
 * feed is requested without the site's download ID (e.g. after "Rebuild
 * update sites" wiped extra_query), add it.
 *
 * The manager also writes the ID onto every Cybersalt update site, so this
 * normally has nothing to do. It never touches URLs that aren't the
 * Cybersalt store's, and never replaces an ID that's already there.
 *
 * @since  0.1.0
 */
final class Csextensionmanager extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return  array<string, string>
     *
     * @since   0.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onInstallerBeforePackageDownload'    => 'onBeforePackageDownload',
            // Joomla 5.3+
            'onInstallerBeforeUpdateSiteDownload' => 'onBeforeUpdateSiteDownload',
        ];
    }

    /**
     * Paid package downloads (task=api.download).
     *
     * @param   EventInterface  $event  BeforePackageDownloadEvent.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function onBeforePackageDownload(EventInterface $event): void
    {
        $this->addDlid($event, ['api.download']);
    }

    /**
     * Update feeds (task=api.updatexml), so the store can answer for this
     * site and put the ID in the download URL it hands back.
     *
     * @param   EventInterface  $event  BeforeUpdateSiteDownloadEvent.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function onBeforeUpdateSiteDownload(EventInterface $event): void
    {
        $this->addDlid($event, ['api.updatexml']);
    }

    /**
     * @param   EventInterface  $event  Event with a `url` argument.
     * @param   string[]        $tasks  Store tasks this applies to.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function addDlid(EventInterface $event, array $tasks): void
    {
        // The component may have been uninstalled while this plugin stayed.
        if (!class_exists(AccountHelper::class) || !class_exists(StoreClient::class)) {
            return;
        }

        try {
            $url = method_exists($event, 'getUrl') ? (string) $event->getUrl() : (string) $event->getArgument('url', '');

            if ($url === '' || !StoreClient::isStoreUrl($url, $tasks) || preg_match('/[?&]dlid=[^&]+/', $url)) {
                return;
            }

            $dlid = AccountHelper::getDlid();

            if ($dlid === '') {
                return;
            }

            $url = StoreClient::withQueryParam($url, 'dlid', $dlid);

            if (method_exists($event, 'updateUrl')) {
                $event->updateUrl($url);
            } else {
                $event->setArgument('url', $url);
            }
        } catch (\Throwable $e) {
            // Never break an install over this; the store will just refuse a paid download.
        }
    }
}
