<!-- XSLT to load sitemap entries into the custom class used for
     populating the special "website" Solr core -->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:php="http://php.net/xsl">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:template match="sitemap:urlset">
        <add>
            <xsl:for-each select="//sitemap:loc">
                <doc>
                    <xsl:value-of disable-output-escaping="yes" select="php:function('VuFindSitemap::getDocument', normalize-space(string(.)))"/>
                </doc>
            </xsl:for-each>
        </add>
    </xsl:template>
</xsl:stylesheet>
