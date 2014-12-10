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

namespace ValoaApplication\Controllers\Checkout;

// Libvaloa classes
use Libvaloa\Debug;
use Libvaloa\Controller\Redirect;

// Webvaloa classes
use Webvaloa\Configuration;
use Webvaloa\Helpers\Checkout;
use Webvaloa\Helpers\Referencefi;

// Standard classes
use Exception;
use stdClass;

 /**
  * Payment module for Checkout XML API.
  *
  * How to use (for developers):
  *
  * 0) Install the Checkout component
  *
  * 1) Insert these items to configuration table:
  *
  * You paytrail account (Paytrail's testing credentials mentioned as defaults):
  *
  *    checkout_merchant_id        - Merchant ID       (default: 375917)
  *    checkout_merchant_secret    - Merchant secret   (default: SAIPPUAKAUPPIAS)
  *
  * Payment component settings. Note, all these are required!
  *
  *    checkout_redirect_to        - Redirect to target controller after successful payment.
  *                                  Controller also gets paymentID as parameter, which target
  *                                  controller can use to check external_order_id for example.
  *                                                     (default: '')
  *
  * 2) Create tables as defined in schema-1.0.0.mysql.sql
  *
  * 3) Add payment to payent- table with at amount and state = 0 (pending, not paid).
  *    Usually you'll also want to add external_order_id which links to order data in your
  *    web store / application.
  *
  * 4) Access payment with url /checkout_payment/<payment_id>
  *
  */
class PaymentController extends \Webvaloa\Application
{

    private $config;
    private $merchantID;
    private $secret;

    public function __construct()
    {
        $this->config = new Configuration('Checkout');
        $this->merchantID = $this->config->checkout_merchant_id->value;
        $this->secret = $this->config->checkout_merchant_secret->value;
    }

    public function index($paymentID = false)
    {
        /*
         * Process somewhat shortly:
         *
         * 1. Load the order
         * 2. Generate return urls
         * 3. Generate reference if it doesn't exist
         * 4. ** Generate order stamp on every page load **
         * 5. Fetch payment buttons from checkout API
         */

        // Required
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
        if ((int) $payment->payment_status > 0) {
            $this->ui->addError(\Webvaloa\Webvaloa::translate('PAYMENT_ALREADY_PAID'));

            return;
        }

        // Payment amount
        $this->view->amount = $this->formatAmount($payment->amount);

        // And in cents
        $this->view->amountCents = $this->formatAmount($payment->amount) * 100;

        // You can set these with plugin in onBeforeController- event if needed:
        if (!isset($this->view->firstname)) {
            $this->view->firstname = '';
        }

        if (!isset($this->view->familyname)) {
            $this->view->familyname = '';
        }

        if (!isset($this->view->addressname)) {
            $this->view->addressname = '';
        }

        if (!isset($this->view->postcode)) {
            $this->view->postcode = '';
        }

        if (!isset($this->view->postoffice)) {
            $this->view->postoffice = '';
        }

        if (!isset($this->view->email)) {
            $this->view->email = '';
        }

        // Payment urls
        $url = $this->request->getBaseUri() . "/checkout_payment";
        $this->view->successURL = $url . '/success/' . $this->view->order_number;
        $this->view->notifyURL = $url . '/notify/' . $this->view->order_number;
        $this->view->cancelURL = $url . '/cancel/' . $this->view->order_number;

        // Basic data for payment
        $this->view->merchant_id = $this->config->checkout_merchant_id->value;

        // Description can also be set from plugin
        if (!isset($this->view->order_description)) {
            $this->view->order_description = \Webvaloa\Webvaloa::translate('ORDER_DESCRIPTION');
        }

        // Generate unique payment stamp
        $stamp = str_replace(".", "", microtime(true));
        if (strlen($stamp) == 13) { // last number was zero so it doesn't get printed out with microtime
            $stamp .= 0;
        }
        $stamp .= $paymentID;

        // Maxlength in case id's are huge. Won't be though :)
        $stamp = substr($stamp, 0, 20);

        // Save stamp
        if (!isset($payment->meta) || empty($payment->meta)) {
            $meta = new stdClass;
        } else {
            $meta = json_decode($payment->meta);
        }

        // Generate reference if one doesn't exist
        if (!isset($meta->reference) || empty($meta->reference)) {
            $ref = new Referencefi($stamp);
            $meta->reference = (string) $ref;
        }

        // Save meta after stamp
        $meta->stamp = $stamp;

        // Xml API data
        $xmldata                    = array();
        $xmldata["stamp"]           = $stamp;
        $xmldata["reference"]       = $meta->reference;
        $xmldata["message"]         = $this->view->order_description;
        $xmldata["return"]          = $this->view->successURL;
        $xmldata["delayed"]         = $this->view->notifyURL;
        $xmldata["reject"]          = $this->view->cancelURL;
        $xmldata["cancel"]          = $this->view->cancelURL;
        $xmldata["amount"]          = $this->view->amountCents;
        $xmldata["delivery_date"]   = date("omd", strtotime("+1 week"));
        $xmldata["firstname"]       = $this->view->firstname;
        $xmldata["familyname"]      = $this->view->familyname;
        $xmldata["address"]         = $this->view->addressname;
        $xmldata["postcode"]        = $this->view->postcode;
        $xmldata["postoffice"]      = $this->view->postoffice;
        $xmldata["email"]           = $this->view->email;

        // Save metadata
        $meta->xmldata = $xmldata;

        // Get payment buttons
        $co = new Checkout($this->merchantID, $this->secret);
        $response = $co->getCheckoutXML($xmldata); // get payment button data
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            throw new Exception(\Webvaloa\Webvaloa::translate('ACCESSING_API_FAILED'));
        } else {
            $tmpobj = $co->getCheckoutObject($xmldata);
            $this->view->mac = $tmpobj['MAC'];

            // paymentURL link is used if a payer somehow manages to fail paying. You can
            // save it to the webstore and later (if needed) send it by email.
            $meta->url = $xml->paymentURL;

            $this->view->checkoutXML = $xml;
            $tmp = (object) $xml->payments->payment->banks;
            foreach ($tmp as $tmp2) {
                foreach ($tmp2 as $k => $bank) {
                    $_bank = new stdClass;
                    $_bank->BankName = (string) $k;

                    foreach ($bank->attributes() as $k2 => $v) {
                        $_bank->$k2 = (string) $v;
                    }

                    foreach ($bank as $kk => $vv) {
                        $value = new stdClass;
                        $value->key = $kk;
                        $value->value = (string) $vv; // simplexml object without cast!
                        $_bank->values[] = $value;
                        unset($value);
                    }

                    $this->view->checkoutXMLBanks[] = $_bank;
                }
            }
        }

        // Save metadata
        $metaJSON = json_encode($meta);
        $query = "
            UPDATE payment
            SET meta = ?
            WHERE id = ?";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->set($metaJSON);
            $stmt->set($paymentID);
            $stmt->execute();
        } catch (Exception $e) {

        }
    }

    public function success($paymentID = false)
    {
        // Load payment
        $payment = $this->loadPayment($paymentID);
        if (!isset($payment->id) || ($payment->id != $paymentID)) {
            throw new Exception(\Webvaloa\Webvaloa::translate('PAYMENT_NOT_FOUND'));
        }

        // Payment already paid or cancelled
        if ((int) $payment->payment_status > 0) {
            $this->ui->addError(\Webvaloa\Webvaloa::translate('PAYMENT_ALREADY_PAID'));

            return;
        }

        if(!isset($_GET["STAMP"]))
            throw new Exception(\Webvaloa\Webvaloa::translate('CHECKOUT_STAMP_MISSING'));
        if(!isset($_GET["STATUS"]))
            throw new Exception(\Webvaloa\Webvaloa::translate('CHECKOUT_STATUS_MISSING'));
        if(!isset($_GET["REFERENCE"]))
            throw new Exception(\Webvaloa\Webvaloa::translate('CHECKOUT_REFERENCE_MISSING'));
        if(!isset($_GET["MAC"]))
            throw new Exception(\Webvaloa\Webvaloa::translate('CHECKOUT_MAC_MISSING'));
        if(!isset($_GET["PAYMENT"]))
            throw new Exception(\Webvaloa\Webvaloa::translate('CHECKOUT_PAYMENT_MISSING'));

        $co = new Checkout($this->merchantID, $this->secret);

        if (!$co->validateCheckout($_GET)) {
            throw new Exception(\Webvaloa\Webvaloa::translate('CHECKOUT_XML_CHECKSUM_MISMATCH'));
        }

        // All ok from this point.
        if (
            $_GET["STATUS"]==2 || // payment OK
            $_GET["STATUS"]==4 || // payment OK
            $_GET["STATUS"]==5 || // payment OK
            $_GET["STATUS"]==6 || // payment frozen
            $_GET["STATUS"]==7 || // 3rd party has accepted the payment / waiting for confirmation
            $_GET["STATUS"]==8 || // 3rd party has accepted the payment / activated
            $_GET["STATUS"]==10   // payment transferred
        ) {
            // Payment OK here

            // Mark as paid
            $meta = new stdClass;
            if (!empty($payment->meta)) {
                $meta = json_decode($payment->meta);
            }

            $meta->response = $_GET;
            $this->setPaymentStatus($paymentID, 1, $meta);

            // Redirect to some other controller
            $tmp = $this->config->checkout_redirect_to->value;
            if (!empty($tmp)) {
                $url = $this->request->getBaseUri() . '/' . strtolower($tmp) . '/' . $paymentID;
                Redirect::to($url);
            }

            // Or fallback to message
            $this->ui->addMessage(\Webvaloa\Webvaloa::translate('CHECKOUT_PAYMENT_SUCCESS'));

            return;
        } elseif ($_GET["STATUS"] == 3) {
            // The payment is delayed
            // TODO: Send order notification mail to admin

        } else {
            // Payment failed / was cancelled

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
        if ((int) $payment->payment_status > 0) {
            $this->ui->addError(\Webvaloa\Webvaloa::translate('PAYMENT_ALREADY_PAID'));

            return;
        }

        // Validates the payment
        $this->success($paymentID);

        // Mark as cancelled
        $meta = new stdClass;
        if (!empty($payment->meta)) {
            $meta = json_decode($payment->meta);
        }
        $this->setPaymentStatus($paymentID, 2, $meta);

        // Redirect to some other controller
        $tmp = $this->config->checkout_redirect_to->value;
        if (!empty($tmp)) {
            $url = $this->request->getBaseUri() . '/' . strtolower($tmp) . '/' . $paymentID;
            Redirect::to($url);
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
                return clone $row;
            }

            return false;
        } catch (PDOException $e) {
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
        } catch (PDOException $e) {
            Debug::__print($e->getMessage());
        }

        return false;
    }

}
