<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
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
/**
 * Facebook authentication Plugin.
 *
 * Usage instructions:
 *
 * 1. Add your Facebook App ID and secret to configuration table with keys:
 *
 * facebook_app_id
 * facebook_app_secret
 *
 * 2. Add plugin 'LoginFacebook' to your 'plugin' table.
 *
 * 3. git clone https://github.com/facebook/facebook-php-sdk.git to your /vendor directory.
 *
 */

namespace ValoaApplication\Plugins;

// Facebook libraries
require_once LIBVALOA_EXTENSIONSPATH . "/facebook-php-sdk/src/base_facebook.php";
require_once LIBVALOA_EXTENSIONSPATH . "/facebook-php-sdk/src/facebook.php";

// Libvaloa classes
use Libvaloa\Debug;
use Webvaloa\Auth\Auth;

// Webvaloa classes
use Webvaloa\User;
use Webvaloa\Role;
use Webvaloa\Configuration;

// Standard classes
use DOMDocument;

class LoginFacebookPlugin extends \Webvaloa\Plugin
{
    private $facebook;
    private $user;
    private $url;
    private $profile;
    private $scope;
    private $buttonText;

    public function __construct()
    {
        $configuration = new Configuration;

        $config = array(
            'appId' => $configuration->facebook_app_id->value,
            'secret' => $configuration->facebook_app_secret->value,
            'allowSignedRequest' => false
        );

        $this->scope = array(
            'scope' => 'email'
        );

        $this->facebook = new \Facebook($config);
        $this->user = $this->facebook->getUser();
    }

    public function onBeforeController()
    {
        if (isset($_SESSION["UserID"])) {
            return; // Already authed
        }

        try {
            if ($this->user) {
                $this->profile = $this->facebook->api('/me', 'GET');

                $backend = 'Webvaloa\Auth\Sso';

                if (!\Webvaloa\User::usernameAvailable($this->profile['email'])) {
                    Debug::__print("Username {$this->profile['email']} found");

                    // Username exists, login the user

                    $auth = new Auth;
                    $auth->setAuthenticationDriver(new $backend);
                    $auth->authenticate($this->profile['email'], '');

                } else {
                    Debug::__print("Username {$this->profile['email']} not  found");

                    // User doesn't exist. Create user and login.
                    $user = new User;
                    $user->login = $user->email = $this->profile['email'];
                    $user->firstname = $this->profile['first_name'];
                    $user->lastname = $this->profile['last_name'];
                    $user->blocked = 0;
                    $user->locale = '*';
                    if (isset($_SESSION['locale'])) {
                        $user->locale = $_SESSION['locale'];
                    }
                    $userID = $user->save();

                    // Add registered role for the user
                    $user = new User($userID);
                    $role = new Role;
                    $user->addRole($role->getRoleID('Registered'));

                    // Attempt to login the user
                    $auth = new Auth;
                    $auth->setAuthenticationDriver(new $backend);
                    $auth->authenticate($this->profile['email'], '');
                }
            }
        } catch (\FacebookApiException $e) {
            Debug::__print('Facebook session expired, relogin');
        }
    }

    public function onAfterController()
    {
        $this->ui->addCSS('/bootstrap/css/bootstrap-social.css');
    }

    public function onBeforeRender()
    {
        // Includes fontawesome CSS in head
        // Get preprocessed template
        $dom = $this->ui->getPreprocessedTemplateDom();
        $head = $dom->getElementsByTagName('head')->item(0);

        $injectTag = $dom->createElement('link');
        $injectTag->setAttribute("href", "//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css");
        $injectTag->setAttribute("rel", "stylesheet");

        // And inject it to before first element after body
        $head->insertBefore($injectTag, $head->getElementsByTagName("*")->item(0));
    }

    public function onAfterRender()
    {
        if (!isset($_SESSION["UserID"])) {
            if ($this->user) {
                // Logout not really used at the moment, but leaving code here anyway
                $this->url = $this->facebook->getLogoutUrl();
                $this->buttonText = \Webvaloa\Webvaloa::translate('LOGOUT_FACEBOOK', 'LoginFacebookPlugin');
            } else {
                $this->url = $this->facebook->getLoginUrl($this->scope);
                $this->buttonText = \Webvaloa\Webvaloa::translate('LOGIN_FACEBOOK', 'LoginFacebookPlugin');
            }

            $dom = new DOMDocument();
            $dom->loadHTML($this->xhtml);

            // A link
            $injectTag = $dom->createElement('a', $this->buttonText);
            $injectTag->setAttribute("id", 'facebook-login');
            $injectTag->setAttribute("href", $this->url);
            $injectTag->setAttribute("class", "btn btn-block btn-social btn-facebook clearfix");

            // <i> inside A
            $i = $dom->createElement('i');
            $i->setAttribute("class", "fa fa-facebook");
            $injectTag->appendChild($i);

            // Insert link to login form
            $form = $dom->getElementById('form-signin');

            if ($form) {
                $form->appendChild($injectTag);

                // Insert <br> before the button
                $br = $dom->createElement('br');
                $form->insertBefore($br, $dom->getElementById('facebook-login'));

                // Modified xhtml
                $this->xhtml = $dom->saveHTML();
            }
        }
    }

}
