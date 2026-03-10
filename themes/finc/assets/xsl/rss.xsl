<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="3.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
  <xsl:template match="/">
    <html xmlns="http://www.w3.org/1999/xhtml">
      <head>
        <title><xsl:value-of select="/rss/channel/title"/></title>
        <meta charset="UTF-8" />
        <style>
          body {
          font-family: Arial, sans-serif;
          margin: 20px;
          }
          h1 {
          color: #333;
          }
          h2 {
          color: #666;
          }
          a {
          color: #0066cc;
          text-decoration: none;
          }
          a:hover {
          text-decoration: underline;
          }
          p {
          margin-bottom: 20px;
          }
          .follow-button {
          display: inline-block;
          margin: 5px 10px;
          padding: 10px 15px;
          font-size: 14px;
          color: #fff;
          background-color: #007bff;
          border: none;
          border-radius: 5px;
          text-decoration: none;
          }
          .follow-button:hover {
          background-color: #0056b3;
          }
        </style>
      </head>
      <body>
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="70" viewBox="0 0 64 64">
          <circle cx="16" cy="55" r="8" fill="#000000"/>
          <path d="M8 32c17.67 0 32 14.33 32 32h-8c0-13.25-10.75-24-24-24v-8z" fill="#000000"/>
          <path d="M8 16c26.51 0 48 21.49 48 48h-8c0-22.09-17.91-40-40-40v-8z" fill="#000000"/>
        </svg>
        <!-- Feeder Follow Button -->
          <a class="follow-button" target="_blank">
            <xsl:attribute name="href">
              <xsl:text>https://feeder.co/discover/?url=</xsl:text>
              <xsl:value-of select="/rss/channel/link"/>
            </xsl:attribute>
            Feeder
          </a>

        <!-- Feedly Follow Button -->
          <a class="follow-button" target="_blank">
            <xsl:attribute name="href">
              <xsl:text>https://feedly.com/i/subscription/feed/</xsl:text>
              <xsl:value-of select="/rss/channel/link"/>
            </xsl:attribute>
            Feedly
          </a>

        <!-- Feedbin Follow Button -->
          <a class="follow-button" target="_blank">
            <xsl:attribute name="href">
              <xsl:text>https://feedbin.com/?subscribe=</xsl:text>
              <xsl:value-of select="/rss/channel/link"/>
            </xsl:attribute>
            Feedbin
          </a>
        <!-- Inoreader Follow Button -->
          <a class="follow-button" target="_blank">
            <xsl:attribute name="href">
              <xsl:text>https://www.inoreader.com/feed/</xsl:text>
              <xsl:value-of select="/rss/channel/link"/>
            </xsl:attribute>
            Inoreader
          </a>

        <!-- Newsblur Follow Button -->
          <a class="follow-button" target="_blank">
            <xsl:attribute name="href">
              <xsl:text>https://newsblur.com/?url=</xsl:text>
              <xsl:value-of select="/rss/channel/link"/>
            </xsl:attribute>
            Newsblur
          </a>
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
