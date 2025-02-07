<?php
/**
 * @package     joomLab.Plugin
 * @subpackage  User.Vklogin
 *
 * @copyright   (C) 2025 Alexand Novikov. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use joomLab\Plugin\User\Vklogin\Extension\Vklogin;

return new class () implements ServiceProviderInterface {
   public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {

                $config = (array) PluginHelper::getPlugin('user', 'vklogin');
                $subject = $container->get(DispatcherInterface::class);
                $app = Factory::getApplication();

                $plugin = new Vklogin($subject, $config);
                $plugin->setApplication($app);

                return $plugin;
            }
        );
    }

};