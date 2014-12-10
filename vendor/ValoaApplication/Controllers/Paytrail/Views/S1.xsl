<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0" xmlns:php="http://php.net/xsl">

    <xsl:template match="index">
        <div class="container">
            <form action="https://payment.paytrail.com/" method="post" id="payment">
                <input name="MERCHANT_ID" type="hidden" value="{merchant_id}" />
                <input name="AMOUNT" type="hidden" value="{amount}" />
                <input name="ORDER_NUMBER" type="hidden" value="{order_number}" />
                <input name="REFERENCE_NUMBER" type="hidden" value="" />
                <input name="ORDER_DESCRIPTION" type="hidden" value="{order_description}" />
                <input name="CURRENCY" type="hidden" value="{currency}" />
                <input name="RETURN_ADDRESS" type="hidden" value="{successURL}" />
                <input name="CANCEL_ADDRESS" type="hidden" value="{cancelURL}" />
                <input name="PENDING_ADDRESS" type="hidden" value="" />
                <input name="NOTIFY_ADDRESS" type="hidden" value="{notifyURL}" />
                <input name="TYPE" type="hidden" value="S1" />
                <input name="CULTURE" type="hidden" value="{culture}" />
                <input name="PRESELECTED_METHOD" type="hidden" value="" />
                <input name="MODE" type="hidden" value="1" />
                <input name="VISIBLE_METHODS" type="hidden" value="" />
                <input name="GROUP" type="hidden" value="" />
                <input name="AUTHCODE" type="hidden" value="{checksum}" />
                <input type="submit" value="{php:function('\Webvaloa\Webvaloa::translate','GOTO_PAYMENT')}" />
            </form>
        </div>

        <script type="text/javascript" src="//payment.paytrail.com/js/payment-widget-v1.0.min.js"></script>
        <script type="text/javascript">
            SV.widget.initWithForm('payment', {
                charset:'UTF-8',
                height: <xsl:value-of select="height"/>,
                width: <xsl:value-of select="width"/>
            });
        </script>
    </xsl:template>

    <xsl:template match="success">
        <xsl:value-of select="php:function('\Webvaloa\Webvaloa::translate','PAYMENT_SUCCESS')" />
    </xsl:template>

    <xsl:template match="notify">
        <xsl:value-of select="php:function('\Webvaloa\Webvaloa::translate','PAYMENT_SUCCESS')" />
    </xsl:template>

    <xsl:template match="cancel">
        <xsl:value-of select="php:function('\Webvaloa\Webvaloa::translate','PAYMENT_FAIL')" />
    </xsl:template>

</xsl:stylesheet>
