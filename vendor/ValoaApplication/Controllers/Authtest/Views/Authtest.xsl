<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0" xmlns:php="http://php.net/xsl">

	<xsl:template match="index">
        <div class="container">
        	I am currently: <xsl:value-of select="status"/><br/><br/>
        </div>
	</xsl:template>

</xsl:stylesheet>
