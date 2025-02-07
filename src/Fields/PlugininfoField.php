<?php
/**
 * @package    WT JShopping cart save
 * @version       1.1.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2024 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace joomLab\Plugin\User\Vklogin\Fields;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\NoteField;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;


class PlugininfoField extends NoteField
{

    protected $type = 'Plugininfo';

    /**
     * Method to get the field input markup for a spacer.
     * The spacer does not have accept input.
     *
     * @return  string  The field input markup.
     *
     * @since   1.7.0
     */
    protected function getInput()
    {
        return ' ';
    }

    /**
     * @return  string  The field label markup.
     *
     * @since   1.7.0
     */
    protected function getLabel(): string
    {
        $data           = $this->form->getData();
        $element        = $data->get('element');
       $folder         = $data->get('folder');
        $wt_plugin_info = simplexml_load_file(JPATH_SITE . "/plugins/" . $folder . "/" . $element . "/" . $element . ".xml");

        /* @var $doc \Joomla\CMS\Document\Document */
        $doc = Factory::getApplication()->getDocument();
        $doc->getWebAssetManager()->addInlineStyle('
            #web_tolk_link {
			text-align: center;
			}
			#web_tolk_link::before{
				content: "";
			}
        ');

        return '</div>
		<div class="card container shadow-sm w-100 p-0">
			<div class="wt-b24-plugin-info row">
				<div class="col-2 d-flex justify-content-center align-items-center">
				<a class="preloader_link" href="https://joomlab.ru" title="Расширения для CMS Joomla">
					<img style="width:150px;" src="https://joomlab.ru/images/logo_joomlab.png" alt="joomlab">
				</a>
				</div>
				<div class="col-10">
					<div class="card-header bg-white p-1">
						<span class="badge bg-success">v.' . $wt_plugin_info->version . '</span>
					</div>
					<div class="card-body">
						<div>' . Text::_("PLG_USER_" . strtoupper($element) . "_DESC") .'</div>
						<div><a href="https://t.me/pro_portal" target="_blank">@pro_portal</a></div>
					</div>
				</div>
			</div>
		</div><div>
		';
    }

    /**
     * Method to get the field title.
     *
     * @return  string  The field title.
     *
     * @since   1.7.0
     */
    protected function getTitle()
    {
        return $this->getLabel();
    }
}