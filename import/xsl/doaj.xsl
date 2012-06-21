<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:php="http://php.net/xsl"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xlink="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:doaj="http://www.doaj.org/schemas/">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:param name="institution">My University</xsl:param>
    <xsl:param name="collection">DOAJ</xsl:param>
    <xsl:template match="doaj:record">
        <add>
            <doc>
                <!-- ID -->
                <!-- Important: This relies on an <identifier> tag being injected by the OAI-PMH harvester. -->
                <field name="id">
                    <xsl:value-of select="//doaj:doajIdentifier"/>
                </field>

                <!-- RECORDTYPE -->
                <field name="recordtype">Article</field>

                <!-- FULLRECORD -->
                <!-- disabled for now; records are so large that they cause memory problems!
                <field name="fullrecord">
                    <xsl:copy-of select="php:function('VuFind::xmlAsText', /doaj:record)"/>
                </field>
                  -->

                <!-- ALLFIELDS -->
                <field name="allfields">
                    <xsl:value-of select="normalize-space(string(/doaj:record))"/>
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
                <!-- TODO: add language support; in practice, there don't seem to be
                     many records with <language> tags in them.  If we encounter any,
                     the code below is partially complete, but we probably need to
                     build a new language map for ISO 639-2b, which is the standard
                     specified by the DOAJ XML schema.
                <xsl:if test="/doaj:record/doaj:language">
                    <xsl:for-each select="/doaj:record/doaj:language">
                        <xsl:if test="string-length() > 0">
                            <field name="language">
                                <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(.)), 'language_map_iso639-1.properties')"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>
                -->

                <!-- FORMAT -->
                <field name="format">Article</field>

                <!-- AUTHOR -->
                <xsl:if test="//doaj:authors/doaj:author/doaj:name">
                    <xsl:for-each select="//doaj:authors/doaj:author/doaj:name">
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

                <!-- TITLE -->
                <xsl:if test="//doaj:title[normalize-space()]">
                    <field name="title">
                        <xsl:value-of select="//doaj:title[normalize-space()]"/>
                    </field>
                    <field name="title_short">
                        <xsl:value-of select="//doaj:title[normalize-space()]"/>
                    </field>
                    <field name="title_full">
                        <xsl:value-of select="//doaj:title[normalize-space()]"/>
                    </field>
                    <field name="title_sort">
                        <xsl:value-of select="php:function('VuFind::stripArticles', string(//doaj:title[normalize-space()]))"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHER -->
                <xsl:if test="//doaj:publisher[normalize-space()]">
                    <field name="publisher">
                        <xsl:value-of select="//doaj:publisher[normalize-space()]"/>
                    </field>
                </xsl:if>

                 <!-- SERIES -->
                <xsl:if test="//doaj:journalTitle[normalize-space()]">
                    <field name="series">
                        <xsl:value-of select="//doaj:journalTitle[normalize-space()]"/>
                    </field>
                </xsl:if>

                 <!-- ISSN  -->
                <xsl:if test="//doaj:issn[normalize-space()]">
                    <field name="issn">
                        <xsl:value-of select="//doaj:issn[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- ISSN  -->
                <xsl:if test="//doaj:eissn[normalize-space()]">
                    <field name="issn">
                        <xsl:value-of select="//doaj:eissn[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHDATE -->
                <xsl:if test="//doaj:publicationDate">
                    <field name="publishDate">
                        <xsl:value-of select="//doaj:publicationDate"/>
                    </field>
                    <field name="publishDateSort">
                        <xsl:value-of select="//doaj:publicationDate"/>
                    </field>
                </xsl:if>

                <!-- DESCRIPTION -->
                <xsl:if test="//doaj:abstract">
                    <field name="description">
                        <xsl:value-of select="//doaj:abstract" />
                    </field>
                </xsl:if>

                <!-- SUBJECT -->
                <xsl:if test="//doaj:keywords">
                    <xsl:for-each select="//doaj:keywords/doaj:keyword">
                        <xsl:if test="string-length() > 0">
                            <field name="topic">
                                <xsl:value-of select="normalize-space()"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- URL -->
                <xsl:if test="//doaj:fullTextUrl">
                    <xsl:choose>
                        <xsl:when test="contains(//doaj:fullTextUrl, '://')">
                            <field name="url"><xsl:value-of select="//doaj:fullTextUrl[normalize-space()]"/></field>
                        </xsl:when>
                        <xsl:otherwise>
                            <field name="url">http://<xsl:value-of select="//doaj:fullTextUrl[normalize-space()]"/></field>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:if> 
            </doc>
        </add>
    </xsl:template>
</xsl:stylesheet>
