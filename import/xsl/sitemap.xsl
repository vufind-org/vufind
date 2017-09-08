<!-- XSLT to load sitemap entries into the custom class used for
     populating the special "website" Solr core -->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:php="http://php.net/xsl">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:template match="sitemap:urlset">
        <add>
            <xsl:for-each select="//sitemap:url">
                <!-- We can't index without a loc element containing a URI! -->
                <xsl:if test="sitemap:loc">
                    <doc>
                        <!-- Pass the URI to PHP, which will use full-text
                             extraction and generate pre-built XML output;
                             see the VuFind\XSLT\Import\VuFindSitemap class. -->
                        <xsl:value-of disable-output-escaping="yes" select="php:function('VuFindSitemap::getDocument', normalize-space(string(sitemap:loc)))" />
                    </doc>
                </xsl:if>
            </xsl:for-each>
        </add>
    </xsl:template>
</xsl:stylesheet>
