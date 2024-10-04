<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:zs="http://www.loc.gov/zing/srw/"
                xmlns:marc="http://www.loc.gov/MARC21/slim">
  <xsl:output method="xml" indent="yes"/>
  <xsl:template match="/">
    <ResultSet>
      <RecordCount><xsl:value-of select="//zs:numberOfRecords"/></RecordCount>
      <xsl:call-template name="facet"/>
      <xsl:call-template name="doc"/>
    </ResultSet>
  </xsl:template> 
    
  <xsl:template name="doc">
    <xsl:for-each select="//zs:records/zs:record">
      <xsl:copy-of select="./zs:recordData/marc:record"/>
    </xsl:for-each>
  </xsl:template>
  
  <xsl:template name="facet">
    <Facets>
      <xsl:for-each select="//lst[@name='facet_fields']/lst">
        <Cluster>
          <xsl:attribute name="name"><xsl:value-of select="@name"/></xsl:attribute>
          <xsl:for-each select="./int">
            <xsl:variable name="elem" select="../@name"/>
            <item>
              <xsl:attribute name="count"><xsl:value-of select="."/></xsl:attribute>
              <xsl:value-of select="@name"/>
            </item>
          </xsl:for-each>
        </Cluster>
      </xsl:for-each>
    </Facets>
  </xsl:template>
  
</xsl:stylesheet>