<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default admin controller: dashboard, extensions, templates, health and history views.
 *
 * @since  0.1.0
 */
final class DisplayController extends BaseController
{
    /**
     * @var    string
     * @since  0.1.0
     */
    protected $default_view = 'dashboard';

    /**
     * @param   boolean  $cachable   Unused.
     * @param   array    $urlparams  Unused.
     *
     * @return  static
     *
     * @since   0.1.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        PermissionHelper::requireView();

        $view = $this->input->getCmd('view', $this->default_view);

        if (!\in_array($view, ['dashboard', 'extensions', 'templates', 'health', 'logs'], true)) {
            $view = $this->default_view;
            $this->input->set('view', $view);
        }

        // The dashboard has no model of its own; it summarises the catalog.
        if ($view === 'dashboard') {
            $this->getView($view, 'html', '', ['base_path' => $this->basePath, 'layout' => 'default'])
                ->setModel($this->getModel('Extensions'), true);
        }

        return parent::display(false, $urlparams);
    }
}
