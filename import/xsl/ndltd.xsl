<!-- available fields are defined in solr/biblio/conf/schema.xml -->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:php="http://php.net/xsl"
    xmlns:xlink="http://www.w3.org/2001/XMLSchema-instance">
    <xsl:param name="institution">My University</xsl:param>
    <xsl:param name="collection">Digital Library</xsl:param>
    <xsl:param name="building"></xsl:param>
    <xsl:param name="urnresolver">http://nbn-resolving.de/</xsl:param>
    <xsl:variable name="URLs" />
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:template match="oai_dc:dc">

    <xsl:if test="//dc:identifier">

      <xsl:variable name="URLs"><xsl:for-each select="//dc:identifier"><xsl:value-of select="."/></xsl:for-each></xsl:variable>

      <xsl:if test="contains($URLs, '://') or contains($URLs, 'urn:')">

        <add>
            <doc>

                <!-- ID -->
                <field name="id">
                      <xsl:value-of select="//identifier"/>
                </field>


                <!-- RECORDTYPE -->
                <field name="recordtype">oai_dc</field>


                <!-- ALLFIELDS -->
                <field name="allfields">
                    <xsl:value-of select="normalize-space(string(//oai_dc:dc))"/>
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
                                <xsl:value-of select="php:function('VuFind::mapString', normalize-space(string(.)), 'language_map.properties')"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- building -->
                <xsl:if test="string-length($building) > 0">
                  <field name="building">
                    <xsl:value-of select="$building" />
                  </field>
                </xsl:if>


            <xsl:choose>
                <xsl:when test="contains(//dc:type, 'master')">
                    <field name="format">Dissertation</field>
                </xsl:when>
                <xsl:when test="contains(//dc:type, 'Master')">
                    <field name="format">Dissertation</field>
                </xsl:when>
                <xsl:when test="contains(//dc:type, 'doctor')">
                    <field name="format">Doctoral Thesis</field>
                </xsl:when>

                <xsl:when test="contains(//dc:type, 'Doctor')">
                    <field name="format">Doctoral Thesis</field>
                </xsl:when>

                <xsl:when test="contains(//dc:type, 'article')">
                    <field name="format">Article</field>
                </xsl:when>
                <xsl:when test="contains(//dc:type, 'artigo')">
                    <field name="format">Article</field>
                </xsl:when>

                <xsl:otherwise>
                    <xsl:if test="//dc:format">
                        <field name="format">
                            <xsl:value-of select="//dc:format"/>
                        </field>
                    </xsl:if>
                </xsl:otherwise>

          </xsl:choose>

          <field name="format">Online</field>

                <!-- SUBJECT -->
                <xsl:if test="//dc:subject">
                    <xsl:for-each select="//dc:subject">

                    <xsl:choose>
                        <xsl:when test="contains(., '. ')">
                                 <xsl:for-each select="php:function('VuFind::explode', '. ', string(//dc:subject))/part">
                                      <field name="topic"><xsl:value-of select="normalize-space(string(.))" /></field>
                                 </xsl:for-each>
                        </xsl:when>
                        <xsl:when test="contains(., ', ')">
                                 <xsl:for-each select="php:function('VuFind::explode', ', ', string(//dc:subject))/part">
                                      <field name="topic"><xsl:value-of select="normalize-space(string(.))" /></field>
                                 </xsl:for-each>
                        </xsl:when>
                        <xsl:when test="contains(., '; ')">
                                 <xsl:for-each select="php:function('VuFind::explode', '; ', string(//dc:subject))/part">
                                      <field name="topic"><xsl:value-of select="normalize-space(string(.))" /></field>
                                 </xsl:for-each>
                        </xsl:when>

                        <xsl:otherwise>
                               <field name="topic">
                                   <xsl:value-of select="." />
                               </field>
                        </xsl:otherwise>

                     </xsl:choose>

                    </xsl:for-each>
                </xsl:if>



                <!-- DESCRIPTION -->

                <xsl:if test="//dc:description">

                 <field name="description">
                    <xsl:for-each select="//dc:description">
                      <xsl:if test="position() > 1"> === </xsl:if><xsl:value-of select="."/>
                    </xsl:for-each>
                </field>

                </xsl:if>

                <!-- ADVISOR / CONTRIBUTOR -->
                <xsl:if test="//dc:contributor[normalize-space()]">
                    <field name="author_additional">
                        <xsl:value-of select="//dc:contributor[normalize-space()]" />
                    </field>
                </xsl:if>


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

                <!-- URL -->

                <xsl:for-each select="//dc:identifier">
                    <xsl:choose>
                        <xsl:when test="contains(., '://')">
                               <field name="url">
                                   <xsl:value-of select="." />
                               </field>
                        </xsl:when>
                        <xsl:when test="contains(., 'urn:')">
                               <field name="url">
                                   <xsl:value-of select="$urnresolver" /><xsl:value-of select="." />
                               </field>
                        </xsl:when>
                    </xsl:choose>
                </xsl:for-each>

            </doc>
        </add>
        </xsl:if>
     </xsl:if>
    </xsl:template>
</xsl:stylesheet>
