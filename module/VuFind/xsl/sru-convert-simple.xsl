<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:zs="http://www.loc.gov/zing/srw/"
                xmlns:marc="http://www.loc.gov/MARC21/slim">
  <xsl:output method="xml" indent="yes"/>
  <xsl:template match="/">
    <ResultSet>
      <RecordCount><xsl:value-of select="//zs:numberOfRecords"/></RecordCount>
      <xsl:call-template name="doc"/>
      <xsl:call-template name="facet"/>
    </ResultSet>
  </xsl:template> 
    
  <xsl:template name="doc">
    <xsl:for-each select="//zs:records/zs:record">
      <xsl:copy-of select="./zs:recordData/marc:record"/>
    </xsl:for-each>
  </xsl:template>
  
  <xsl:template name="facet">
    <Facets>
      <Databases>
        <xsl:for-each select="//zs:extraResponseData/resultCountForDatabase">
          <Database>
            <xsl:copy-of select="./*" />
          </Database>
        </xsl:for-each>
      </Databases>
    </Facets>
  </xsl:template>
  
</xsl:stylesheet>