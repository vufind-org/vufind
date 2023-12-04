<?php

/**
 * SitemapIndex Test Class
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

use VuFind\Sitemap\SitemapIndex;

/**
 * SitemapIndex Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SitemapIndexTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test toString().
     *
     * @return void
     */
    public function testToString()
    {
        $sm = new SitemapIndex('2014-11-14');
        $sm->addUrl('http://foo');
        $sm->addUrl('http://bar');
        $expected = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <sitemapindex
               xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
               http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

              <sitemap>
                <loc>http://foo</loc>
                <lastmod>2014-11-14</lastmod>
              </sitemap>
              <sitemap>
                <loc>http://bar</loc>
                <lastmod>2014-11-14</lastmod>
              </sitemap>
            </sitemapindex>
            XML;
        $this->assertEquals($expected, $sm->toString());
    }
}
