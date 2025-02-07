<?php
/**
 * @package     joomLab.Plugin
 * @subpackage  User.Vklogin
 *
 * @copyright   (C) 2025 Alexand Novikov. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserHelper;


$view = $displayData['view'];

$params = json_decode($displayData['params']);
$app_name = $params->app_name;
$app_id = $params->app_id;
$redirectUrl = $params->redirectUrl;

$code_verifier = $displayData['code_verifier'];
$state = UserHelper::genRandomPassword(50);

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('plg_user_vklogin');
$wa->useStyle('plg_user_vklogin.css');

?>

   <?php function generate_code_challenge($code_verifier) {
        $hash = hash('sha256', $code_verifier, true); // Второй параметр true возвращает бинарные данные
        $base64Url = base64_encode($hash);
        $base64Url = strtr($base64Url, '+/', '-_');
        $base64Url = rtrim($base64Url, '=');
        return $base64Url;
    }
    $code_challenge = generate_code_challenge($code_verifier); ?>

<script defer>
    function redirectToVKAuth() {
        const vkAuthUrl = 'https://id.vk.com/auth?app_id=<?php echo $app_id ?>&device_id=&response_type=code&redirect_uri=<?php echo $redirectUrl ?>&scope=email%20phone&lang_id=0&scheme=light&oauth_version=2&v=2.4.1&redirect_state=<?php echo $state ?>&code_challenge=<?php echo $code_challenge ?>&code_challenge_method=sha256';
        window.location.href = vkAuthUrl;
    }
</script>
<div class="preloader_login"></div>

<script>
   document.addEventListener('DOMContentLoaded', function () {
       const url = new URL(window.location.href);
       const params = new URLSearchParams(url.search);
        if(params.get('code')) {
           const code = params.get('code');
           const device_id = params.get('device_id');
           const state = params.get('state');
               Joomla.request({
                   url: '/index.php?option=com_ajax&plugin=Vklogin&format=raw&group=user&social=vk',
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/json',
                   },
                   data: JSON.stringify({
                       code: code,
                       device_id: device_id,
                       state: state,
                       social_name: 'vkontakte'
                   }),
                   onBefore: function (xhr) {
                       const preloader = document.querySelector('.preloader_login');
                       if (preloader) {
                           preloader.style.display = 'block';
                       }
                   },
                   onSuccess: function (response) {
                       //console.log(response);
                       if (response) {
                           const currentUrl = new URL(window.location.href);
                           currentUrl.search = '';
                           window.history.replaceState({}, document.title, currentUrl.toString());

                           const user = JSON.parse(response);
                           if (user.isNew === true) {
                               window.location.href = user.registeredUrl;
                           }
                           if (user.isNew === false) {
                               if (user.message) {
                                   Joomla.renderMessages({
                                       info: [user.message]
                                   });
                               } else {
                                   location.reload();
                               }

                           }
                           if (user.isNew === 'error') {
                               Joomla.renderMessages({
                                   error: [user.message_error]
                               });
                           }
                       }
                   },
                   onError: function (xhr, status, error) {
                       console.error('Error:', error);
                   },
                   onComplete: function (xhr) {
                       const preloader = document.querySelector('.preloader_login');
                       if (preloader) {
                           preloader.style.display = 'none';
                       }
                   }
               });
           }

               const name = params.get('name');
               const email = params.get('email');
               const phone = params.get('phone');
               const checkform = params.get('checkform');
               if (checkform == 1) {
                   Joomla.renderMessages({
                       success: ['<?php echo Text::_('PLG_USER_SOCLOGIN_REG_TEXT'); ?>']
                   });
               }
               if (name) {
                   document.getElementById('jform_name').value = name;
               }
               if (email) {
                   document.getElementById('jform_email1').value = email;
               }
               if (phone) {
                   document.getElementById('jform_com_fields_phone').value = phone;
               }


   });
</script>