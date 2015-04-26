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

namespace Webvaloa\Helpers;

use Exception;
use RuntimeException;
use UnexpectedValueException;

use Libvaloa\Debug;
use Webvaloa\User;

/**
 * Example:
 *
 * use Webvaloa\Helpers\Stripe as StripeHelper;
 *
 * $stripe = new StripeHelper;
 *
 * try { 
 *     $stripe->createCustomer();
 * } 
 * catch(Exception $e) {} 
 * catch(RuntimeException $e) {}
 * catch(UnexpectedValueException $e) {}
 *
 *
 */

class Stripe
{

    public function __construct()
    {

    }

    public function getStripeId($userId = false) 
    {
        if ($userId === false && isset($_SESSION['UserID'])) {
            $userId = $_SESSION['UserID'];
        }

        if ($userId === false || !is_numeric($userId)) {
            throw new UnexpectedValueException('UserID not found');
        }

        $query = " 
            SELECT stripe_id 
            FROM stripe_ids
            WHERE user_id = ?";

        $db = \Webvaloa\Webvaloa::DBConnection();

        $stmt = $db->prepare($query);
        $stmt->set((int) $userId);

        try {
            $stmt->execute();
            $row = $stmt->fetch();

            if (isset($row->stripe_id)) {
                return $row->stripe_id;
            }

            return false;
        } catch(Exception $e) {
            Debug::__print($e->getMessage());
        }
    }

    public function setStripeId($stripeId, $userId = false) 
    {
        if ($userId === false && isset($_SESSION['UserID'])) {
            $userId = $_SESSION['UserID'];
        }

        if ($userId === false || !is_numeric($userId)) {
            throw new UnexpectedValueException('UserID not found');
        }

        if (empty($stripeId)) {
            throw new UnexpectedValueException('stripeId cannot be empty');
        }

        if ($this->getStripeId($userId)) {
            throw new RuntimeException('User already has stripe id');
        }

        // Insert new stripeid
        $query = "
            INSERT INTO stripe_ids (stripe_id, user_id) 
            VALUES (?, ?) ";

        $db = \Webvaloa\Webvaloa::DBConnection();

        $stmt = $db->prepare($query);
        $stmt->set($stripeId);
        $stmt->set($userId);

        try {
            $stmt->execute();
        } catch(Exception $e) {
            Debug::__print($e->getMessage());
        }

        if ($this->getStripeId($userId)) {
            return true;
        } else {
            throw new RuntimeException('Adding stripeId failed');
        }
    }

    public function createCustomer($userId = false)
    {
        if ($userId === false && isset($_SESSION['UserID'])) {
            $userId = $_SESSION['UserID'];
        }

        if ($userId === false || !is_numeric($userId)) {
            throw new UnexpectedValueException('UserID not found');
        }

        if ($this->getStripeId($userId)) {
            throw new RuntimeException('User already has stripe id');
        }

        $user = new User($userId);

        $opts = array(
            'email' => $user->email,
            'metadata' => array(
                'user_id' => $userId
            )
        );

        try {
            $resp = \Stripe\Customer::create($opts);

            $this->setStripeId($resp->id);
            Debug::__print($resp);

            return $resp;
        } catch(Exception $e) {
            Debug::__print($e->getMessage());
        }
    }

    public function getCustomer($userId = false) 
    {
        if ($userId === false && isset($_SESSION['UserID'])) {
            $userId = $_SESSION['UserID'];
        }

        if ($userId === false || !is_numeric($userId)) {
            throw new UnexpectedValueException('UserID not found');
        }

        if (!$stripeId = $this->getStripeId($userId)) {
            throw new RuntimeException('User does not have stripe id');
        }

        try {
            $resp = \Stripe\Customer::retrieve($stripeId);
            Debug::__print($resp);

            return $resp;
        } catch(Exception $e) {
            Debug::__print($e->getMessage());
        }
    }

}
