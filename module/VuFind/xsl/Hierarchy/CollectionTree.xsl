<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl">
 
    <xsl:template match="/">
        <root>
        <xsl:apply-templates select="//root">
        </xsl:apply-templates>
        </root>
    </xsl:template>

    <xsl:template match="item">
        <xsl:variable name="id" select="@id" />
        <item>
          <content>
              <name class="JSTreeID"><xsl:value-of select="$id"/></name>
              <name href="{$baseURL}/Collection/{$collectionID}/HierarchyTree?recordID={$id}#tabnav" title="{$titleText}">
                  <xsl:value-of select="./content/name" />
              </name>
          </content>
          <xsl:apply-templates select="item"/>
      </item>
    </xsl:template>
    
</xsl:stylesheet>
