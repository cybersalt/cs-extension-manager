<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\View\Templates;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\SupportHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\TemplatesHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Templates from Energize Themes and VirtueMart Templates, with topic,
 * brand, Joomla version and shop filters. Nothing here installs anything.
 *
 * @since  0.1.0
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> TemplatesHelper::get(). */
    public $templates = [];

    /** @var array<int, array<string, mixed>> Every template, sorted by name. */
    public $all = [];

    /** @var array<string, int> Topic slug => number of templates. */
    public $topicCounts = [];

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

        $this->templates = TemplatesHelper::get();
        $this->all       = TemplatesHelper::flatten($this->templates['brands']);

        foreach ($this->all as $template) {
            foreach ($template['tags'] as $tag) {
                $this->topicCounts[$tag] = ($this->topicCounts[$tag] ?? 0) + 1;
            }
        }

        $this->addToolbar();
        $this->getDocument()->setTitle(Text::_('COM_CSEXTENSIONMANAGER_TITLE_TEMPLATES'));

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

        ToolbarHelper::title(Text::_('COM_CSEXTENSIONMANAGER_TITLE_TEMPLATES'), 'paint-brush');

        $toolbar->linkButton('csem-refresh', 'COM_CSEXTENSIONMANAGER_BTN_REFRESH')
            ->url(Route::_('index.php?option=com_csextensionmanager&task=extensions.refresh&return=templates&' . Session::getFormToken() . '=1', false))
            ->icon('icon-loop')
            ->buttonClass('btn btn-secondary csem-busy-link');

        if (PermissionHelper::canConfigure()) {
            $toolbar->preferences('com_csextensionmanager');
        }

        $toolbar->help('COM_CSEXTENSIONMANAGER_HELP', false, SupportHelper::REPO_URL);
    }
}
