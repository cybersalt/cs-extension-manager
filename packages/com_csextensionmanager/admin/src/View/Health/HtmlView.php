<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\View\Health;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\CatalogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\HealthHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\SupportHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Health checks for the Cybersalt extensions on this site.
 *
 * @since  0.1.0
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array<int, array<string, mixed>> */
    public $issues = [];

    /** @var array<string, int> */
    public $severityCounts = [];

    /** @var integer */
    public $installedCount = 0;

    /** @var integer */
    public $safeFixCount = 0;

    /** @var boolean */
    public $canFix = false;

    /** @var boolean */
    public $canInstall = false;

    /** @var array<string, mixed> */
    public $catalog = [];

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

        $this->catalog = CatalogHelper::get();
        $items         = StateHelper::enrich($this->catalog['items']);
        $this->issues  = HealthHelper::check($items);
        $this->canFix  = PermissionHelper::canFix();
        $this->canInstall = PermissionHelper::canInstall();

        $this->installedCount = \count(array_filter($items, static fn (array $i): bool => (bool) $i['installed']));
        $this->severityCounts = ['danger' => 0, 'warning' => 0, 'info' => 0];

        foreach ($this->issues as $issue) {
            $this->severityCounts[$issue['severity']]++;

            if (\in_array($issue['fix'], HealthHelper::SAFE_FIXES, true)) {
                $this->safeFixCount++;
            }
        }

        $this->addToolbar();
        $this->getDocument()->setTitle(Text::_('COM_CSEXTENSIONMANAGER_TITLE_HEALTH'));

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

        ToolbarHelper::title(Text::_('COM_CSEXTENSIONMANAGER_TITLE_HEALTH'), 'heart');

        $toolbar->linkButton('csem-back', 'COM_CSEXTENSIONMANAGER_SUBMENU_EXTENSIONS')
            ->url(Route::_('index.php?option=com_csextensionmanager&view=extensions', false))
            ->icon('icon-puzzle-piece')
            ->buttonClass('btn btn-secondary');

        $toolbar->linkButton('csem-recheck', 'COM_CSEXTENSIONMANAGER_BTN_RECHECK')
            ->url(Route::_('index.php?option=com_csextensionmanager&task=health.recheck&' . Session::getFormToken() . '=1', false))
            ->icon('icon-loop')
            ->buttonClass('btn btn-secondary csem-busy-link');

        if (PermissionHelper::canConfigure()) {
            $toolbar->preferences('com_csextensionmanager');
        }

        $toolbar->help('COM_CSEXTENSIONMANAGER_HELP', false, SupportHelper::REPO_URL);
    }
}
