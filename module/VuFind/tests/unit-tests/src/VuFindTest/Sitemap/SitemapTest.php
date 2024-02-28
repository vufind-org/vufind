<?php

/**
 * Sitemap Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Sitemap;

use VuFind\Sitemap\Sitemap;

/**
 * Sitemap Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SitemapTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test toString().
     *
     * @return void
     */
    public function testToString()
    {
        $sm = new Sitemap();
        $sm->addUrl('http://foo');
        $sm->addUrl('http://bar');
        $expected = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset
               xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
               http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

            <url>
              <loc>http://foo</loc>
              <changefreq>weekly</changefreq>
            </url>
            <url>
              <loc>http://bar</loc>
              <changefreq>weekly</changefreq>
            </url>
            </urlset>
            XML;
        $this->assertEquals($expected, $sm->toString());
    }

    /**
     * Test toString() with multiple languages.
     *
     * @return void
     */
    public function testToStringWithLanguagesAndFrequencies()
    {
        $sm = new Sitemap();
        $sm->addUrl(
            [
                'url' => 'http://foo',
                'languages' => [
                    'en' => 'en', 'en-GB' => 'en-gb', 'fi' => 'fi', 'x-default' => null,
                ],
            ]
        );
        $sm->addUrl(
            [
                'url' => 'http://bar?t=1',
                'languages' => [
                  'en' => 'en', 'en-GB' => 'en-gb', 'fi' => 'fi', 'x-default' => null,
                ],
                'frequency' => 'daily',
            ]
        );
        $sm->addUrl('http://baz');
        $expected = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset
               xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
               xmlns:xhtml="http://www.w3.org/1999/xhtml"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
               http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

            <url>
              <loc>http://foo</loc>
              <changefreq>weekly</changefreq>
              <xhtml:link rel="alternate" hreflang="en">http://foo?lng=en</xhtml:link>
              <xhtml:link rel="alternate" hreflang="en-GB">http://foo?lng=en-gb</xhtml:link>
              <xhtml:link rel="alternate" hreflang="fi">http://foo?lng=fi</xhtml:link>
              <xhtml:link rel="alternate" hreflang="x-default">http://foo</xhtml:link>
            </url>
            <url>
              <loc>http://bar?t=1</loc>
              <changefreq>daily</changefreq>
              <xhtml:link rel="alternate" hreflang="en">http://bar?t=1&amp;lng=en</xhtml:link>
              <xhtml:link rel="alternate" hreflang="en-GB">http://bar?t=1&amp;lng=en-gb</xhtml:link>
              <xhtml:link rel="alternate" hreflang="fi">http://bar?t=1&amp;lng=fi</xhtml:link>
              <xhtml:link rel="alternate" hreflang="x-default">http://bar?t=1</xhtml:link>
            </url>
            <url>
              <loc>http://baz</loc>
              <changefreq>weekly</changefreq>
            </url>
            </urlset>
            XML;
        $this->assertXmlStringEqualsXmlString($expected, $sm->toString());
    }
}
