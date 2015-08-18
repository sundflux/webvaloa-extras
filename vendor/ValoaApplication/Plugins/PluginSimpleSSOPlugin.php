<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2015 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace ValoaApplication\Plugins;

use Webvaloa\Controller\Request;
use Webvaloa\Controller\Redirect;
use Libvaloa\Debug;
use ReflectionClass;
use ReflectionException;
/*
 * - 1) Define endpoint
 *
 * Add 'simple_sso_endpoint' property to config.php with api endpoint url, for example:
 *
 * 'simple_sso_endpoint'       => 'http://http://webvaloa.dev.webvaloa.org/test.php',
 *
 * - 2) Set secret share key
 * 'simple_sso_sharekey'       => 'yourkeyhere',
 * ^ that key must match the key at sso target
 *
 * - 3) Define sso auth driver as:
 * 'simple_sso_auth'           => 'Webvaloa\Auth\Sso',
 *
 * - How to require authentication in the controller?
 *
 * To request SSO authentication, make sure to define $expectAuthentication
 * in the controller. For example:
 *
 * class AuthtestController extends \Webvaloa\Application
 * {
 *    public static $expectAuthentication = true;
 *
 */

use Webvaloa\Role;
use Webvaloa\User;
use Webvaloa\Auth\Auth;

class PluginSimpleSSOPlugin extends \Webvaloa\Plugin
{
    public function onBeforeController()
    {
        $endPoint = \Webvaloa\config::$properties['simple_sso_endpoint'];
        $shareKey = \Webvaloa\config::$properties['simple_sso_sharekey'];

        if (isset($_GET['sso_response'])) {
            $response = base64_decode($_GET['sso_response']);
            $response = json_decode($response);

            Debug::__print($response);

            if (isset($response->success) && $response->success == 1) {

                // Validate hash
                $resp = sha1($shareKey.':'.$response->user_id.':'.$response->email);

                if ($resp != $response->hash) {
                    // checksum mismatch

                    return;
                }

                if (isset($_SESSION['User']) && $_SESSION['User'] == $response->email) {
                    return; // Already authed
                } elseif (isset($_SESSION['User']) && $_SESSION['User'] != $response->email) {
                    // Authed but different user.
                    $backend = \Webvaloa\config::$properties['webvaloa_auth'];

                    $auth = new Auth();
                    $auth->setAuthenticationDriver(new $backend());
                    $auth->logout();
                }

                Debug::__print('SSO: hash matched, all ok');

                // Create user/login
                try {
                    if (User::usernameAvailable($response->email)) {
                        // User not found
                        $user = new User();
                        $user->email = $user->login = $response->email;
                        $user->firstname = $user->lastname = $response->email;
                        $user->locale = 'en_US';

                        $tmp = explode(' ', $response->name);
                        if (isset($tmp[0])) {
                            $user->firstname = $tmp[0];
                        }
                        if (isset($tmp[1])) {
                            $user->lastname = $tmp[1];
                        }

                        $user->blocked = 0;
                        $id = $user->save();

                        $role = new Role();
                        $user = new User($id);
                        $user->addRole($role->getRoleID('Registered'));
                    }
                } catch (RuntimeException $e) {
                } catch (Exception $e) {
                }

                $user = new User();
                $user->byEmail($response->email);

                $backend = \Webvaloa\config::$properties['simple_sso_auth'];

                $auth = new Auth();
                $auth->setAuthenticationDriver(new $backend());
                $auth->authenticate($response->email, $response->user_id);

                $_SESSION['UserID'] = $user->id;

                Debug::__print($_SESSION);
            }

            // Never redirect again in case of any response
            return;
        }

        try {
            $class = new ReflectionClass($this->controller);
            $v = $class->getStaticPropertyValue('expectAuthentication');

            if (isset($v) && $v === true) {
                $returnUrl = strtolower($this->request->getUri());
                $endPoint .= '?return='.base64_encode($returnUrl).'&hash='.sha1($returnUrl.':'.$shareKey);

                Debug::__print('SSO: API endpoint:');
                Debug::__print($endPoint);
                Debug::__print('SSO: API returnpoint:');
                Debug::__print($returnUrl);

                Redirect::to($endPoint);
            }
        } catch (ReflectionException $e) {
            Debug::__print($e->getMessage());

            return;
        }
    }
}
