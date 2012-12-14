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
          <xsl:variable name="isCollection" select="@isCollection" />
          <xsl:attribute name="class">
          <xsl:if test="$isCollection = 'true'">hierarchy </xsl:if>
          <xsl:choose>
            <xsl:when test="$isCollection = 'true' and $recordID = $id">currentHierarchy</xsl:when>
            <xsl:when test="$isCollection != 'true' and $recordID = $id">currentRecord</xsl:when>
          </xsl:choose>
          </xsl:attribute>
        <xsl:variable name="baseModule">
          <xsl:choose>
            <xsl:when test="$context = 'Record'">
                <xsl:choose>
                    <xsl:when test="$isCollection = 'true'">Collection</xsl:when>
                    <xsl:otherwise>Record</xsl:otherwise>
                </xsl:choose>
            </xsl:when>
            <xsl:otherwise>Collection</xsl:otherwise>
          </xsl:choose>
        </xsl:variable>
          <a href="{$baseURL}/{$baseModule}/{$id}/HierarchyTree?hierarchy={$collectionID}&amp;recordID={$id}#tree-{$id}" title="{$titleText}">
              <xsl:value-of select="./content/name" />
          </a>
          <xsl:apply-templates select="item"/>
      </li>
      </ul>
    </xsl:template>

</xsl:stylesheet>
