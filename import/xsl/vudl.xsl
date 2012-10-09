<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:mets="http://www.loc.gov/METS/"
    xmlns:METS="http://www.loc.gov/METS/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:php="http://php.net/xsl"
    xmlns:xlink="http://www.w3.org/1999/xlink">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:param name="institution">My University</xsl:param>
    <xsl:param name="collection">Digital Library</xsl:param>
    <xsl:param name="track_changes">0</xsl:param>
    <xsl:param name="solr_core">biblio</xsl:param>
    <xsl:template match="METS:mets">
        <add>
            <doc>
                <!-- ID -->
                <field name="id">
                    <xsl:value-of select="//@OBJID"/>
                </field>

                <!-- CHANGE TRACKING DATES -->
                <xsl:if test="$track_changes != 0">
                    <field name="first_indexed">
                        <xsl:value-of select="php:function('VuFind::getFirstIndexed', $solr_core, string(//@OBJID), string(//METS:metsHdr/@LASTMODDATE))"/>
                    </field>
                    <field name="last_indexed">
                        <xsl:value-of select="php:function('VuFind::getLastIndexed', $solr_core, string(//@OBJID), string(//METS:metsHdr/@LASTMODDATE))"/>
                    </field>
                </xsl:if>

                <!-- RECORDTYPE -->
                <field name="recordtype">vudl</field>

                <!-- FULLRECORD -->
                <field name="fullrecord">
                    &lt;root&gt;
                    &lt;url&gt;
                    <xsl:value-of select="//METS:dmdSec/METS:mdRef/@href"/><xsl:value-of select="//METS:dmdSec/METS:mdRef/@xlink:href"/>
                    &lt;/url&gt;
                    &lt;thumbnail&gt;
                    <xsl:value-of select="//METS:fileSec/METS:fileGrp[@USE = 'THUMBNAIL']/METS:file[@ID = //METS:structMap/METS:div/METS:div[@TYPE = 'page_level']/METS:div[1]/METS:fptr/@FILEID]/METS:FLocat/@xlink:href"/>
                    &lt;/thumbnail&gt;
                    &lt;/root&gt;
                    <!-- <xsl:value-of select="string(//METS:dmdSec/METS:mdRef/@href)"/> -->
                </field>

                <!-- ALLFIELDS -->
                <field name="allfields">
                    <xsl:value-of select="normalize-space(string(//METS:mets))"/>
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
                <xsl:if test="//dc:language">
                    <xsl:for-each select="//dc:language">
                        <xsl:if test="string-length() > 0">
                            <field name="language">
                                <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(.)), 'language_map_iso639-1.properties')"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- FORMAT -->
                <field name="format">Online</field>

                <!-- AUTHOR -->
                <xsl:if test="//dc:creator">
                    <xsl:for-each select="//dc:creator">
                        <xsl:if test="normalize-space()">
                            <!-- author is not a multi-valued field, so we'll put
                                 first value there and subsequent values in author2.
                             -->
                            <xsl:if test="position()=1">
                                <field name="author">
                                    <xsl:value-of select="normalize-space()"/>
                                </field>
                                <field name="author-letter">
                                    <xsl:value-of select="normalize-space()"/>
                                </field>
                            </xsl:if>
                            <xsl:if test="position()>1">
                                <field name="author2">
                                    <xsl:value-of select="normalize-space()"/>
                                </field>
                            </xsl:if>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- TOPIC -->
                <xsl:if test="//dc:subject">
                    <xsl:for-each select="//dc:subject">
                        <field name="topic"><xsl:value-of select="normalize-space()"/></field>
                    </xsl:for-each>
                </xsl:if>

                <!-- TITLE -->
                <xsl:if test="//dc:title[normalize-space()]">
                    <field name="title">
                        <xsl:value-of select="//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_short">
                        <xsl:value-of select="//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_full">
                        <xsl:value-of select="//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_sort">
                        <xsl:value-of select="php:function('VuFind::stripArticles', string(//dc:title[normalize-space()]))"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHER -->
                <xsl:if test="//dc:publisher[normalize-space()]">
                    <field name="publisher">
                        <xsl:value-of select="//dc:publisher[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHDATE -->
                <xsl:if test="//dc:date">
                    <field name="publishDate">
                        <xsl:value-of select="substring(//dc:date, 1, 4)"/>
                    </field>
                    <field name="publishDateSort">
                        <xsl:value-of select="substring(//dc:date, 1, 4)"/>
                    </field>
                </xsl:if>

                <!-- FULL TEXT -->
                <field name="fulltext">
                    <xsl:for-each select="//METS:fileGrp[@USE=&quot;OCR-DIRTY&quot;]/METS:file/METS:FLocat">
                        <xsl:value-of select="php:function('VuFind::harvestTextFile', string(./@xlink:href))"/>
                    </xsl:for-each>
                    <xsl:for-each select="//METS:fileGrp[@USE=&quot;TRANSCRIPTION&quot;]/METS:file/METS:FLocat">
                        <xsl:value-of select="php:function('VuFind::harvestWithParser', string(./@xlink:href))"/>
                    </xsl:for-each>
                </field>
            </doc>
        </add>
    </xsl:template>
</xsl:stylesheet>
