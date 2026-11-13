<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\AccountHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\LogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\SupportHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\TaskHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\TemplatesHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\UpdateRunner;
use Cybersalt\Component\Csextensionmanager\Administrator\Model\ExtensionsModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * The landing page: where things stand, what needs doing, and the way into
 * Extensions, Templates, Health Check and History.
 *
 * @since  0.1.0
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    public $catalog = [];

    /** @var array<string, array<string, mixed>> */
    public $items = [];

    /** @var array<string, array<string, mixed>> Updates and half-installed extensions. */
    public $attention = [];

    /** @var array<string, int> */
    public $counts = [];

    /** @var integer */
    public $issueCount = 0;

    /** @var array<string, mixed> */
    public $account = [];

    /** @var boolean */
    public $canInstall = false;

    /** @var boolean */
    public $canConfigure = false;

    /** @var boolean */
    public $emailsOn = false;

    /** @var array<string, mixed> TemplatesHelper::get(). */
    public $templates = [];

    /** @var array<int, array<string, mixed>> A few templates for the dashboard strip. */
    public $featured = [];

    /** @var array<int, object> Latest history rows. */
    public $recent = [];

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
        $this->issueCount   = $model->getIssueCount();
        $this->account      = AccountHelper::getState();
        $this->canInstall   = PermissionHelper::canInstall();
        $this->canConfigure = PermissionHelper::canConfigure();
        $this->emailsOn     = TaskHelper::isEnabled();
        $this->templates    = TemplatesHelper::get();
        $this->recent       = LogHelper::recent(5);
        $this->attention    = array_filter(
            $this->items,
            static fn (array $i): bool => \in_array($i['status'], [StateHelper::STATUS_UPDATE, StateHelper::STATUS_BROKEN], true)
        );

        // Four templates that change once a day, the same for everyone that day.
        $all = TemplatesHelper::flatten($this->templates['brands']);
        mt_srand((int) gmdate('Ymd'));
        shuffle($all);
        mt_srand();
        $this->featured = \array_slice($all, 0, 4);

        $this->addToolbar();
        $this->getDocument()->setTitle(Text::_('COM_CSEXTENSIONMANAGER_TITLE_DASHBOARD'));

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

        ToolbarHelper::title(Text::_('COM_CSEXTENSIONMANAGER_TITLE_DASHBOARD'), 'puzzle-piece');

        $toolbar->linkButton('csem-refresh', 'COM_CSEXTENSIONMANAGER_BTN_REFRESH')
            ->url(Route::_('index.php?option=com_csextensionmanager&task=extensions.refresh&return=dashboard&' . Session::getFormToken() . '=1', false))
            ->icon('icon-loop')
            ->buttonClass('btn btn-secondary csem-busy-link');

        if ($this->canConfigure) {
            $toolbar->preferences('com_csextensionmanager');
        }

        $toolbar->help('COM_CSEXTENSIONMANAGER_HELP', false, SupportHelper::REPO_URL);
    }
}
