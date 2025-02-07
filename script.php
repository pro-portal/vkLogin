<?php
/**
 * @package     joomLab.Plugin
 * @subpackage  VkLogin
 *
 * @copyright   (C) 2025 Alexandr Novikov. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Installer\Installer;

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(InstallerScriptInterface::class, new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
			/**
			 * The application object
			 *
			 * @var  AdministratorApplication
			 *
			 * @since  __DEPLOY_VERSION__
			 */
			protected AdministratorApplication $app;

			/**
			 * The Database object.
			 *
			 * @var   DatabaseDriver
			 *
			 * @since  __DEPLOY_VERSION__
			 */
			protected DatabaseDriver $db;

			/**
			 * Minimum Joomla version required to install the extension.
			 *
			 * @var  string
			 *
			 * @since  __DEPLOY_VERSION__
			 */
			protected string $minimumJoomla = '5.0';

			/**
			 * Minimum PHP version required to install the extension.
			 *
			 * @var  string
			 *
			 * @since  __DEPLOY_VERSION__
			 */
			protected string $minimumPhp = '8.1';

			/**
			 * Constructor.
			 *
			 * @param   AdministratorApplication  $app  The application object.
			 *
			 * @since __DEPLOY_VERSION__
			 */
			public function __construct(AdministratorApplication $app)
			{
				$this->app = $app;
				$this->db  = Factory::getContainer()->get('DatabaseDriver');
			}

			/**
			 * Function called after the extension is installed.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   __DEPLOY_VERSION__
			 */
			public function install(InstallerAdapter $adapter): bool
			{
				$this->enablePlugin($adapter);

				return true;
			}

			/**
			 * Function called after the extension is updated.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   __DEPLOY_VERSION__
			 */
			public function update(InstallerAdapter $adapter): bool
			{
				// Refresh media version
				(new Version())->refreshMediaVersion();

				return true;
			}

			/**
			 * Function called after the extension is uninstalled.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   __DEPLOY_VERSION__
			 */
			public function uninstall(InstallerAdapter $adapter): bool
			{
				return true;
			}

			/**
			 * Function called before extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   __DEPLOY_VERSION__
			 */
			public function preflight(string $type, InstallerAdapter $adapter): bool
			{
				// Check compatible
				if (!$this->checkCompatible())
				{
					return false;
				}

				return true;
			}

			/**
			 * Function called after extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   __DEPLOY_VERSION__
			 */
            public function postflight(string $type, $parent): bool
            {
                $this->parseLayouts($parent->getParent()->getManifest()->layouts, $parent->getParent());

                $wt_plugin_info = simplexml_load_file(JPATH_PLUGINS . '/user/vklogin/vklogin.xml');
                $html = 			'<div class="wt-b24-plugin-info row">
				<div class="col-2 d-flex justify-content-center align-items-center">
				<a class="preloader_link" href="https://joomlab.ru" title="Расширения для CMS Joomla">
					<img width="150" src="https://joomlab.ru/images/logo_joomlab.png" alt="joomlab">
				</a>
				</div>
				<div class="col-10">
					<div class="p-1">
						<span class="badge bg-success">v.' . $wt_plugin_info->version . '</span>
					</div>
					<div class="card-body">
						Плагин авторизации через VK ID для CMS Joomla
					</div>
				</div>
			</div>';
                $this->app->enqueueMessage($html, 'info');
                return true;
            }

			/**
			 * Method to check compatible.
			 *
			 * @return  bool True on success, False on failure.
			 *
			 * @throws  \Exception
			 *
			 * @since  __DEPLOY_VERSION__
			 */
			protected function checkCompatible(): bool
			{
				$app = Factory::getApplication();

				// Check joomla version
				if (!(new Version())->isCompatible($this->minimumJoomla))
				{
					$app->enqueueMessage(Text::sprintf('PLG_EDITORS_JS_WRONG_JOOMLA', $this->minimumJoomla),
						'error');

					return false;
				}

				// Check PHP
				if (!(version_compare(PHP_VERSION, $this->minimumPhp) >= 0))
				{
					$app->enqueueMessage(Text::sprintf('PLG_CONTENT_LIKE_WRONG_PHP', $this->minimumPhp),
						'error');

					return false;
				}

				return true;
			}

			/**
			 * Enable plugin after installation.
			 *
			 * @param   InstallerAdapter  $adapter  Parent object calling object.
			 *
			 * @since  1.0.0
			 */
			protected function enablePlugin(InstallerAdapter $adapter)
			{
				// Prepare plugin object
                $domain = 'https://'.Uri::getInstance()->getHost();
                $params = ['redirectUrl' => $domain];
                $params = json_encode($params);
				$plugin          = new \stdClass();
				$plugin->type    = 'plugin';
				$plugin->element = $adapter->getElement();
				$plugin->folder  = (string) $adapter->getParent()->manifest->attributes()['group'];
				$plugin->params = $params;

				// Update record
				$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);


			}

            public function parseLayouts(SimpleXMLElement $element = null, Installer $installer = null): bool
            {
                if (!$element || !count($element->children()))
                {
                    return false;
                }

                // Get destination
                $folder      = ((string) $element->attributes()->destination) ? '/' . $element->attributes()->destination : null;
                $destination = Path::clean(JPATH_ROOT . '/layouts' . $folder);

                // Get source
                $folder = (string) $element->attributes()->folder;
                $source = ($folder && file_exists($installer->getPath('source') . '/' . $folder))
                    ? $installer->getPath('source') . '/' . $folder : $installer->getPath('source');

                // Prepare files
                $copyFiles = [];
                foreach ($element->children() as $file)
                {
                    $path['src']  = Path::clean($source . '/' . $file);
                    $path['dest'] = Path::clean($destination . '/' . $file);

                    // Is this path a file or folder?
                    $path['type'] = $file->getName() === 'folder' ? 'folder' : 'file';
                    if (basename($path['dest']) !== $path['dest'])
                    {
                        $newdir = dirname($path['dest']);
                        if (!Folder::create($newdir))
                        {
                            Log::add(Text::sprintf('JLIB_INSTALLER_ERROR_CREATE_DIRECTORY', $newdir), Log::WARNING, 'jerror');

                            return false;
                        }
                    }

                    $copyFiles[] = $path;
                }
                return $installer->copyFiles($copyFiles, true);

            }

		});
	}
};