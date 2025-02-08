<?php
/**
 * @package     joomLab.Plugin
 * @subpackage  User.Vklogin
 *
 * @copyright   (C) 2025 Alexand Novikov. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace joomLab\Plugin\User\Vklogin\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\Event\Event;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Form\Form;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\User\User;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Component\Fields\Administrator\Model\FieldModel;
use Joomla\Utilities\ArrayHelper;


class Vklogin extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private $delete_name = 0;
    private $delete_username = 0;
    private $delete_password1 = 0;
    private $delete_password2 = 0;

    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
        $this->delete_username = (int) $this->params->get('delete_username', 0);
        $this->delete_password1 = (int) $this->params->get('delete_password1', 0);
        $this->delete_password2 = (int) $this->params->get('delete_password2', 0);
    }

    public function getAuthenticationResponseObject()
    {
        // Force the class autoloader to load the Authentication class
        class_exists(Authentication::class);
        return new AuthenticationResponse();
    }

    //добавляем кнопку
    public function onUserLoginButtons(Event $event): void
    {
        $all_btn = [];
        $vk_btn = [];

        if(!empty($event->getArguments()['result'])) {
            $all_btn = $event->getArguments()['result'];
        }
        [$form] = $event->getArguments();
        if($this->getApplication()->isClient('site')) {
            if ($this->params->get('type_btn') == 'button') {
                $vk_btn[] = [
                    'label' => 'PLG_USER_VK_LOGIN_LABEL',
                    'tooltip' => 'PLG_USER_VK_LOGIN_DESC',
                    'icon' => 'joomlab_vk_image',
                    'class' => 'vk_mini_btn',
                    'id' => 'vk_' . $form,
                    'onclick' => 'redirectToVKAuth()'
                ];
            }

        }
        $buttons = array_merge($all_btn, $vk_btn);
        $event->addResult($buttons);
    }

    //добавляем скрипт VK
    public function onAfterRenderModule(Event $event)
    {
        $module = $event->getModule()->module;
        if($module != 'mod_login') return;
        $app = $this->getApplication();
        $view = $app->getInput('view');
        //$code_verifier = UserHelper::genRandomPassword(55);
        $code_verifier = 'sdfvdfvwegerwehwet455555asafgag';
        $session = Factory::getSession()->set('code_verifier', $code_verifier);
        $content = $event->getModule()->content;
        $content .= LayoutHelper::render('plugins.user.vklogin.vk', ['params' => $this->params, 'code_verifier' => $code_verifier, 'view' => $view]);

        $event->getModule()->content = $content;
    }
    public function onAjaxVklogin(Event $event) {
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        $app = $this->getApplication();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $social_name = $app->getInput()->json->getArray()['social_name'];
        $task = $app->getInput('social', '', 'string');

        include __DIR__ . '/ResponseVK.php';

        if(!empty($social_id)) {
            //записываем в сессию
            $session_user_id = Factory::getSession()->set('social_id', $social_id);
            $session_avatar = Factory::getSession()->set('avatar', $avatar);
            $session_social_name = Factory::getSession()->set('social_name', $social_name);

            //проверяем пользователя
            $db->setQuery("SELECT `user_id` FROM `#__joomlab_soc_login` WHERE `social_id` = '{$social_id}' AND `social_name` = '{$social_name}'");
            $user_id = $db->loadResult();
            if (!$user_id) { //если пользователя нет - регистрируем
                $url = Route::link(
                    'site',
                    'index.php?option=com_users&view=registration' .
                    '&name=' . urlencode($social_user_name) .
                    '&email=' . urlencode($social_user_email) .
                    '&checkform=1' .
                    //проверяем поля и добавляем парамеры
                    '&phone=' . urlencode($social_user_phone)
                );
                $registeredData = [
                    'isNew' => true,
                    'registeredUrl' => html_entity_decode($url),
                ];

                $event->addResult(json_encode($registeredData, JSON_UNESCAPED_UNICODE));
            } else { //если пользователь есть - проверяем и авторизируем
                //меняем аватар
                $http = HttpFactory::getHttp();
                $avatarUrl = preg_replace('/&cs=\d+x\d+/', '&cs=640x640', $avatar);
                $savePath = JPATH_ROOT . '/images/avatar/' . $social_name . '_' . $social_id . '.jpg';
                $response_ava = $http->get($avatarUrl);
                if ($response_ava->code === 200) {
                    $dir = dirname($savePath);
                    if (!Folder::exists($dir)) {
                        Folder::create($dir);
                    }
                    File::write($savePath, $response_ava->body);
                }
                $user = Factory::getUser($user_id);
                if ($user->block) {
                    $message = Text::_('COM_USERS_PROFILE_USER_BLOCKED');
                    $registeredData = [
                        'isNew' => false,
                        'message' => $message
                    ];
                    $event->addResult(json_encode($registeredData, JSON_UNESCAPED_UNICODE));
                } else {
                    $registeredData = [
                        'isNew' => false,
                        'loginUrl' => ''
                    ];

                    $statusSuccess = Authentication::STATUS_SUCCESS;
                    $response = $this->getAuthenticationResponseObject();
                    $response->status = $statusSuccess;
                    $response->username = $user->username;
                    $response->fullname = $user->name;
                    $response->error_message = '';
                    $response->language = $user->getParam('language');

                    $options = [
                        'remember' => true,
                        'action' => 'core.login.site',
                    ];

                    $dispatcher = $app->getDispatcher();
                    PluginHelper::importPlugin('user');
                    $event_login = new Event('onUserLogin', [(array)$response, $options]);
                    $results = $dispatcher->dispatch('onUserLogin', $event_login);

                    $event->addResult(json_encode($registeredData, JSON_UNESCAPED_UNICODE));
                }
            }
        } else { //если запрос с ошибкой и не получен social_id
            if($app->getIdentity()->id == 0) {
                $message = Text::_('COM_USERS_PROFILE_USER_NO_RESPONSE');
                $registeredData = [
                    'isNew' => 'error',
                    'message_error' => $message
                ];

            $event->addResult(json_encode($registeredData, JSON_UNESCAPED_UNICODE));
            }
        }
    }

//подменяем форму
    public function onContentPrepareData($context, $data)
    {
        if ($context !== 'com_users.registration' and $context !== 'com_users.profile' || !is_object($data)) {
            return true;
        }
        $app = $this->getApplication();
        $temp = $app->input->get('jform', [], 'array');
        if (isset($temp['password1'])) {
            $data->password1 = $temp['password1'];
        }
        if (empty($temp['email1'])) {
            return true;
        }
        if (!isset($data->email)) {
            $data->email1 = $temp['email1'];
        }
        if ($this->delete_name) {
            $data->name = explode('@', $data->email1)[0];
        }
        if ($this->delete_username) {
            $data->username = $data->email1;
        }
        if ($this->delete_password1) {
            $data->password1 = UserHelper::genRandomPassword($this->params->get('pass_length', 8));
        }
        if ($this->delete_password2) {
            $data->password2 = $data->password1;
        }

        return true;
    }
    public function onContentPrepareForm(Form $form, $data)
    {
        if ($form->getName() !== 'com_users.registration' and $form->getName() !== 'com_users.profile') {
            return true;
        }
        if ($this->delete_username) {
            $form->removeField('username');
        }
        if ($this->delete_password1) {
            $form->removeField('password1');
        }
        if ($this->delete_password2) {
            $form->removeField('password2');
            $form->setFieldAttribute('password1', 'validate', '');
        }
        return true;
    }

//сохраняем пользователя
    public function onUserAfterSave(Event $event) {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $user_id = $event->getArguments()['subject']['id'];

        $isNew = $event->getArguments()['isNew'];
        $social_id = Factory::getSession()->get('social_id');
        $avatar = Factory::getSession()->get('avatar');
        $social_name = Factory::getSession()->get('social_name');

        if($isNew == true) {
            //обновляем таблицу
            $profile = new \stdClass();
            $profile->id = $db->insertid();
            $profile->user_id = $user_id;
            $profile->social_id = $social_id;
            $profile->social_name = $social_name;

            $result = $db->insertObject('#__joomlab_soc_login', $profile);

            //записываем аватар пользователю
            $custom_fields = FieldsHelper::getFields('com_users.user', ['id' => $user_id], true);
            $custom_fields_by_name = ArrayHelper::pivot($custom_fields, 'name');
            if (isset($custom_fields_by_name['avatar'])) {
                // Значение для установки
                $your_custom_field_value = [
                    'imagefile' => '/images/avatar/'.$social_name.'_'.$social_id.'.jpg',
                    'alt_text' => ''
                ];
                $your_custom_field_value = json_encode($your_custom_field_value);
                $app = $this->getApplication();
                $mvcFactory = $app->bootComponent('com_fields')->getMVCFactory();
                $model = $mvcFactory->createModel('Field', 'Administrator', ['ignore_request' => true]);
                $model->setFieldValue(
                     $custom_fields_by_name['avatar']->id,
                     $user_id,
                     $your_custom_field_value
                );
            }

            //добавляем аватар на сервер
            $http = HttpFactory::getHttp();
            $avatarUrl = preg_replace('/&cs=\d+x\d+/', '&cs=640x640', $avatar);
            $savePath = JPATH_ROOT . '/images/avatar/'.$social_name.'_'.$social_id.'.jpg';
            $response_ava = $http->get($avatarUrl);
            if ($response_ava->code === 200) {
                $dir = dirname($savePath);
                if (!Folder::exists($dir)) {
                    Folder::create($dir);
                }
                File::write($savePath, $response_ava->body);
            }

            //проверяем активацию и если она пройдена логиним пользователя
            $user = $event->getArguments()['subject'];
            if(empty($user['activation'])) {
                $statusSuccess           = Authentication::STATUS_SUCCESS;
                $response                = $this->getAuthenticationResponseObject();
                $response->status        = $statusSuccess;
                $response->username      = $user['username'];
                $response->fullname      = $user['name'];
                $response->error_message = '';
                $response->language      = $user['language'];

                $options = [
                    'remember' => true,
                    'action'   => 'core.login.site',
                ];

                $dispatcher = $app->getDispatcher();
                PluginHelper::importPlugin('user');
                $event_login = new Event('onUserLogin', [(array) $response, $options]);
                $results = $dispatcher->dispatch('onUserLogin', $event_login);

                $app->redirect(URI::root()); // Редирект на главную страницу
            }

        }
        return true;
    }

    //при удалении пользователя удаляем связанную таблицу плагина и изображение
    public function onAfterDeleteUser (Event $event)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $user_id = $event->getArguments()['subject']['id'];

        $query = $db->getQuery(true);
        $conditions = array(
            $db->quoteName('user_id') . ' = '.$user_id
        );

        $query->delete($db->quoteName('#__joomlab_soc_login'));
        $query->where($conditions);
        $db->setQuery($query);
        $result = $db->execute();

        return true;
    }
}
