<!-- available fields are defined in solr/biblio/conf/schema.xml -->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:php="http://php.net/xsl"
    xmlns:nlm="http://dtd.nlm.nih.gov/publishing/2.3"
    xmlns:xlink="http://www.w3.org/1999/xlink"
    xmlns:mml="http://www.w3.org/1998/Math/MathML"
    >
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:param name="institution">My University</xsl:param>
    <xsl:param name="collection">OJS</xsl:param>
    <xsl:param name="workKey_include_regEx"></xsl:param>
    <xsl:param name="workKey_exclude_regEx"></xsl:param>
    <xsl:param name="workKey_transliterator_rules">:: NFD; :: lower; :: Latin; :: [^[:letter:] [:number:]] Remove; :: NFKC;</xsl:param>
    <xsl:template match="nlm:article">
        <add>
            <doc>
                <!-- ID -->
                <!-- Important: This relies on an <identifier> tag being injected by the OAI-PMH harvester. -->
                <field name="id">
                    <xsl:value-of select="nlm:identifier"/>
                </field>

                <!-- RECORD FORMAT -->
                <field name="record_format">NLMOJS</field>

                <!-- ALLFIELDS -->
                <field name="allfields">
                    <xsl:value-of select="normalize-space(string(.))"/>
                </field>

                <!-- INSTITUTION -->
                <field name="institution">
                    <xsl:value-of select="$institution" />
                </field>

                <!-- COLLECTION -->
                <field name="collection">
                    <xsl:value-of select="$collection" />
                </field>

                <!-- JOURNAL TITLE -->
                <field name="container_title">
                    <xsl:value-of select="//nlm:journal-title[normalize-space()]"/>
                </field>

                <!-- JOURNAL VOLUME -->
                <xsl:if test="//nlm:volume[normalize-space()]">
                    <field name="container_volume">
                        <xsl:value-of select="//nlm:volume[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- JOURNAL issue -->
                <xsl:if test="//nlm:issue[normalize-space()]">
                    <field name="container_issue">
                        <xsl:value-of select="//nlm:issue[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- Article startPage -->
                <xsl:if test="//nlm:fpage[normalize-space()]">
                    <field name="container_start_page">
                        <xsl:value-of select="//nlm:fpage[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- Article endPage !! Only enable this part of the code if you have defined "container_end_page" in  ./solr/vufind/biblio/conf/schema.xml -> <field name="container_end_page" type="text" indexed="true" stored="true"/> !!

                <xsl:if test="//nlm:lpage[normalize-space()]">
                        <field name="container_end_page">
                            <xsl:value-of select="//nlm:lpage[normalize-space()]"/>
                        </field>
                </xsl:if>

                -->

                <!-- LANGUAGE -->
                <field name="language">
                    <xsl:value-of select="php:function('VuFind::mapString', php:function('strtolower', string(@xml:lang)), 'language_map_iso639-1.properties')" />
                </field>

                <!-- FORMAT -->
                <field name="format">Article</field>
                <field name="format">Online</field>

                <!-- ISSN -->
                <xsl:for-each select="//nlm:issn">
                    <field name="issn">
                        <xsl:value-of select="normalize-space()"/>
                    </field>
                </xsl:for-each>

                <!-- SUBJECT -->
                <xsl:for-each select="//nlm:kwd-group">
                    <xsl:if test="position()=1">
                        <xsl:for-each select="./nlm:kwd">
                            <xsl:if test="normalize-space()">
                                <field name="topic">
                                    <xsl:value-of select="normalize-space()"/>
                                </field>
                                <field name="topic_facet">
                                    <xsl:value-of select="normalize-space()"/>
                                </field>
                            </xsl:if>
                        </xsl:for-each>
                    </xsl:if>
                </xsl:for-each>
                <xsl:for-each select="//nlm:subject">
                    <field name="topic">
                        <xsl:value-of select="normalize-space()"/>
                    </field>
                    <field name="topic_facet">
                        <xsl:value-of select="normalize-space()"/>
                    </field>
                </xsl:for-each>

                <!-- DESCRIPTION -->
                <xsl:if test="//nlm:abstract/nlm:p">
                    <field name="description">
                        <xsl:value-of select="//nlm:abstract/nlm:p" />
                        <xsl:if test="//nlm:abstract-trans/nlm:p"> ABSTRACT Translated: <xsl:value-of select="//nlm:abstract-trans/nlm:p" />
                        </xsl:if>
                    </field>
                </xsl:if>

                <!-- ADVISOR / CONTRIBUTOR -->
                <xsl:for-each select="//nlm:contrib[@contrib-type='editor']/nlm:name">
                    <field name="author2">
                        <xsl:value-of select="nlm:surname[normalize-space()]" />, <xsl:value-of select="nlm:given-names[normalize-space()]" />
                    </field>
                </xsl:for-each>

                <!-- AUTHOR -->
                <xsl:for-each select="//nlm:contrib[@contrib-type='author']/nlm:name">
                    <xsl:if test="normalize-space()">
                        <field name="author">
                            <xsl:value-of select="nlm:surname[normalize-space()]" />, <xsl:value-of select="nlm:given-names[normalize-space()]" />
                        </field>
                        <!-- use first author value for sorting -->
                        <xsl:if test="position()=1">
                            <field name="author_sort">
                                <xsl:value-of select="nlm:surname[normalize-space()]" />, <xsl:value-of select="nlm:given-names[normalize-space()]" />
                            </field>
                        </xsl:if>
                    </xsl:if>
                </xsl:for-each>

                <!-- TITLE -->
                <field name="title">
                    <xsl:value-of select="//nlm:article-title[normalize-space()]"/>
                </field>
                <field name="title_short">
                    <xsl:value-of select="//nlm:article-title[normalize-space()]"/>
                </field>
                <field name="title_full">
                    <xsl:value-of select="//nlm:article-title[normalize-space()]"/>
                </field>
                <field name="title_sort">
                    <xsl:value-of select="php:function('VuFind::titleSortLower', php:function('VuFind::stripArticles', string(//nlm:article-title[normalize-space()])))"/>
                </field>
                <field name="title_alt">
                    <xsl:value-of select="//nlm:trans-title[normalize-space()]"/>
                </field>

                <!-- PUBLISHER -->
                <xsl:if test="//nlm:publisher-name">
                    <field name="publisher">
                        <xsl:value-of select="//nlm:publisher-name[normalize-space()]"/>
                    </field>
                </xsl:if>

                <!-- PUBLISHDATE -->
                <xsl:if test="//nlm:pub-date[@pub-type='collection']/nlm:year">
                    <field name="publishDate">
                        <xsl:value-of select="//nlm:pub-date[@pub-type='collection']/nlm:year"/>
                    </field>
                    <field name="publishDateSort">
                        <xsl:value-of select="//nlm:pub-date[@pub-type='collection']/nlm:year"/>
                    </field>
                </xsl:if>

                <!-- URL -->
                <xsl:for-each select="//nlm:self-uri">
                    <xsl:sort select="position()" data-type="number" order="descending"/>
                       <field name="url">
                           <xsl:value-of select="@xlink:href" />
                       </field>
                </xsl:for-each>

                <!-- FULL TEXT -->
                <xsl:choose>
                    <xsl:when test="nlm:body/nlm:p">
                       <field name="fulltext">
                           <xsl:value-of select="nlm:body/nlm:p" />
                       </field>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:for-each select="//nlm:self-uri[@content-type=&quot;application/pdf&quot;]">
                            <field name="fulltext">
                                <xsl:value-of select="php:function('VuFind::harvestWithParser', string(./@xlink:href))"/>
                            </field>
                        </xsl:for-each>
                    </xsl:otherwise>
                </xsl:choose>

                <!-- FULLRECORD (exclude body tag because fulltext is huge) -->
                <field name="fullrecord">
                    <xsl:copy-of select="php:function('VuFind::removeTagAndReturnXMLasText', ., 'body')"/>
                </field>

                <!-- Work Keys -->
                <xsl:for-each select="php:function('VuFindWorkKeys::getWorkKeys', '', ///nlm:article-title[normalize-space()], php:function('VuFind::stripArticles', string(///nlm:article-title[normalize-space()])), //nlm:contrib[@contrib-type='author']/nlm:name, $workKey_include_regEx, $workKey_exclude_regEx, $workKey_transliterator_rules)/workKey">
                    <field name="work_keys_str_mv">
                        <xsl:value-of select="." />
                    </field>
                </xsl:for-each>
            </doc>
        </add>
    </xsl:template>
</xsl:stylesheet>
