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
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * The account card: activate with an email, re-check, deactivate, and push
 * the download ID onto every Cybersalt update site.
 *
 * @since  0.1.0
 */
final class AccountController extends BaseController
{
    /**
     * POST: email.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function activate(): void
    {
        $this->checkToken();
        PermissionHelper::requireConfigure();

        $result = AccountHelper::activate($this->input->post->getString('email', ''));

        // Pro items may unlock: the feeds now answer for this ID.
        if ($result['ok']) {
            CatalogHelper::clear();
        }

        $this->setMessage($result['message'], $result['ok'] ? 'message' : 'error');
        $this->back();
    }

    /**
     * @return  void
     *
     * @since   0.1.0
     */
    public function verify(): void
    {
        $this->checkToken();
        PermissionHelper::requireConfigure();

        $result = AccountHelper::verify(true);
        $state  = $result['state'] !== '' ? Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_' . strtoupper($result['state'])) : '';

        $this->setMessage(
            $result['ok']
                ? Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_VERIFIED', $state)
                : ($result['message'] !== '' ? $result['message'] : Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_VERIFY_FAILED_GENERIC')),
            $result['ok'] ? 'message' : 'warning'
        );

        CatalogHelper::clear();
        $this->back();
    }

    /**
     * @return  void
     *
     * @since   0.1.0
     */
    public function deactivate(): void
    {
        $this->checkToken();
        PermissionHelper::requireConfigure();

        AccountHelper::deactivate();
        CatalogHelper::clear();

        $this->setMessage(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_DEACTIVATED'));
        $this->back();
    }

    /**
     * @return  void
     *
     * @since   0.1.0
     */
    public function sync(): void
    {
        $this->checkToken();
        PermissionHelper::requireConfigure();

        $changed = AccountHelper::syncUpdateSites();

        $this->setMessage(Text::plural('COM_CSEXTENSIONMANAGER_ACCOUNT_SYNCED', $changed));
        $this->back();
    }

    /**
     * @return  void
     *
     * @since   0.1.0
     */
    private function back(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_csextensionmanager&view=extensions', false) . '#cs-account');
    }
}
