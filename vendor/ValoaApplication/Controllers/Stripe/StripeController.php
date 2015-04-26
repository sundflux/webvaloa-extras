<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
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

namespace ValoaApplication\Controllers\Stripe;

// Libvaloa classes
use Libvaloa\Debug;
use Libvaloa\Controller\Redirect;
use Libvaloa\Db;

// Standard classes
use stdClass;
use Exception;
use RuntimeException;

use Webvaloa\Helpers\Stripe as StripeHelper;

require_once(LIBVALOA_EXTENSIONSPATH . '/stripe-php/init.php');

/**
 *
 * 0) Copy latest stripe to vendor/stripe-php from: https://github.com/stripe/stripe-php/releases
 *
 * 1) set the api key in config.php:
 *    'stripe_api_key'            => 'sk_test_yourkeywhatever'
 *    'stripe_pub_key'            => 'pk_test_0tSrTkLCP63sk9lpeUOuTPFJ'
 * 
 * 2) Install the component from Extensions > Install
 *
 * 3) Set permissions for this component for desired groups
 *
 * 4) Install database tables from schema-1.0.0.mysql.sql
 *
 */

class StripeController extends \Webvaloa\Application
{

    private $stripeHelper;

    public function __construct()
    {

        if (!isset(\Webvaloa\config::$properties['stripe_api_key']) ||  empty(\Webvaloa\config::$properties['stripe_api_key'])) {
            throw new RuntimeException('stripe_api_key must be set in config first');
        }

        if (!isset(\Webvaloa\config::$properties['stripe_pub_key']) ||  empty(\Webvaloa\config::$properties['stripe_pub_key'])) {
            throw new RuntimeException('stripe_pub_key must be set in config first');
        }

        \Stripe\Stripe::setApiKey(\Webvaloa\config::$properties['stripe_api_key']);

        $this->stripeHelper = new StripeHelper;
        $this->view->publicKey = \Webvaloa\config::$properties['stripe_pub_key'];
    }

    public function index()
    {
        Debug::__print($_POST);

        $userStripeId = $this->stripeHelper->getStripeId();

        // TODO: check token here and create customer+subscription+card after that...
        if ($userStripeId === false) {
            try { 
                $this->stripeHelper->createCustomer();
            } 
            catch(Exception $e) {} 
            catch(RuntimeException $e) {}
            catch(UnexpectedValueException $e) {}
        }

        try {
            $customer = $this->stripeHelper->getCustomer();
        } catch(Exception $e) {

        }

        if (isset($customer)) {
            Debug::__print('StripeId is:');
            Debug::__print($userStripeId);
            Debug::__print($customer);
        }
    }

    public function charge()
    {

    }

}
 