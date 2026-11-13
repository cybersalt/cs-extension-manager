<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\View\Extensions;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\SupportHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\TaskHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\UpdateRunner;
use Cybersalt\Component\Csextensionmanager\Administrator\Model\ExtensionsModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * The catalog: every Cybersalt extension, what's installed, what's new.
 *
 * @since  0.1.0
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    public $catalog = [];

    /** @var array<string, array<string, mixed>> */
    public $items = [];

    /** @var array<string, int> */
    public $counts = [];

    /** @var integer */
    public $issueCount = 0;

    /** @var string success (none), warning or danger (a problem). */
    public $issueTone = 'success';

    /** @var boolean */
    public $canInstall = false;

    /** @var boolean */
    public $canConfigure = false;

    /** @var array<string, mixed>|null */
    public $selfUpdate = null;

    /** @var boolean The scheduled release check (and its emails) is switched on. */
    public $emailsOn = false;

    /** @var string Who gets the release emails. */
    public $emailRecipients = '';

    /** @var array<string, mixed> The item the row sub-template is rendering. */
    public $currentItem = [];

    /**
     * @param   string|null  $tpl  Template.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function display($tpl = null): void
    {
        PermissionHelper::requireView();

        /** @var ExtensionsModel $model */
        $model = $this->getModel();

        $this->catalog      = $model->getCatalog();
        $this->items        = $model->getItems();
        $this->counts       = $model->getCounts();
        $issues             = $model->getIssueSummary();
        $this->issueCount   = $issues['count'];
        $this->issueTone    = $issues['tone'];
        $this->canInstall   = PermissionHelper::canInstall();
        $this->canConfigure = PermissionHelper::canConfigure();
        $this->emailsOn     = TaskHelper::isEnabled();

        if ($this->emailsOn) {
            $this->emailRecipients = implode(', ', UpdateRunner::recipients());
        }

        foreach ($this->items as $item) {
            if (!empty($item['self']) && $item['status'] === 'update') {
                $this->selfUpdate = $item;
            }
        }

        $this->addToolbar();
        $this->getDocument()->setTitle(Text::_('COM_CSEXTENSIONMANAGER_TITLE_EXTENSIONS'));

        parent::display($tpl);
    }

    /**
     * @return  void
     *
     * @since   0.1.0
     */
    private function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_CSEXTENSIONMANAGER_TITLE_EXTENSIONS'), 'puzzle-piece');

        $toolbar->linkButton('csem-refresh', 'COM_CSEXTENSIONMANAGER_BTN_REFRESH')
            ->url(Route::_('index.php?option=com_csextensionmanager&task=extensions.refresh&return=extensions&' . Session::getFormToken() . '=1', false))
            ->icon('icon-loop')
            ->buttonClass('btn btn-secondary csem-busy-link');

        if ($this->canInstall && $this->counts['update'] > 0) {
            $toolbar->customButton('csem-update-all')
                ->html(
                    '<button type="button" class="btn btn-success" data-csem-update-all>'
                    . '<span class="icon-upload" aria-hidden="true"></span> '
                    . htmlspecialchars(Text::plural('COM_CSEXTENSIONMANAGER_BTN_UPDATE_ALL_N', $this->counts['update']), ENT_QUOTES, 'UTF-8')
                    . '</button>'
                );
        }

        if ($this->canConfigure) {
            $toolbar->preferences('com_csextensionmanager');
        }

        $toolbar->help('COM_CSEXTENSIONMANAGER_HELP', false, SupportHelper::REPO_URL);
    }
}
