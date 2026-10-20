<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\AccountHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\CatalogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\InstallHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Catalog actions. Install, uninstall, toggle and changelog answer JSON for
 * the browser-side queue; refresh is a normal redirect.
 *
 * @since  0.1.0
 */
final class ExtensionsController extends BaseController
{
    use JsonResponseTrait;

    /**
     * Install, update, reinstall or downgrade one item.
     * POST: key, version (optional).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function install(): void
    {
        $this->guardJson([PermissionHelper::class, 'requireInstall']);
        @set_time_limit(300);

        $item = $this->requestedItem();

        if ($item === null) {
            $this->sendJson(false, Text::_('COM_CSEXTENSIONMANAGER_UNKNOWN_ITEM'), [], 404);
        }

        if ($item['requires_missing'] !== []) {
            $parent = CatalogHelper::find((string) $item['requires_missing'][0]);
            $this->sendJson(false, Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_NEEDS_PARENT', $item['title'], $parent['title'] ?? $item['requires_missing'][0]));
        }

        $version = trim($this->input->post->getString('version', ''));
        $result  = InstallHelper::install($item, $version);

        $this->sendJson($result['ok'], $result['message'], [
            'action'   => $result['action'],
            'from'     => $result['from'],
            'to'       => $result['to'],
            'messages' => $result['messages'],
            'self'     => !empty($item['self']),
        ]);
    }

    /**
     * Uninstall one item. POST: key.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function uninstall(): void
    {
        $this->guardJson([PermissionHelper::class, 'requireInstall']);

        $item = $this->requestedItem();

        if ($item === null) {
            $this->sendJson(false, Text::_('COM_CSEXTENSIONMANAGER_UNKNOWN_ITEM'), [], 404);
        }

        // Add-ons installed for this one would be left without their parent.
        if ($item['element'] === CatalogHelper::ADDON_PARENT_ELEMENT) {
            $dependants = [];

            foreach (StateHelper::enrich(CatalogHelper::get()['items']) as $other) {
                if ($other['installed'] && \in_array($item['key'], (array) $other['requires'], true)) {
                    $dependants[] = $other['title'];
                }
            }

            if ($dependants !== [] && !$this->input->post->getBool('force', false)) {
                $this->sendJson(false, Text::sprintf('COM_CSEXTENSIONMANAGER_UNINSTALL_HAS_DEPENDANTS', $item['title'], implode(', ', $dependants)), ['dependants' => $dependants, 'needs_confirm' => true]);
            }
        }

        $result = InstallHelper::uninstall($item);

        $this->sendJson($result['ok'], $result['message'], ['messages' => $result['messages']]);
    }

    /**
     * Enable or disable an item or a part of its package. POST: key, id, enabled.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function toggle(): void
    {
        $this->guardJson([PermissionHelper::class, 'requireInstall']);

        $item = $this->requestedItem();

        if ($item === null || !$item['installed']) {
            $this->sendJson(false, Text::_('COM_CSEXTENSIONMANAGER_UNKNOWN_ITEM'), [], 404);
        }

        $result = InstallHelper::setEnabled($item, $this->input->post->getInt('id', 0), $this->input->post->getBool('enabled', false));

        $this->sendJson($result['ok'], $result['message']);
    }

    /**
     * Changelog for one item. POST: key.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function changelog(): void
    {
        $this->guardJson([PermissionHelper::class, 'requireView']);

        $item = $this->requestedItem();

        if ($item === null) {
            $this->sendJson(false, Text::_('COM_CSEXTENSIONMANAGER_UNKNOWN_ITEM'), [], 404);
        }

        $versions = InstallHelper::changelog($item);

        if ($versions === null) {
            $this->sendJson(false, Text::_('COM_CSEXTENSIONMANAGER_CHANGELOG_UNAVAILABLE'));
        }

        $this->sendJson(true, '', [
            'title'     => $item['title'],
            'installed' => $item['installed_version'],
            'latest'    => $item['latest_version'],
            'versions'  => $versions,
            'can_install' => PermissionHelper::canInstall() && $item['entitled'],
        ]);
    }

    /**
     * Home Dashboard quickicon count. GET with token, answered in the shape
     * mod_quickicon's script expects ({data: {amount, name, sronly}}). Runs
     * after the dashboard has rendered, so a stale catalog can be refreshed
     * here without slowing the page down.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function quickicon(): void
    {
        if (!$this->checkToken('get', false)) {
            $this->sendJson(false, Text::_('JINVALID_TOKEN_NOTICE'), [], 403);
        }

        try {
            PermissionHelper::requireView();
        } catch (\Throwable $e) {
            $this->sendJson(false, Text::_('JERROR_ALERTNOAUTHOR'), [], 403);
        }

        $this->app->getLanguage()->load('com_csextensionmanager', JPATH_ADMINISTRATOR);

        $counts = StateHelper::counts(StateHelper::enrich(CatalogHelper::get()['items']));
        $amount = $counts['update'] + $counts['broken'];

        $this->sendJson(true, '', [
            'amount' => $amount,
            'name'   => $amount > 0
                ? Text::plural('COM_CSEXTENSIONMANAGER_QUICKICON_UPDATES', $amount)
                : Text::_('COM_CSEXTENSIONMANAGER_QUICKICON_UPTODATE'),
            'sronly' => Text::plural('COM_CSEXTENSIONMANAGER_QUICKICON_SRONLY', $amount),
        ]);
    }

    /**
     * Fetch the catalog again and re-check the download ID. GET with token.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function refresh(): void
    {
        $this->checkToken('get');
        PermissionHelper::requireView();

        $catalog = CatalogHelper::get(true);

        if (AccountHelper::getDlid() !== '') {
            AccountHelper::verify(true);
        }

        if ($catalog['errors'] !== []) {
            $this->setMessage(Text::sprintf('COM_CSEXTENSIONMANAGER_REFRESH_PARTIAL', implode('; ', \array_slice($catalog['errors'], 0, 3))), 'warning');
        } else {
            $this->setMessage(Text::plural('COM_CSEXTENSIONMANAGER_REFRESH_OK', \count($catalog['items'])));
        }

        $this->setRedirect(Route::_('index.php?option=com_csextensionmanager&view=extensions', false));
    }
}
