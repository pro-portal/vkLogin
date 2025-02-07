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

$http = HttpFactory::getHttp();

$code_verifier = Factory::getSession()->get('code_verifier');
$code = $app->getInput()->json->getArray()['code'];
$device_id =  $app->getInput()->json->getArray()['device_id'];
$state =  $app->getInput()->json->getArray()['state'];

// Данные для запроса токена
$params = json_decode($this->params);
$postData = [
    'client_id'      => $params->app_id,
    'grant_type'     => 'authorization_code',
    'code_verifier'  => $code_verifier,
    'device_id'      => $device_id,
    'code'           => $code,
    'state' => $state,
    'redirect_uri'   => $params->redirectUrl
];
// получаем токен
$token = '';
$responseToken = $http->post(
    'https://id.vk.com/oauth2/auth',
    $postData
);
if ($responseToken->getStatusCode() === 200) {
    $responseData = json_decode($responseToken->body);
    //  var_dump($responseData);
    if(!empty($responseData->access_token)) {
        $token = $responseData->access_token;
    } else {
        return;
    }
    $responseUser = '';
    if(!empty($token)) {
        //получаем информацию о пользователе
        $userData = [
            'client_id' => $params->app_id,
            'access_token' => $token
        ];
        $response = $http->post(
            'https://id.vk.com/oauth2/user_info',
            $userData
        );
        if ($response->getStatusCode() === 200) {
            $responseUser = json_decode($response->body);
        } else {
            return;
        }
    }
} else {
    return;
}

if(!empty($responseUser)) {
    $social_id = $responseUser->user->user_id;
    $avatar = $responseUser->user->avatar;
    $social_user_name = $responseUser->user->first_name . ' ' . $responseUser->user->last_name;
    $social_user_email = $responseUser->user->email;
    $social_user_phone = $responseUser->user->phone;
} else {
    $social_id = '';
}
?>