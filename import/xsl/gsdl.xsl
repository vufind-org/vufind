<!-- available fields are defined in solr/biblio/conf/schema.xml -->
<!-- This xsl file is similar to xsl file of DSpace as both system use Dublin Core MetadataSet -->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:php="http://php.net/xsl"
    xmlns:xlink="http://www.w3.org/2001/XMLSchema-instance">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:param name="institution">My Institute</xsl:param>
    <xsl:param name="collection">DEMO</xsl:param>
    <xsl:param name="gsdlurl">http://greenstone.org</xsl:param>
    <xsl:param name="id_tag_name">identifier</xsl:param>
    <xsl:param name="change_tracking_core">biblio</xsl:param>
    <xsl:param name="change_tracking_date_tag_name"></xsl:param>
    <xsl:param name="workKey_include_regEx"></xsl:param>
    <xsl:param name="workKey_exclude_regEx"></xsl:param>
    <xsl:param name="workKey_transliterator_rules">:: NFD; :: lower; :: Latin; :: [^[:letter:] [:number:]] Remove; :: NFKC;</xsl:param>
    <xsl:template match="/">
        <xsl:if test="collection">
            <collection>
            <xsl:for-each select="collection">
                <xsl:for-each select="oai_dc:dc">
                    <xsl:apply-templates select="."/>
                </xsl:for-each>
            </xsl:for-each>
            </collection>
        </xsl:if>
        <xsl:if test="oai_dc:dc">
            <xsl:apply-templates/>
        </xsl:if>
    </xsl:template>
    <xsl:template match="oai_dc:dc">
        <add>
            <doc>
                <!-- ID -->
                <!-- Important: This relies on an <identifier> tag being injected by the OAI-PMH harvester. -->
                <field name="id">
                    <xsl:value-of select="*[name()=$id_tag_name]"/>
                </field>
                <!-- RECORD FORMAT -->
                <field name="record_format">gsdl</field>
                <!-- FULLRECORD -->
                <!-- disabled for now; records are so large that they cause memory problems!
                <field name="fullrecord">
                    <xsl:copy-of select="php:function('VuFind::xmlAsText', .)"/>
                </field>
                  -->
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

                <!-- LANGUAGE -->
                <xsl:for-each select="dc:language">
                    <xsl:if test="string-length() > 0">
                        <field name="language">
                            <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(.)), 'language_map_iso639-1.properties')"/>
                        </field>
                    </xsl:if>
                </xsl:for-each>

                <!-- FORMAT -->
                <field name="format">eResources</field>

                <!-- SUBJECT -->
                <xsl:for-each select="dc:subject">
                    <xsl:if test="string-length() > 0">
                        <field name="topic">
                            <xsl:value-of select="normalize-space()"/>
                        </field>
                    </xsl:if>
                </xsl:for-each>
                <!-- DESCRIPTION -->
                <xsl:if test="dc:description">
                    <field name="description">
                        <xsl:value-of select="dc:description" />
                    </field>
                </xsl:if>
                <!-- ADVISOR / CONTRIBUTOR -->
                <xsl:if test="dc:contributor[normalize-space()]">
                    <field name="author2">
                        <xsl:value-of select="dc:contributor[normalize-space()]" />
                    </field>
                </xsl:if>

                <!-- AUTHOR -->
                <xsl:for-each select="dc:creator">
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
                <!-- TITLE -->
                <xsl:if test="dc:title[normalize-space()]">
                    <field name="title">
                        <xsl:value-of select="dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_short">
                        <xsl:value-of select="dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_full">
                        <xsl:value-of select="dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_sort">
                        <xsl:value-of select="php:function('VuFind::titleSortLower', php:function('VuFind::stripArticles', string(dc:title[normalize-space()])))"/>
                    </field>
                </xsl:if>
                <!-- PUBLISHER -->
                <xsl:if test="dc:publisher[normalize-space()]">
                    <field name="publisher">
                        <xsl:value-of select="dc:publisher[normalize-space()]"/>
                    </field>
                </xsl:if>
                <!-- PUBLISHDATE -->
                <xsl:if test="dc:date">
                    <field name="publishDate">
                        <xsl:value-of select="substring(dc:date, 1, 4)"/>
                    </field>
                    <field name="publishDateSort">
                        <xsl:value-of select="substring(dc:date, 1, 4)"/>
                    </field>
                </xsl:if>
                <!-- URL -->
                <xsl:for-each select="dc:identifier">
                    <xsl:choose>
                        <xsl:when test="contains(., '://')">
                               <field name="url">
                                   <xsl:value-of select="." />
                               </field>
                        </xsl:when>
                        <xsl:when test="contains(., 'urn:')">
                               <field name="url">
                                   <xsl:value-of select="$gsdlurl" /><xsl:value-of select="." />
                               </field>
                        </xsl:when>
                    </xsl:choose>
                </xsl:for-each>

                <!-- Work Keys -->
                <xsl:for-each select="php:function('VuFindWorkKeys::getWorkKeys', '', dc:title[normalize-space()], php:function('VuFind::stripArticles', string(dc:title[normalize-space()])), dc:creator, $workKey_include_regEx, $workKey_exclude_regEx, $workKey_transliterator_rules)/workKey">
                    <field name="work_keys_str_mv">
                        <xsl:value-of select="." />
                    </field>
                </xsl:for-each>

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
