<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:php="http://php.net/xsl"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xlink="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:oai_doaj="http://doaj.org/features/oai_doaj/1.0/">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:param name="institution">Directory of Open Access Journals</xsl:param>
    <xsl:param name="collection">DOAJ</xsl:param>
    <xsl:param name="workKey_include_regEx"></xsl:param>
    <xsl:param name="workKey_exclude_regEx"></xsl:param>
    <xsl:param name="workKey_transliterator_rules">:: NFD; :: lower; :: Latin; :: [^[:letter:] [:number:]] Remove; :: NFKC;</xsl:param>
    <xsl:template match="oai_doaj:doajArticle">
        <add>
            <doc>
                <!-- ID -->
                <!-- Important: This relies on an <identifier> tag being injected by the OAI-PMH harvester. -->
                <field name="id">
                    <xsl:value-of select="//identifier"/>
                </field>

                <!-- RECORD FORMAT -->
                <field name="record_format">Article</field>

                <!-- FULLRECORD -->
                <!-- disabled for now; records are so large that they cause memory problems!
                <field name="fullrecord">
                    <xsl:copy-of select="php:function('VuFind::xmlAsText', /oai_doaj:record)"/>
                </field>
                  -->

                <!-- ALLFIELDS -->
                <field name="allfields">
                    <xsl:value-of select="normalize-space(string(/oai_doaj:doajArticle))"/>
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
                <xsl:if test="/oai_doaj:doajArticle/oai_doaj:language">
                    <xsl:for-each select="/oai_doaj:doajArticle/oai_doaj:language">
                        <xsl:if test="string-length() > 0">
                            <field name="language">
                                <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(.)), 'language_map.properties')"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- FORMAT -->
                <field name="format">Article</field>

                <!-- AUTHOR -->
                <xsl:if test="//oai_doaj:authors/oai_doaj:author/oai_doaj:name">
                    <xsl:for-each select="//oai_doaj:authors/oai_doaj:author/oai_doaj:name">
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

                <!-- TITLE -->
                <xsl:if test="//oai_doaj:title[normalize-space()]">
                    <field name="title">
                        <xsl:value-of select="//oai_doaj:title[normalize-space()]"/>
                    </field>
                    <field name="title_short">
                        <xsl:value-of select="//oai_doaj:title[normalize-space()]"/>
                    </field>
                    <field name="title_full">
                        <xsl:value-of select="//oai_doaj:title[normalize-space()]"/>
                    </field>
                    <field name="title_sort">
                        <xsl:value-of select="php:function('VuFind::stripArticles', string(//oai_doaj:title[normalize-space()]))"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHER -->
                <xsl:if test="//oai_doaj:publisher[normalize-space()]">
                    <field name="publisher">
                        <xsl:value-of select="//oai_doaj:publisher[normalize-space()]"/>
                    </field>
                </xsl:if>

                 <!-- SERIES -->
                <xsl:if test="//oai_doaj:journalTitle[normalize-space()]">
                    <field name="series">
                        <xsl:value-of select="//oai_doaj:journalTitle[normalize-space()]"/>
                    </field>
                </xsl:if>

                 <!-- ISSN  -->
                <xsl:if test="//oai_doaj:issn[normalize-space()]">
                    <field name="issn">
                        <xsl:value-of select="//oai_doaj:issn[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- ISSN  -->
                <xsl:if test="//oai_doaj:eissn[normalize-space()]">
                    <field name="issn">
                        <xsl:value-of select="//oai_doaj:eissn[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHDATE -->
                <xsl:if test="//oai_doaj:publicationDate">
                    <field name="publishDate">
                        <xsl:value-of select="//oai_doaj:publicationDate"/>
                    </field>
                    <field name="publishDateSort">
                        <xsl:value-of select="//oai_doaj:publicationDate"/>
                    </field>
                </xsl:if>

                <!-- DESCRIPTION -->
                <xsl:if test="//oai_doaj:abstract">
                    <field name="description">
                        <xsl:value-of select="//oai_doaj:abstract" />
                    </field>
                </xsl:if>

                <!-- SUBJECT -->
                <xsl:if test="//oai_doaj:keywords">
                    <xsl:for-each select="//oai_doaj:keywords/oai_doaj:keyword">
                        <xsl:if test="string-length() > 0">
                            <field name="topic">
                                <xsl:value-of select="normalize-space()"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- URL -->
                <xsl:if test="//oai_doaj:fullTextUrl">
                    <xsl:choose>
                        <xsl:when test="contains(//oai_doaj:fullTextUrl, '://')">
                            <field name="url"><xsl:value-of select="//oai_doaj:fullTextUrl[normalize-space()]"/></field>
                        </xsl:when>
                        <xsl:otherwise>
                            <field name="url">http://<xsl:value-of select="//oai_doaj:fullTextUrl[normalize-space()]"/></field>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:if>

                <!-- Work Keys -->
                <xsl:for-each select="php:function('VuFindWorkKeys::getWorkKeys', '', //oai_doaj:title[normalize-space()], php:function('VuFind::stripArticles', string(//oai_doaj:title[normalize-space()])), //oai_doaj:authors/oai_doaj:author/oai_doaj:name, $workKey_include_regEx, $workKey_exclude_regEx, $workKey_transliterator_rules)/workKey">
                    <field name="work_keys_str_mv">
                        <xsl:value-of select="." />
                    </field>
                </xsl:for-each>
            </doc>
        </add>
    </xsl:template>
</xsl:stylesheet>
