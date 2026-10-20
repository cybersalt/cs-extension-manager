<?php

/**
 * @package    plg_installer_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Cybersalt\Plugin\Installer\Csextensionmanager\Extension\Csextensionmanager;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container) {
                $plugin = new Csextensionmanager((array) PluginHelper::getPlugin('installer', 'csextensionmanager'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
