<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0" xmlns:php="http://php.net/xsl">

    <xsl:template match="index">
        <!-- Just an example template, use layout overrides to style it :-) -->
        <div id="banks">
            <xsl:for-each select="checkoutXMLBanks">
                <form action="{url}" method='post' onclick="jQuery(this).submit();">
                    <div class="bankIcon">
                        <xsl:for-each select="values">
                            <input type="hidden" name="{key}" value="{value}" />
                        </xsl:for-each>
                        <span><input type="image" src="{icon}" /></span>
                        <div><xsl:value-of select="name"/></div>
                    </div>
                </form>
            </xsl:for-each>
        </div>
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
