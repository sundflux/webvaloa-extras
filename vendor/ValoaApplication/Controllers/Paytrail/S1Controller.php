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

namespace ValoaApplication\Controllers\Paytrail;

// Libvaloa classes
use Libvaloa\Debug;
use Libvaloa\Controller\Redirect;

// Webvaloa classes
use Webvaloa\Configuration;

// Standard classes
use Exception;

 /**
  * Payment module for Paytrails S1 form API.
  *
  * How to use (for developers):
  *
  * 0) Add the Paytrail component to component- table and add
  *    public role id for it with component_role table. This set
  *    is needed so settings module can access the configuration
  *    variables set in next step, (url: /settings/Paytrail).
  *
  * 1) Insert these items to configuration table:
  *
  * You paytrail account (Paytrail's testing credentials mentioned as defaults):
  *
  *    paytrail_merchant_id        - Merchant ID       (default: 13466)
  *    paytrail_merchant_secret    - Merchant secret   (default: 6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ)
  *
  * Payment component settings. Note, all these are required!
  *
  *    paytrail_widget_height      - Widget height     (default: 0)
  *    paytrail_widget_width       - Widget width      (default: 500)
  *    paytrail_culture            - Culture           (default: fi_FI)
  *    paytrail_currency           - Currency          (default: EUR)
  *    paytrail_redirect_to        - Redirect to target controller after successful payment.
  *                                  Controller also gets paymentID as parameter, which target
  *                                  controller can use to check external_order_id for example.
  *                                                     (default: '')
  *
  * 2) Create tables as defined in schema-1.0.0.mysql.sql
  *
  * 3) Add payment to payent- table with at least amount and state = 0 (pending, not paid).
  *    Usually you'll also want to add external_order_id which links to order data in your
  *    web store / application.
  *
  * 4) Access payment with url /paytrail_s1/<payment_id>
  *
  */
class S1Controller extends \Webvaloa\Application
{

    private $config;
    private $secret;

    public function __construct()
    {
        $this->config = new Configuration('Paytrail');
        $this->secret = $this->config->paytrail_merchant_secret->value;
    }

    public function index($paymentID = false)
    {
        if (!$paymentID || !is_numeric($paymentID)) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_NOT_FOUND'));
        }

        // Payment ID
        $this->view->order_number = $paymentID;

        // Load payment
        $payment = $this->loadPayment($paymentID);
        if (!isset($payment->id) || ($payment->id != $paymentID)) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_NOT_FOUND'));
        }

        // Payment already paid or cancelled
        if ($payment->payment_status > 0) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_ALREADY_PAID'));
        }

        // Payment amount
        $this->view->amount = $this->formatAmount($payment->amount);

        // Payment urls
        $url = $this->request->getBaseUri() . "/paytrail_s1";
        $this->view->successURL = $url . '/success/' . $this->view->order_number;
        $this->view->notifyURL = $url . '/notify/' . $this->view->order_number;
        $this->view->cancelURL = $url . '/cancel/' . $this->view->order_number;

        // Basic data for payment
        $this->view->culture = $this->config->paytrail_culture->value;
        $this->view->currency = $this->config->paytrail_currency->value;
        $this->view->width = $this->config->paytrail_widget_width->value;
        $this->view->height = $this->config->paytrail_widget_height->value;
        $this->view->merchant_id = $this->config->paytrail_merchant_id->value;
        $this->view->order_description = \Webvaloa\Webvaloa::translate('ORDER_DESCRIPTION');

        // Checksum
        $this->view->checksum = strtoupper(md5("{$this->secret}|{$this->view->merchant_id}|{$this->view->amount}|{$this->view->order_number}||{$this->view->order_description}|{$this->view->currency}|{$this->view->successURL}|{$this->view->cancelURL}||{$this->view->notifyURL}|S1|{$this->view->culture}||1||"));
    }

    public function success($paymentID = false)
    {
        // Load payment
        $payment = $this->loadPayment($paymentID);
        if (!isset($payment->id) || ($payment->id != $paymentID)) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_NOT_FOUND'));
        }

        // Payment already paid or cancelled
        if ($payment->payment_status > 0) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_ALREADY_PAID'));
        }

        $params = array(
            'ORDER_NUMBER',
            'TIMESTAMP',
            'PAID',
            'METHOD',
            'RETURN_AUTHCODE'
        );

        foreach ($params as $k => $v) {
            if (!isset($_GET[$v])) {
                throw new Exception(\Webvaloa\Webvaloa::translate('CANNOT_VERIFY_PAYMENT'));
            }
        }

        $checksum = "{$_GET['ORDER_NUMBER']}|{$_GET['TIMESTAMP']}|{$_GET['PAID']}|{$_GET['METHOD']}|{$this->secret}";
        $checksum = strtoupper(md5($checksum));

        // Payment successful
        if ($checksum == $_GET['RETURN_AUTHCODE']) {
            // Mark as paid
            $this->setPaymentStatus($paymentID, 1, $_GET);

            // Redirect to some other controller
            $tmp = $this->config->paytrail_redirect_to->value;
            if (!empty($tmp)) {
                $url = $this->request->getBaseUri() . '/' . strtolower($tmp) . '/' . $paymentID;
                Redirect::to($url);
            }
        } else {
            throw new Exception(\Webvaloa\Webvaloa::translate('CANNOT_VERIFY_PAYMENT'));
        }
    }

    public function cancel($paymentID = false)
    {
        // Load payment
        $payment = $this->loadPayment($paymentID);
        if (!isset($payment->id) || ($payment->id != $paymentID)) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_NOT_FOUND'));
        }

        // Payment already paid or cancelled
        if ($payment->payment_status > 0) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_ALREADY_PAID'));
        }

        $params = array(
            'ORDER_NUMBER',
            'TIMESTAMP',
            'RETURN_AUTHCODE'
        );

        foreach ($params as $k => $v) {
            if (!isset($_GET[$v])) {
                throw new Exception(\Webvaloa\Webvaloa::translate('CANNOT_VERIFY_PAYMENT'));
            }
        }

        $checksum = "{$_GET['ORDER_NUMBER']}|{$_GET['TIMESTAMP']}|{$this->secret}";
        $checksum = strtoupper(md5($checksum));

        // Payment successfully cancelled
        if ($checksum == $_GET['RETURN_AUTHCODE']) {

            // Mark as cancelled
            $this->setPaymentStatus($paymentID, 2);

        } else {
            throw new Exception(\Webvaloa\Webvaloa::translate('CANNOT_VERIFY_PAYMENT'));
        }
    }

    public function notify($paymentID = false)
    {
        $this->success($paymentID);
    }

    private function formatAmount($a)
    {
        return str_replace(',', '.', $a);
    }

    private function loadPayment($paymentID)
    {
        $query = "
            SELECT *
            FROM payment
            WHERE id = ?";

        $stmt = $this->db->prepare($query);
        $stmt->set((int) $paymentID);

        try {
            $stmt->execute();
            $row = $stmt->fetch();

            if (isset($row->id)) {
                return $row;
            }

            return false;
        } catch (Exception $e) {
            Debug::__print($e->getMessage());
        }

        return false;
    }

    private function setPaymentStatus($paymentID, $paymentStatus = 1, $meta = false)
    {
        $paymentID = (int) $paymentID;
        $paymentStatus = (int) $paymentStatus;
        $meta = json_encode($meta);

        if ($paymentStatus < 0 || $paymentStatus > 2) {
            throw new Exception(\Webvaloa\Webvaloa::translate('UNKNOWN_PAYMENT_STATUS'));
        }

        $query = "
            UPDATE payment
            SET payment_status = ?, meta = ?
            WHERE id = ?";

        $stmt = $this->db->prepare($query);
        $stmt->set((int) $paymentStatus);
        $stmt->set($meta);
        $stmt->set((int) $paymentID);

        try {
            $stmt->execute();

            return true;
        } catch (Exception $e) {
            Debug::__print($e->getMessage());
        }

        return false;
    }

}
