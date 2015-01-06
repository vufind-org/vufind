<?php
/**
 * Sitemap Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Sitemap;
use VuFind\Sitemap\Sitemap;

/**
 * Sitemap Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SitemapTest extends \VuFindTest\Unit\TestCase
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
}