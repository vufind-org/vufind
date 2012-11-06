<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl">

    <xsl:template match="/">
        <div id="treeList">
        <xsl:apply-templates select="//root">
        </xsl:apply-templates>
        </div>
    </xsl:template>

    <xsl:template match="item">
        <ul>
          <xsl:variable name="id" select="@id" />
          <li>
          <xsl:attribute name="id">tree-<xsl:value-of select="$id"/></xsl:attribute>
          <xsl:attribute name="class">
          <xsl:choose>
            <xsl:when test="$recordID = $id">currentRecord</xsl:when>
          </xsl:choose>
          </xsl:attribute>
        <xsl:variable name="baseModule">Record</xsl:variable>
          <a href="{$baseURL}/{$baseModule}/{$id}/HierarchyTree?hierarchy={$collectionID}&amp;recordID={$id}#tree-{$id}" title="{$titleText}">
              <xsl:value-of select="./content/name" />
          </a>
          <xsl:apply-templates select="item"/>
      </li>
      </ul>
    </xsl:template>

</xsl:stylesheet>
