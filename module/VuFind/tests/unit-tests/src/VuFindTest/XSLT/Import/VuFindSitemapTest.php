<?php

/**
 * Sitemap XSLT helper tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\XSLT\Import;

use VuFind\XSLT\Import\VuFindSitemap;
use VuFindTest\Feature\FixtureTrait;

/**
 * Sitemap XSLT helper tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VuFindSitemapTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Test indexing a web page.
     *
     * @return void
     */
    public function testWebIndexing(): void
    {
        /**
         * Extend the class so we can mock some details:
         */
        $class = new class () extends VuFindSitemap {
            /**
             * Simulate reading parser method from fulltext.ini.
             *
             * @return string Name of parser to use (i.e. Aperture or Tika)
             */
            public static function getParser()
            {
                return 'Tika';
            }

            /**
             * Simulate loading metadata about an HTML document using Tika.
             *
             * @param string $htmlFile File on disk containing HTML.
             *
             * @return array
             */
            protected static function getTikaFields($htmlFile)
            {
                return [
                    'title' => 'The Fake Title',
                    'keywords' => 'fake keywords',
                    'description' => 'fake description',
                    'fulltext' => 'fake full text',
                ];
            }
        };
        $url = $this->getFixturePath('web/test.html');
        $xml = $class::getDocument($url);
        $xmlRegEx = '|<field name="title">The Fake Title</field><field name="keywords">fake keywords</field>'
            . '<field name="description">fake description</field><field name="fulltext">fake full text</field>'
            . '<field name="category">fake category</field><field name="subject">fake subject</field>'
            . '<field name="use_count">123</field><field name="id">' . md5($url) . '</field>'
            . '<field name="url">' . $url . '</field>'
            . '<field name="last_indexed">\d+-\d+-\d+T\d+:\d+:\d+Z</field>'
            . '<field name="title_sort">fake title</field>|';
        $this->assertMatchesRegularExpression($xmlRegEx, $xml);
    }
}
