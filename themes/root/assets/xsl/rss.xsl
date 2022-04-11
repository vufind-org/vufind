<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="3.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
  <xsl:template match="/">
    <html xmlns="http://www.w3.org/1999/xhtml">
      <head>
        <title><xsl:value-of select="/rss/channel/title"/></title>
        <meta charset="UTF-8" />
      </head>
      <body>
        <h1><xsl:value-of select="/rss/channel/title"/></h1>
        <h2><xsl:value-of select="/rss/channel/description"/></h2>
        <xsl:for-each select="/rss/channel/atom:link">
          <a>
            <xsl:attribute name="href">
              <xsl:value-of select="@href" />
            </xsl:attribute>
            <xsl:value-of select="@title" />
          </a><br />
        </xsl:for-each>
        <xsl:for-each select="/rss/channel/item">
          <p>
            <b>
              <a>
                <xsl:attribute name="href">
                  <xsl:value-of select="link"/>
                </xsl:attribute>
                <xsl:value-of select="title"/>
              </a>
            </b><br />
            <xsl:if test="author">
              <xsl:value-of select="author" /><br />
            </xsl:if>
            <xsl:if test="dc:format">
              <xsl:value-of select="dc:format" /><br />
            </xsl:if>
            <xsl:if test="dc:date">
              <xsl:value-of select="dc:date" /><br />
            </xsl:if>
          </p>
        </xsl:for-each>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
