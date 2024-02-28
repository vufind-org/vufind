<?php

/**
 * Unit tests for SOLR similar records query builder
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Solr;

use VuFindSearch\Backend\Solr\SimilarBuilder;

/**
 * Unit tests for SOLR similar records query builder
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SimilarBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test builder with default params.
     *
     * @return void
     */
    public function testDefaultParams()
    {
        $sb = new SimilarBuilder();
        $response = $sb->build('testrecord');
        $rows = $response->get('rows');
        $this->assertEquals(5, $rows[0]);
        $q = $response->get('q');
        $this->assertEquals('id:"testrecord"', $q[0]);
        $qt = $response->get('qt');
        $this->assertEquals('morelikethis', $qt[0]);
    }

    /**
     * Test builder with alternative id field.
     *
     * @return void
     */
    public function testAlternativeIdField()
    {
        $sb = new SimilarBuilder(null, 'key');
        $response = $sb->build('testrecord');
        $q = $response->get('q');
        $this->assertEquals('key:"testrecord"', $q[0]);
    }

    /**
     * Test builder with different configurations.
     *
     * @return void
     */
    public function testMltConfig()
    {
        $config = [
            'MoreLikeThis' => [
                'count' => 10,
            ],
        ];
        $sb = new SimilarBuilder(new \Laminas\Config\Config($config));
        $response = $sb->build('testrecord');
        $rows = $response->get('rows');
        $this->assertEquals(10, $rows[0]);

        $config['MoreLikeThis']['useMoreLikeThisHandler'] = true;
        $sb = new SimilarBuilder(new \Laminas\Config\Config($config));
        $response = $sb->build('testrecord');
        $rows = $response->get('rows');
        $this->assertEquals(10, $rows[0]);
        $q = $response->get('q');
        $this->assertEquals(
            '{!mlt qf=title,title_short,callnumber-label,topic,language,author,'
            . 'publishDate mintf=1 mindf=1}testrecord',
            $q[0]
        );
        $qt = $response->get('qt');
        $this->assertEquals(null, $qt);

        $config['MoreLikeThis']['params'] = 'qf=title,topic';
        $sb = new SimilarBuilder(new \Laminas\Config\Config($config));
        $response = $sb->build('testrecord');
        $q = $response->get('q');
        $this->assertEquals('{!mlt qf=title,topic}testrecord', $q[0]);
    }
}
