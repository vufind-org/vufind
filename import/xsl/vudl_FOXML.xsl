<?xml version="1.0" encoding="utf-8"?>
<!--
Copyright (C) Villanova University 2012.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2,
as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
-->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:mets="http://www.loc.gov/METS/"
    xmlns:METS="http://www.loc.gov/METS/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:php="http://php.net/xsl"
    xmlns:xlink="http://www.w3.org/1999/xlink"
    xmlns:access="http://www.fedora.info/definitions/1/0/access/"
    xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
    xmlns:sparql="http://www.w3.org/2001/sw/DataAccess/rf1/result"
    xmlns:fedora-model="info:fedora/fedora-system:def/model#"
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:rel="info:fedora/fedora-system:def/relations-external#"
    xmlns:foxml="info:fedora/fedora-system:def/foxml#"
    xmlns:exsl="http://exslt.org/common"
    xmlns:math="http://exslt.org/math"
    exclude-result-prefixes="exsl"
    >
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>

    <xsl:param name="institution"></xsl:param>
    <xsl:param name="collection"></xsl:param>
    <xsl:param name="track_changes"></xsl:param>
    <xsl:param name="solr_core"></xsl:param>

    <xsl:param name="fedoraURL"></xsl:param>
    <xsl:param name="fedoraPort"></xsl:param>

    <xsl:param name="workKey_include_regEx"></xsl:param>
    <xsl:param name="workKey_exclude_regEx"></xsl:param>
    <xsl:param name="workKey_transliterator_rules">:: NFD; :: lower; :: Latin; :: [^[:letter:] [:number:]] Remove; :: NFKC;</xsl:param>

    <xsl:template match="/">



        <xsl:variable name="PID" select="//dc:identifier"/>

        <xsl:variable name="DC" select="document(concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/datastreams/DC/content'))"/>

        <xsl:variable name="RELS-EXT" select="document(concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/datastreams/RELS-EXT/content'))"/>
        <!-- <xsl:variable name="modelType" select="substring-after($RELS-EXT//fedora-model:hasModel[last()]/@rdf:resource, 'info:fedora/')"/> -->
        <!--
        <xsl:variable name="objectInfo" select="document(concat($fedoraURL, '/fedora/objects/', $PID, '?format=xml'))"/>
        -->
        <!-- -->
        <xsl:variable name="listDatastreams" select="document(concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/datastreams?format=xml'))"/>

        <add>
            <doc>
                <!-- ID -->
                <field name="id">
                    <xsl:value-of select="$PID"/>
                </field>

		            <xsl:for-each select="$RELS-EXT//fedora-model:hasModel">
    		            <field name="modeltype_str_mv">
    		                <xsl:value-of select="substring-after(./@rdf:resource, 'info:fedora/')"/>
    		            </field>
		            </xsl:for-each>

		            <!-- Hierarchy stuff -->
                <xsl:if test="//foxml:datastream[@ID = 'PARENT-LIST-RAW']">

                    <xsl:variable name="PARENT-LIST-RAW" select="document(concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/datastreams/PARENT-LIST-RAW/content'))"/>
                    <!-- <xsl:variable name="PARENT-LIST" select="document(concat($fedoraURL, '/fedora/objects/', $PID, '/datastreams/PARENT-LIST/content'))"/> -->
                    <!-- -->
                    <xsl:variable name="PARENT-LIST">
                        <parent PID="{$PID}">
                            <xsl:for-each select="$PARENT-LIST-RAW//sparql:child[@uri=concat('info:fedora/', $PID)]">
                                <xsl:variable name="parentURI_template" select="../sparql:parent/@uri"/>
                                <xsl:variable name="parentName_template" select="../sparql:parentTitle"/>
                                <xsl:call-template name="parent">
                                    <xsl:with-param name="parentURI_template" select="$parentURI_template"/>
                                    <xsl:with-param name="parentName_template" select="$parentName_template"/>
                                </xsl:call-template>
                            </xsl:for-each>
                        </parent>
                    </xsl:variable>

                    <!-- <xsl:copy-of select="$PARENT-LIST"/> -->

                    <field name="hierarchytype"/> <!-- -->

                    <xsl:if test="$RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:FolderCollection']">
                        <field name="is_hierarchy_id"><xsl:value-of select="$PID"/></field>
                        <field name="is_hierarchy_title"><xsl:value-of select="$DC//dc:title[1]"/></field>
                    </xsl:if>

                    <field name="has_order_str">
                        <xsl:choose>
                            <xsl:when test="$listDatastreams//access:datastream[@dsid='STRUCTMAP']">
                                <xsl:value-of select="string('yes')"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:value-of select="string('no')"/>
                            </xsl:otherwise>
                        </xsl:choose>
                    </field>

                    <!-- <xsl:copy-of select="$PARENT-LIST-RAW"/> -->

                    <xsl:for-each select="$PARENT-LIST-RAW//sparql:child[@uri=concat('info:fedora/', $PID)]/parent::*">

                        <!-- <xsl:variable name="parentURI" select="$PARENT-LIST-RAW//sparql:child[@uri=concat('info:fedora/', $PID)]/../sparql:parent/@uri"/> -->
                        <xsl:variable name="parentURI" select="./sparql:parent/@uri"/>


                        <xsl:variable name="parentResource" select="document(concat($fedoraURL, '/getParentResource.php?PID=', $PID))"/>
                        <!--
                        <as>
                        <xsl:copy-of select="$parentResource"/>
                        </as>
                        <xsl:variable name="parentPID" select="substring-after($parentURI, 'info:fedora/')"/>
                        <xsl:variable name="parentLabel" select="./sparql:parentTitle"/>
                        -->

                        <xsl:variable name="realParentPID" select="substring-after($parentURI, 'info:fedora/')"/>

                        <xsl:variable name="parentPID">
                            <xsl:choose>
                                <xsl:when test="$RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:DataModel']">
                                    <xsl:value-of select="normalize-space($parentResource//PID)"/>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="substring-after($parentURI, 'info:fedora/')"/>
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:variable>

                        <xsl:variable name="parentLabel">
                            <xsl:choose>
                                <xsl:when test="$RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:DataModel']">
                                    <xsl:value-of select="$parentResource//label"/>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="./sparql:parentTitle"/>
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:variable>


                        <xsl:variable name="parentDatastreams" select="document(concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $realParentPID, '/datastreams?format=xml'))"/>
                        <xsl:variable name="parentRELS-EXT" select="document(concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $realParentPID, '/datastreams/RELS-EXT/content'))"/>


                        <!-- <xsl:if test="$parentDatastreams//access:datastream[@dsid='STRUCTMAP']"> -->
                        <!-- TODO: this needs to check to see if the parent is a collection -->
                        <!-- <xsl:if test="$RELS-EXT//fedora-model:hasModel[@rdf:resource = 'info:fedora/vudl-system:CollectionModel']"> -->
                        <xsl:if test="$parentRELS-EXT//fedora-model:hasModel[@rdf:resource = 'info:fedora/vudl-system:CollectionModel']">

                            <xsl:variable name="parentSTRUCTMAP">
                                <xsl:choose>
                                    <xsl:when test="$parentDatastreams//access:datastream[@dsid='STRUCTMAP']">
                                        <xsl:copy-of select="document(concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $realParentPID, '/datastreams/STRUCTMAP/content'))"/>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <METS:structMap/>
                                    </xsl:otherwise>
                                </xsl:choose>
                            </xsl:variable>

                            <xsl:variable name="hierarchy_top_id">
                              <!-- <xsl:value-of select="substring-after($hierarchy_top_uri, 'info:fedora/')"/> -->
                              <xsl:choose>
                                  <xsl:when test="$realParentPID = 'vudl:3'">
                                      <xsl:value-of select="$PID"/>
                                  </xsl:when>
                                  <xsl:otherwise>
                                      <xsl:value-of select="exsl:node-set($PARENT-LIST)//parent[@PID=$realParentPID]//parent[@PID='vudl:3']/../@PID"/>
                                  </xsl:otherwise>
                              </xsl:choose>

                            </xsl:variable>

                            <xsl:variable name="hierarchy_top_title">
                            <xsl:choose>
                              <xsl:when test="$parentPID = 'vudl:3'">
                                  <xsl:value-of select="$DC//dc:title[1]"/>
                              </xsl:when>
                              <xsl:when test="$parentPID = $PID">
                                  <xsl:value-of select="$DC//dc:title[1]"/>
                              </xsl:when>
                              <xsl:otherwise>
                                <xsl:value-of select="$PARENT-LIST-RAW//sparql:parent[@uri = concat('info:fedora/', $hierarchy_top_id)]/../sparql:parentTitle"/>
                              </xsl:otherwise>
                            </xsl:choose>
                            </xsl:variable>

                            <field name="hierarchy_top_id">
                              <xsl:value-of select="$hierarchy_top_id"/>
                            </field>




                            <field name="hierarchy_top_title">
                              <xsl:value-of select="$hierarchy_top_title"/>
                            </field>

                            <field name="hierarchy_parent_id"><xsl:value-of select="$parentPID"/></field>
                            <field name="hierarchy_parent_title"><xsl:value-of select="$parentLabel"/></field>

                            <!-- -->
                            <field name="hierarchy_sequence">
                                <!--
                                <xsl:if test="exsl:node-set($parentSTRUCTMAP)//METS:fptr[@FILEID=$PID]">
                                    <xsl:value-of select="exsl:node-set($parentSTRUCTMAP)//METS:fptr[@FILEID=$PID]/../@ORDER"/>
                                </xsl:if>
                                -->

                                <xsl:choose>
                                    <xsl:when test="exsl:node-set($parentSTRUCTMAP)//METS:fptr[@FILEID=$PID]">
                                        <xsl:value-of select="exsl:node-set($parentSTRUCTMAP)//METS:fptr[@FILEID=$PID]/../@ORDER"/>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:value-of select="string('0000000000')"/>
                                    </xsl:otherwise>
                                </xsl:choose>

                            </field>

                            <xsl:if test="position() = 1">
                            <!-- This is what we are collapsing on -->
                            <field name="hierarchy_first_parent_id_str">
                                <xsl:choose>
                                  <xsl:when test="$RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:DataModel']">
                                      <xsl:value-of select="$parentPID"/>
                                  </xsl:when>
                                  <xsl:otherwise>
                                    <xsl:value-of select="$PID"/>
                                  </xsl:otherwise>
                                </xsl:choose>
                            </field>
                            </xsl:if>

                            <!-- -->
                            <xsl:if test="position() = 1">
                            <field name="hierarchy_sequence_sort_str">
                                <xsl:choose>
                                    <xsl:when test="exsl:node-set($parentSTRUCTMAP)//METS:fptr[@FILEID=$PID]">
                                        <xsl:variable name="sequence_sort_str" select="exsl:node-set($parentSTRUCTMAP)//METS:fptr[@FILEID=$PID]/../@ORDER"/>
                                        <xsl:value-of select="php:function('str_pad', normalize-space(string($sequence_sort_str)), '10', '0', 0)"/>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:value-of select="string('0000000000')"/>
                                    </xsl:otherwise>
                                </xsl:choose>
                            </field>
                            </xsl:if>

                            <!--
                            <xsl:if test="position() = 1">
                              <xsl:variable name="sequence_sort_str" select="$parentSTRUCTMAP//METS:fptr[@FILEID=$PID]/../@ORDER"/>
                              <field name="hierarchy_sequence_sort_str">
                                <xsl:value-of select="php:function('str_pad', normalize-space(string($sequence_sort_str)), '10', '0', 0)"/>
                              </field>
                            </xsl:if>
                            -->
                            <!--
                            hierarchy_browse
                            title{{{_ID_}}}id
                            -->
                            <!-- -->
                            <field name="hierarchy_browse">
                              <xsl:value-of select="concat($parentLabel, '{{{_ID_}}}', $parentPID)"/>
                            </field>



                            <!-- flattens all parents
                            <xsl:for-each select="$PARENT-LIST-RAW//sparql:result">
                              <xsl:variable name="tempParentURI" select="./sparql:parent/@uri"/>
                              <xsl:variable name="tempParentPID" select="substring-after($tempParentURI, 'info:fedora/')"/>
                              <xsl:if test="not(./sparql:parent[@uri = 'info:fedora/vudl:1']) and not(./sparql:parent[@uri = 'info:fedora/vudl:3'])">
                                <field name="hierarchy_all_parents_str_mv">
                                  <xsl:value-of select="normalize-space(string($tempParentPID))"/>
                                </field>
                              </xsl:if>
                            </xsl:for-each>
                            -->

                        </xsl:if>

                    </xsl:for-each>



                </xsl:if>

                <!-- CHANGE TRACKING DATES -->
                <xsl:if test="$track_changes != 0">
                    <field name="first_indexed">
                        <xsl:value-of select="//foxml:property[@NAME='info:fedora/fedora-system:def/model#createdDate']/@VALUE"/>
                    </field>
                    <field name="last_indexed">
                        <xsl:value-of select="//foxml:property[@NAME='info:fedora/fedora-system:def/view#lastModifiedDate']/@VALUE"/>
                    </field>
                </xsl:if>

                <!-- RECORD FORMAT -->
                <field name="record_format">vudl</field>

                <!-- FULLRECORD -->
                <field name="fullrecord">
                    &lt;root&gt;
                    &lt;url&gt;
                    <xsl:value-of select="concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/DC/content')"/>
                    &lt;/url&gt;
                    &lt;thumbnail&gt;
                    <xsl:value-of select="concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/datastreams/THUMBNAIL/content')"/>
                    &lt;/thumbnail&gt;
                    &lt;/root&gt;
                    <!-- <xsl:value-of select="string(//METS:dmdSec/METS:mdRef/@href)"/> -->
                </field>

                <!-- ALLFIELDS -->
                <field name="allfields">
                    <xsl:value-of select="normalize-space(string($DC//oai_dc:dc))"/>
                </field>

                <!-- INSTITUTION -->
                <field name="institution">
                    <xsl:value-of select="$institution" />
                </field>

                <!-- COLLECTION -->
                <field name="collection">
                    <xsl:value-of select="$collection" />
                </field>

                <!-- LANGUAGE -->
                <xsl:if test="$DC//dc:language">

                    <xsl:if test="string-length($DC//dc:language[1]) > 0">
                        <field name="dc_language_str">
                            <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string($DC//dc:language[1])), 'language_map_iso639-1.properties')"/>
                        </field>
                    </xsl:if>

                    <xsl:for-each select="$DC//dc:language">
                        <xsl:if test="string-length() > 0">
                            <field name="language">
                                <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(.)), 'language_map_iso639-1.properties')"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- Series / dc:relation -->
                <xsl:if test="$DC//dc:relation">

                    <xsl:if test="string-length($DC//dc:relation[1]) > 0">
                        <field name="dc_relation_str">
                            <xsl:value-of select="$DC//dc:relation[1]"/>
                        </field>
                    </xsl:if>

                    <xsl:for-each select="$DC//dc:relation">
                        <xsl:if test="string-length() > 0">
                            <field name="series">
                                <xsl:value-of select="normalize-space()"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- FORMAT
                <field name="format">Online</field>
                -->

                <!-- AUTHOR -->
                <xsl:if test="$DC//dc:creator">
                    <xsl:for-each select="$DC//dc:creator">
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
                </xsl:if>

                <xsl:if test="$DC//dc:contributor">
                    <xsl:for-each select="$DC//dc:contributor">
                        <xsl:if test="normalize-space()">
                            <field name="author2">
                                <xsl:value-of select="normalize-space()"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- TOPIC -->
                <xsl:if test="$DC//dc:subject">
                    <field name="topic_str">
                        <xsl:value-of select="$DC//dc:subject[1]"/>
                    </field>

                    <xsl:for-each select="$DC//dc:subject">
                        <field name="topic"><xsl:value-of select="normalize-space()"/></field>
                    </xsl:for-each>
                </xsl:if>

                <!-- TOPIC_FACET -->
                <xsl:if test="$DC//dc:subject">
                    <xsl:for-each select="$DC//dc:subject">
                        <field name="topic_facet"><xsl:value-of select="normalize-space()"/></field>
                    </xsl:for-each>
                </xsl:if>

                <!-- TOPIC_STR_MV (for autocomplete) -->
                <xsl:if test="$DC//dc:subject">
                    <xsl:for-each select="$DC//dc:subject">
                        <field name="topic_str_mv"><xsl:value-of select="normalize-space()"/></field>
                    </xsl:for-each>
                </xsl:if>

                <!-- TITLE -->
                <xsl:if test="$DC//dc:title[normalize-space()]">

                    <field name="dc_title_str">
                        <xsl:value-of select="$DC//dc:title[1]"/>
                    </field>

                    <field name="title">
                        <xsl:value-of select="$DC//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_short">
                        <xsl:value-of select="$DC//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_full">
                        <xsl:value-of select="$DC//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_sort">
                        <xsl:value-of select="php:function('VuFind::titleSortLower', php:function('VuFind::stripArticles', string($DC//dc:title[normalize-space()])))"/>
                    </field>

                    <!-- title_alt / dc:titel[gt 1] -->
                    <xsl:for-each select="$DC//dc:title">
                        <xsl:if test="position() &gt; 1">
                            <field name="title_alt">
                                <xsl:value-of select="normalize-space()"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>


                <!-- Format -->
                <xsl:for-each select="$DC//dc:format">
                    <field name="format">
                        <xsl:value-of select="."/>
                    </field>
                </xsl:for-each>

                <!-- DESCRIPTION -->
                <xsl:if test="$DC//dc:description[normalize-space()]">
                    <field name="description">
                        <xsl:value-of select="$DC//dc:description[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHER -->
                <xsl:if test="$DC//dc:publisher[normalize-space()]">
                    <field name="publisher">
                        <xsl:value-of select="$DC//dc:publisher[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHERSTR -->
                <xsl:if test="$DC//dc:publisher[normalize-space()]">
                    <field name="publisher_str_mv">
                        <xsl:value-of select="$DC//dc:publisher[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHDATE -->

                <xsl:if test="$DC//dc:date[1]">
                    <xsl:variable name="strippedDate" select="substring($DC//dc:date[1], 1, 4)"/>

                    <xsl:if test="number($strippedDate) > 1000">
                        <field name="publishDate">
                            <xsl:value-of select="substring($DC//dc:date[1], 1, 4)"/>
                        </field>
                        <field name="publishDateSort">
                            <xsl:value-of select="substring($DC//dc:date[1], 1, 4)"/>
                        </field>
                    </xsl:if>

                    <field name="dc_date_str">
                        <xsl:value-of select="$DC//dc:date[1]"/>
                    </field>

                </xsl:if>

                <!-- FULL TEXT -->
                <xsl:if test="//foxml:datastream[@ID='OCR-DIRTY'] or $RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:PDFData'] or $RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:DOCData']">
                    <field name="fulltext">
                        <xsl:if test="//foxml:datastream[@ID='OCR-DIRTY']">
                            <xsl:value-of select="php:function('VuFind::harvestTextFile', concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/datastreams/OCR-DIRTY/content'))"/>
                        </xsl:if>
                        <xsl:if test="$RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:PDFData'] or $RELS-EXT//fedora-model:hasModel[@rdf:resource='info:fedora/vudl-system:DOCData']">
                            <xsl:value-of select="php:function('VuFind::harvestWithParser', concat($fedoraURL, ':', $fedoraPort, '/fedora/objects/', $PID, '/datastreams/MASTER/content'))"/>
                        </xsl:if>
                    </field>
                </xsl:if>

                <!-- Work Keys -->
                <xsl:for-each select="php:function('VuFindWorkKeys::getWorkKeys', '', $DC//dc:title[normalize-space()], php:function('VuFind::stripArticles', string($DC//dc:title[normalize-space()])), $DC//dc:creator, $workKey_include_regEx, $workKey_exclude_regEx, $workKey_transliterator_rules)/workKey">
                    <field name="work_keys_str_mv">
                        <xsl:value-of select="." />
                    </field>
                </xsl:for-each>
            </doc>
        </add>
    </xsl:template>

    <xsl:template name="parent">
        <xsl:param name="parentURI_template"/>
        <xsl:param name="parentName_template"/>
        <!-- <xsl:if test="substring-after($parentURI_template,'/') != 'vudl:1' and substring-after($parentURI_template,'/') != 'vudl:3'"> --> <!--   -->
            <parent uri="{$parentURI_template}" PID="{substring-after($parentURI_template,'/')}" name="{$parentName_template}">
                <xsl:for-each select="//sparql:child[@uri=$parentURI_template]">
                    <xsl:variable name="new_parentURI_template" select="../sparql:parent/@uri"/>
                    <xsl:variable name="new_parentName_template" select="../sparql:parentTitle"/>
                    <xsl:call-template name="parent">
                        <xsl:with-param name="parentURI_template" select="$new_parentURI_template"/>
                        <xsl:with-param name="parentName_template" select="$new_parentName_template"/>
                    </xsl:call-template>
                </xsl:for-each>
            </parent>
        <!-- </xsl:if> -->
    </xsl:template>

</xsl:stylesheet>
