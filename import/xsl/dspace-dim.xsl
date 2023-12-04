<!-- available fields are defined in solr/biblio/conf/schema.xml -->
<!-- This document was written for Biblioteca Brasiliana Guita e José Mindlin
     by Fabio Chagas da Silva (fabio.chagas.silva@usp.br, GitHub: @fabio-stdio).
     It takes metadata directly from dim to index in Solr. It is based on dspace.xsl.

     The choices of fields were made based on which metadata is most interesting
     for the user of the library and which Solr fields in schema.xml are close to
     the metadata of choice. Those choices were made supervised by the librarian
     of Brasiliana Guita e José Mindlin, Rodrigo Garcia (garcia.rodrigo@gmail.com).

     Additional fields (and significant adjustments) were added based on the work
     of Roland Keck.
-->
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:dim="http://www.dspace.org/xmlns/dspace/dim"
                xmlns:php="http://php.net/xsl"
                xmlns:xlink="http://www.w3.org/2001/XMLSchema-instance">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:param name="institution">My University</xsl:param>
    <xsl:param name="collection">DSpace</xsl:param>
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
                <xsl:for-each select="dim:dim">
                    <xsl:apply-templates select="."/>
                </xsl:for-each>
            </xsl:for-each>
            </collection>
        </xsl:if>
        <xsl:if test="dim:dim">
            <xsl:apply-templates/>
        </xsl:if>
    </xsl:template>
    <xsl:template match="dim:dim">
        <add>
            <doc>
                <!-- Those fields are treated the same as in dspace.xsl, except
                     for the tags
                -->

                <!-- ID -->
                <!-- Important: This relies on an <identifier> tag being injected
                     by the OAI-PMH harvester.
                -->
                <field name="id">
                    <xsl:value-of select="*[name()=$id_tag_name]"/>
                </field>

                <!-- RECORD FORMAT -->
                <field name="record_format">dspace</field>

                <!-- ALLFIELDS -->
                <xsl:for-each select="dim:field">
                    <xsl:if test="string-length(.) > 0">
                        <field name="allfields">
                            <xsl:value-of select="normalize-space(.)"/>
                        </field>
                    </xsl:if>
                </xsl:for-each>

                <!-- INSTITUTION -->
                <field name="institution">
                    <xsl:value-of select="$institution" />
                </field>

                <!-- COLLECTION -->
                <field name="collection">
                    <xsl:value-of select="$collection" />
                </field>

                <!-- TITLE -->
                <xsl:if test= "dim:field[@element='title']">
                    <field name="title">
                        <xsl:value-of select="dim:field[@element='title']"/>
                    </field>
                    <field name="title_short">
                        <xsl:value-of select="dim:field[@element='title']"/>
                    </field>
                    <field name="title_full">
                        <xsl:value-of select="dim:field[@element='title']"/>
                    </field>
                    <field name="title_sort">
                        <xsl:value-of select="php:function('VuFind::titleSortLower', php:function('VuFind::stripArticles', string(dim:field[@element='title'][normalize-space()])))"/>
                    </field>
                </xsl:if>

                <!-- AUTHOR -->
                <xsl:for-each select="dim:field[@element='contributor' and @qualifier='author']">
                    <field name="author">
                        <xsl:value-of select="normalize-space(.)"/>
                    </field>
                </xsl:for-each>

                <!-- CO AUTHOR -->
                <xsl:for-each select="dim:field[@element='contributor' and @qualifier='other']">
                    <field name="author2">
                        <xsl:value-of select="normalize-space(.)"/>
                    </field>
                </xsl:for-each>

                <!-- EDITOR (treated as corporate author as per Roland Keck's example;
                     this may vary by institution.
                -->
                <xsl:for-each select="dim:field[@element='contributor' and @qualifier='editor']">
                    <field name="author_corporate">
                        <xsl:value-of select="normalize-space(.)"/>
                    </field>
                </xsl:for-each>

                <!-- SUBJECT -->
                <xsl:for-each select="dim:field[@element='subject']">
                    <field name="topic">
                        <xsl:value-of select="normalize-space(.)"/>
                    </field>
                    <field name="topic_facet">
                        <xsl:value-of select="normalize-space(.)"/>
                    </field>
                </xsl:for-each>

                <!-- Published Date -->
                <xsl:if test="dim:field[@element='date' and @qualifier='issued']">
                    <field name="publishDate">
                        <xsl:value-of select="dim:field[@element='date' and @qualifier='issued']"/>
                    </field>
                    <field name="publishDateSort">
                        <xsl:value-of select="substring(dim:field[@element='date' and @qualifier='issued'], 1, 4)" />
                    </field>
                </xsl:if>

                <!-- Language -->
                <xsl:if test="dim:field[@element='language' and @qualifier='iso']">
                    <field name="language">
                        <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(dim:field[@element='language' and @qualifier='iso'])), 'language_map_iso639-1.properties')"/>
                    </field>
                </xsl:if>

                <!-- Relation -->
                <xsl:if test="dim:field[@element='relation' and @qualifier = 'ispartof']">
                    <field name="container_title">
                        <xsl:value-of select="normalize-space(dim:field[@element='relation' and @qualifier = 'ispartof'])"/>
                    </field>
                </xsl:if>
                <xsl:if test="dim:field[@element='relation' and @qualifier = 'ispartofvolume']">
                    <field name="container_volume">
                        <xsl:value-of select="normalize-space(dim:field[@element='relation' and @qualifier = 'ispartofvolume'])"/>
                    </field>
                </xsl:if>

                <!-- Publisher -->
                <xsl:if test="dim:field[@element='publisher']">
                    <field name="publisher">
                        <xsl:value-of select="dim:field[@element='publisher']"/>
                    </field>
                </xsl:if>

                <!-- Extent -->
                <xsl:if test="dim:field[@element='format' and @qualifier='extent']">
                    <field name="physical">
                        <xsl:value-of select="dim:field[@element='format' and @qualifier='extent']"/>
                    </field>
                </xsl:if>

                <!-- Format -->
                <xsl:if test="dim:field[@element='format' and @qualifier='medium']">
                    <field name="format">
                        <xsl:value-of select="dim:field[@element='format' and @qualifier='medium']"/>
                    </field>
                </xsl:if>

                <!-- Type -->
                <xsl:if test="dim:field[@element='type']">
                    <field name="format">
                        <xsl:value-of select="dim:field[@element='type']"/>
                    </field>
                </xsl:if>

                <!-- Alternative Title -->
                <xsl:for-each select="dim:field[@element='title' and @qualifier='alternative']">
                    <field name="title_alt">
                        <xsl:value-of select="normalize-space(.)"/>
                    </field>
                </xsl:for-each>

                <!-- Description -->
                <!-- Two if blocks are used in description, one to check its
                     existence and another, within the for-each loop to not print
                     a dim:field[@element='description' and @qualifier='provenance'].

                     Although, the field with only attribute @element='description'
                     doesn't print, an inelegant solution was found and implemented

                     A text tag is included for the new line character after
                     printing the value in the field with the attribute
                     @element='description' only.

                     Within the for-each loop was included (as mentioned earlier)
                     an if block. After printing all values with descriptions,
                     except for description and provenance, concatenate with new
                     line character.
                -->
                <xsl:if test="dim:field[@element='description' and @qualifier='abstract']">
                    <field name="description">
                        <xsl:for-each select="dim:field[@element='description' and @qualifier='abstract']">
                            <xsl:value-of select="concat(., '&#xA;')"/>
                        </xsl:for-each>
                    </field>
                </xsl:if>

                <xsl:if test="dim:field[@element='description' and @qualifier='edition']">
                    <field name="edition">
                        <xsl:value-of select="normalize-space(dim:field[@element='description' and @qualifier='edition'])"/>
                    </field>
                </xsl:if>

                <!-- Volume (dc.relation.requires) -->
                <xsl:if test="dim:field[@element='relation' and @qualifier='requires']">
                    <field name="container_volume">
                        <xsl:value-of select="dim:field[@element='relation' and @qualifier='requires']"/>
                    </field>
                </xsl:if>

                <!-- Table of contents -->
                <xsl:if test="dim:field[@element='description' and @qualifier='tableofcontents']">
                    <field name="contents">
                        <xsl:value-of select="dim:field[@element='description' and @qualifier='tableofcontents']"/>
                    </field>
                </xsl:if>

                <!-- Spatial -->
                <xsl:for-each select="dim:field[@qualifier='spatial']">
                    <field name="geographic_facet">
                        <xsl:value-of select="normalize-space(.)"/>
                    </field>
                </xsl:for-each>

                <!-- ISBN -->
                <xsl:if test="dim:field[@qualifier='isbn']">
                    <field name="isbn">
                        <xsl:value-of select="dim:field[@qualifier='isbn']"/>
                    </field>
                </xsl:if>

                <!-- ISSN -->
                <xsl:if test="dim:field[@qualifier='issn']">
                    <field name="issn">
                        <xsl:value-of select="dim:field[@qualifier='issn']"/>
                    </field>
                </xsl:if>

                <!-- URL -->
                <xsl:for-each select="dim:field[@element='identifier']">
                    <xsl:if test="string-length(.) > 0">
                        <xsl:if test="@qualifier = 'uri'">
                            <field name="url">
                                <xsl:value-of select="normalize-space(.)" />
                            </field>
                        </xsl:if>
                    </xsl:if>
                </xsl:for-each>

                <!-- Work Keys -->
                <xsl:for-each select="php:function('VuFindWorkKeys::getWorkKeys', '', dim:field[@element='title'], php:function('VuFind::stripArticles', string(dim:field[@element='title'][normalize-space()])), dim:field[@element='contributor' and @qualifier='author'], $workKey_include_regEx, $workKey_exclude_regEx, $workKey_transliterator_rules)/workKey">
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
