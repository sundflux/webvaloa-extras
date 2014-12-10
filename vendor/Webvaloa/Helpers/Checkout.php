<?php
/**
 * The Initial Developer of the Original Code is
 * Checkout Finland Oy
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2014 Checkout Finland Oy
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 * 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
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

class Checkout
{
    private $version		= "0001";
    private $language		= "FI";
    private $country		= "FIN";
    private $currency		= "EUR";
    private $device			= "1";
    private $content		= "1";
    private $type			= "0";
    private $algorithm		= "2";
    private $merchant		= "";
    private $password		= "";
    private $stamp			= 0;
    private $amount			= 0;
    private $reference		= "";
    private $message		= "";
    private $return			= "";
    private $cancel			= "";
    private $reject			= "";
    private $delayed		= "";
    private $delivery_date	= "";
    private $firstname		= "";
    private $familyname		= "";
    private $address		= "";
    private $postcode		= "";
    private $postoffice		= "";
    private $status			= "";
    private $email			= "";

    public function __construct($merchant, $password)
    {
        // merchant id
        $this->merchant	= $merchant;

        // security key (about 80 chars)
        $this->password	= $password;
    }

    /*
     * generates MAC and prepares values for creating payment
     */
    public function getCheckoutObject($data)
    {
        // overwrite default values
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        $mac = strtoupper(md5("{$this->version}+{$this->stamp}+{$this->amount}+{$this->reference}+{$this->message}+{$this->language}+{$this->merchant}+{$this->return}+{$this->cancel}+{$this->reject}+{$this->delayed}+{$this->country}+{$this->currency}+{$this->device}+{$this->content}+{$this->type}+{$this->algorithm}+{$this->delivery_date}+{$this->firstname}+{$this->familyname}+{$this->address}+{$this->postcode}+{$this->postoffice}+{$this->password}"));
        $post['VERSION']		= $this->version;
        $post['STAMP']			= $this->stamp;
        $post['AMOUNT']			= $this->amount;
        $post['REFERENCE']		= $this->reference;
        $post['MESSAGE']		= $this->message;
        $post['LANGUAGE']		= $this->language;
        $post['MERCHANT']		= $this->merchant;
        $post['RETURN']			= $this->return;
        $post['CANCEL']			= $this->cancel;
        $post['REJECT']			= $this->reject;
        $post['DELAYED']		= $this->delayed;
        $post['COUNTRY']		= $this->country;
        $post['CURRENCY']		= $this->currency;
        $post['DEVICE']			= $this->device;
        $post['CONTENT']		= $this->content;
        $post['TYPE']			= $this->type;
        $post['ALGORITHM']		= $this->algorithm;
        $post['DELIVERY_DATE']	= $this->delivery_date;
        $post['FIRSTNAME']		= $this->firstname;
        $post['FAMILYNAME']		= $this->familyname;
        $post['ADDRESS']		= $this->address;
        $post['POSTCODE']		= $this->postcode;
        $post['POSTOFFICE']		= $this->postoffice;
        $post['MAC']			= $mac;

        if ($this->device == 10) {
            $post['EMAIL'] = $this->email;
        }

        return $post;
    }

    /*
     * returns payment information in XML
     */
    public function getCheckoutXML($data)
    {
        $this->device = "10";

        return $this->sendPost($this->getCheckoutObject($data));
    }

    private function sendPost($post)
    {
        $options = array(
                CURLOPT_POST 		=> 1,
                CURLOPT_HEADER 		=> 0,
                CURLOPT_URL 		=> 'https://payment.checkout.fi',
                CURLOPT_FRESH_CONNECT 	=> 1,
                CURLOPT_RETURNTRANSFER 	=> 1,
                CURLOPT_FORBID_REUSE 	=> 1,
                CURLOPT_TIMEOUT 	=> 20,
                CURLOPT_POSTFIELDS 	=> http_build_query($post)
        );

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function validateCheckout($data)
    {
        $generatedMac = strtoupper(md5("{$this->password}&{$data['VERSION']}&{$data['STAMP']}&{$data['REFERENCE']}&{$data['PAYMENT']}&{$data['STATUS']}&{$data['ALGORITHM']}"));

        if ($data['MAC'] == $generatedMac) {
            return true;
        } else {
            return false;
        }
    }

    public function isPaid($status)
    {
        if (in_array($status, array(2, 4, 5, 6, 7, 8, 9, 10))) {
            return true;
        } else {
            return false;
        }
    }

}
