<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

/**
 * Component class for Cybersalt Extension Manager.
 *
 * @since  0.1.0
 */
final class CsextensionmanagerComponent extends MVCComponent implements BootableExtensionInterface
{
    /**
     * Nothing to boot yet.
     *
     * @param   ContainerInterface  $container  The container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function boot(ContainerInterface $container): void
    {
    }
}
