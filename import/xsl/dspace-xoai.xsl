<!-- 
    The contents of this file are subject to the license and copyright
    detailed in the LICENSE and NOTICE files at the root of the source
    tree and available online at
    http://www.dspace.org/license/
	Developed by DSpace @ Lyncode <dspace@lyncode.com>
 --><xsl:stylesheet xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0" xmlns:php="http://php.net/xsl" xsi:schemaLocation="http://www.w3.org/2005/Atom http://www.kbcafe.com/rss/atom.xsd.xml">
   <xsl:output method="xml" indent="yes" encoding="utf-8"/>
   <xsl:param name="collection">DSpace</xsl:param>
   <xsl:param name="urlPrefix">http</xsl:param>
   <xsl:param name="geographic">false</xsl:param>
   <xsl:param name="id_tag_name">identifier</xsl:param>
   <xsl:param name="change_tracking_core">biblio</xsl:param>
   <xsl:param name="change_tracking_date_tag_name"></xsl:param>
   <xsl:param name="workKey_include_regEx"/>
   <xsl:param name="workKey_exclude_regEx"/>
   <xsl:param name="workKey_transliterator_rules">:: NFD; :: lower; :: Latin; :: [^[:letter:] [:number:]] Remove; :: NFKC;</xsl:param>
   <xsl:template match="/">
      <add>
         <doc>
	    <!-- RECORD ID -->
	    <field name="id">
               <xsl:value-of select="//*[name()=$id_tag_name]"/>
            </field>
            <!-- RECORD FORMAT -->
	    <field name="record_format">dspace</field>
            <!-- INSTITUTION -->
	    <field name="institution">
		<xsl:if test="//*[@name='publisher']">
	            <xsl:value-of select="//*[@name='publisher']/*/*[@name='value']"/>
	        </xsl:if>
            </field>
            <!-- COLLECTION -->
            <field name="collection">
               <xsl:value-of select="$collection"/>
            </field>
            <!-- TITLE -->
            <xsl:if test="//*[@name='title']">
               <field name="title">
	          <xsl:value-of select="//*[@name='title']/*/*[@name='value']"/>
               </field>
               <field name="title_short">
		  <xsl:value-of select="//*[@name='title']/*/*[@name='value'][normalize-space()]"/>
               </field>
               <field name="title_full">
		  <xsl:value-of select="//*[@name='title']/*/*[@name='value'][normalize-space()]"/>
               </field>
               <field name="title_sort">
		  <xsl:value-of select="php:function('VuFind::stripArticles', string(//*[@name='title']/*/*[@name='value'][normalize-space()]))"/>
               </field>
            </xsl:if>
            <!-- AUTHOR -->
	    <xsl:for-each select="//*[@name='dc']/*[@name='contributor']/*[@name='author']/*/*[@name='value']">
               <xsl:if test="normalize-space()">
                  <field name="author">
		      <xsl:value-of select="normalize-space()"/>
                  </field>
                  <!-- use first author value for sorting -->
                  <xsl:if test="position()=1">
                     <field name="author_sort">
                        <xsl:value-of select="normalize-space()"/>
                     </field>
                  </xsl:if>
               </xsl:if>
            </xsl:for-each>
            <!-- CO AUTHOR -->
	    <xsl:for-each select="//*[@name='contributor']/*[@name='advisor']/*[@name='none']/*[@name='value']">
               <field name="author2">

                  <xsl:value-of select="normalize-space(.)"/>
               </field>
            </xsl:for-each>
            <!-- PUBLISHDATE -->
            <xsl:if test="//*[@name='date']">
               <field name="publishDate">
                  <xsl:value-of select="substring(//*[@name='date']/*[@name='accessioned']/*[@name='none']/*[@name='value'], 1, 4)"/>
               </field>
               <field name="publishDateSort">
                  <xsl:value-of select="substring(//*[@name='date']/*[@name='accessioned']/*[@name='none']/*[@name='value'], 1, 4)"/>
               </field>
            </xsl:if>
            <!-- Publisher -->
            <xsl:if test="//*[@name='publisher']">
               <field name="publisher">
		   <xsl:value-of select="//*[@name='publisher']/*/*[@name='value']"/> 
		   <xsl:if test="//*[@name='dc']/*[@name='relation']/*[@name='uri']">
                       # <xsl:value-of select="//*[@name='dc']/*[@name='relation']/*[@name='uri']" />
		   </xsl:if>
	        </field>
            </xsl:if>
	    <xsl:if test="//*[@name='description']/*[@name='abstract']/*/*[@name='value']">
               <field name="description">
		       <xsl:for-each select="//*[@name='description']/*[@name='abstract']/*/*[@name='value']">
                     <xsl:value-of select="concat(., '&#10;')"/>
                  </xsl:for-each>
               </field>
            </xsl:if>
	    <!-- LANGUAGE -->
            <xsl:for-each select="//*[@name='language']/*[@name='iso']">
                <xsl:if test="string-length() > 0">
                    <field name="language">
                        <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(.)), 'language_map_iso639-1.properties')"/>
                    </field>
                </xsl:if>
            </xsl:for-each>
	    <!-- SUBJECT -->
            <xsl:for-each select="//*[@name='subject']/*/*[@name='value']">
               <field name="topic">
                  <xsl:value-of select="normalize-space(.)"/>
               </field>
               <field name="topic_facet">
                  <xsl:value-of select="normalize-space(.)"/>
               </field>
            </xsl:for-each>
            <!-- Type -->
	    <xsl:if test="//*[@name='type']">
               <field name="format">
		   <xsl:value-of select="//*[@name='type']"/>
               </field>
            </xsl:if>
	    <!-- Rights - stored in the rights solr field for want of a better place -->
	    <xsl:for-each select="//*[@name='dc']/*[@name='rights']">
	       <field name="edition">
		   <xsl:value-of select="//*[@name='dc']/*[@name='rights']"/>
	       </field>
            </xsl:for-each>
            <!-- Original URL -->
	    <xsl:for-each select="//*[text()='ORIGINAL']/following-sibling::*[@name='bitstreams']/*[@name='bitstream']">
               <field name="url">
		   <xsl:value-of select="./*[@name='url']"/> # <xsl:value-of select="./*[@name='name']" />

	       </field>
            </xsl:for-each>
            <!-- Thumbnail -->
            <xsl:if test="//*[text()='THUMBNAIL']">
               <field name="thumbnail">
                  <xsl:value-of select="//*[text()='THUMBNAIL']/following-sibling::*[@name='bitstreams']/*[@name='bitstream']/*[@name='url']"/>
               </field>
            </xsl:if>
            <!-- FULLTEXT -->
	    <xsl:if test="//*[text()='TEXT']/following-sibling::*[@name='bitstreams']/*[@name='bitstream']/*[@name='url']">
              <field name="fulltext">
	          <xsl:value-of select="php:function('VuFind::harvestWithParser', string(//*[text()='TEXT']/following-sibling::*[@name='bitstreams']/*[@name='bitstream']/*[@name='url']) )"/>
              </field>
            </xsl:if>
            <!-- LICENSE -->
            <xsl:if test="//*[text()='LICENSE']/following-sibling::*[@name='bitstreams']/*[@name='bitstream']/*[@name='url']">
              <field name="physical">
                  <xsl:value-of select="php:function('VuFind::harvestWithParser', string(//*[text()='LICENSE']/following-sibling::*[@name='bitstreams']/*[@name='bitstream']/*[@name='url']) )"/>
              </field>
            </xsl:if>
            <!-- Change Tracking (note that the identifier selected below must match the id field above)-->
            <xsl:if test="$change_tracking_date_tag_name">
	        <field name="first_indexed">
                   <xsl:value-of select="php:function('VuFind::getFirstIndexed', $change_tracking_core, normalize-space(string(*[name()=$id_tag_name])), normalize-space(*[name()=$change_tracking_date_tag_name]))" />
                </field>
                <field name="last_indexed">
                  <xsl:value-of select="php:function('VuFind::getLastIndexed', $change_tracking_core, normalize-space(string(*[name()=$id_tag_name])), normalize-space(*[name()=$change_tracking_date_tag_name]))" />
	        </field>
            </xsl:if>
          </doc>
      </add>
   </xsl:template>
</xsl:stylesheet>
