<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0" xmlns:php="http://php.net/xsl">

    <xsl:template match="index">
    	<!-- just an placeholder layout -->
        <h1>Charge $10 with Stripe</h1>
        <!-- to display errors returned by createToken -->
        <span class="payment-errors"><xsl:value-of select="error"/></span>
        <span class="payment-success"><xsl:value-of select="success"/></span>

        <form action="/stripe" method="post" id="payment-form">
            <div class="form-row">
                <label>Card Number</label>
                <input type="text" size="20" autocomplete="off" class="card-number" />
            </div>
            <div class="form-row">
                <label>CVC</label>
                <input type="text" size="4" autocomplete="off" class="card-cvc" />
            </div>
            <div class="form-row">
                <label>Expiration (MM/YYYY)</label>
                <input type="text" size="2" class="card-expiry-month"/>
                <span> / </span>
                <input type="text" size="4" class="card-expiry-year"/>
            </div>
            <button type="submit" class="submit-button">Submit Payment</button>
        </form>

        <!-- Make sure jQuery is included -->
        <script type="text/javascript" src="https://js.stripe.com/v1/"></script>
        <script type="text/javascript">
            // this identifies your website in the createToken call below
            Stripe.setPublishableKey('<xsl:value-of select="publicKey"/>');

            <![CDATA[
            function stripeResponseHandler(status, response) {
                if (response.error) {
                    // re-enable the submit button
                    jQuery('.submit-button').removeAttr("disabled");
                    // show the errors on the form
                    jQuery(".payment-errors").html(response.error.message);
                } else {
                    var form$ = jQuery("#payment-form");
                    // token contains id, last4, and card type
                    var token = response['id'];
                    // insert the token into the form so it gets submitted to the server
                    form$.append("<input type='hidden' name='stripeToken' value='" + token + "' />");
                    // and submit
                    form$.get(0).submit();
                }
            }

            jQuery(document).ready(function() {
                jQuery("#payment-form").submit(function(event) {
                    // disable the submit button to prevent repeated clicks
                    jQuery('.submit-button').attr("disabled", "disabled");

                    // createToken returns immediately - the supplied callback submits the form if there are no errors
                    Stripe.createToken({
                        number: jQuery('.card-number').val(),
                        cvc: jQuery('.card-cvc').val(),
                        exp_month: jQuery('.card-expiry-month').val(),
                        exp_year: jQuery('.card-expiry-year').val()
                    }, stripeResponseHandler);
                    return false; // submit from callback
                });
            });
            ]]>
        </script>
    </xsl:template>

    <xsl:template match="charge">

    </xsl:template>

</xsl:stylesheet>
