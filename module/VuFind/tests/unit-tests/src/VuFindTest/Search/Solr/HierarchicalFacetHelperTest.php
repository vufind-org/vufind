<?php

/**
 * Unit tests for Hierarchical Facet Helper.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace VuFindTest\Search\Solr;

use VuFindTest\Unit\TestCase;
use Zend\EventManager\Event;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\UrlQueryHelper;
use VuFind\Search\Base\Params;
use VuFind\Config\PluginManager;
use VuFind\Search\Solr\Options;

/**
 * Unit tests for Hierarchical Facet Helper.
 *
 * @category VuFind2
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 * @todo     Test buildFacetArray using url helper
 */
class HierarchicalFacetHelperTest extends TestCase
{
    protected $facetList = array(
        array(
            'value' => '0/Book/',
            'displayText' => 'Book',
            'count' => 1000,
            'operator' => 'OR'
        ),
        array(
            'value' => '0/AV/',
            'displayText' => 'Audiovisual',
            'count' => 600,
            'operator' => 'OR'
        ),
        array(
            'value' => '0/Audio/',
            'displayText' => 'Sound',
            'count' => 400,
            'operator' => 'OR'
        ),
        array(
            'value' => '1/Book/BookPart/',
            'displayText' => 'Book Part',
            'count' => 300,
            'operator' => 'OR'
        ),
        array(
            'value' => '1/Book/Section/',
            'displayText' => 'Book Section',
            'count' => 200,
            'operator' => 'OR'
        ),
        array(
            'value' => '1/Audio/Spoken/',
            'displayText' => 'Spoken Text',
            'count' => 100,
            'operator' => 'OR'
        ),
        array(
            'value' => '1/Audio/Music/',
            'displayText' => '1/Audio/Music/',
            'count' => 50,
            'operator' => 'OR'
        )
    );

    /**
     * Hierarchical Facet Helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $helper;

    /**
     * Setup.
     *
     * @return void
     */
    protected function setup()
    {
        $this->helper = new HierarchicalFacetHelper();
    }

    public function testSortFacetListTopLevel()
    {
        $facetList = $this->facetList;
        $this->helper->sortFacetList($facetList, true);
        $this->assertEquals($facetList[0]['value'], '0/AV/');
        $this->assertEquals($facetList[1]['value'], '0/Book/');
        $this->assertEquals($facetList[2]['value'], '0/Audio/');
        $this->assertEquals($facetList[3]['value'], '1/Book/BookPart/');
        $this->assertEquals($facetList[4]['value'], '1/Book/Section/');
        $this->assertEquals($facetList[5]['value'], '1/Audio/Spoken/');
        $this->assertEquals($facetList[6]['value'], '1/Audio/Music/');
    }

    public function testSortFacetListAllLevels()
    {
        $facetList = $this->facetList;
        $this->helper->sortFacetList($facetList, false);
        $this->assertEquals($facetList[0]['value'], '0/AV/');
        $this->assertEquals($facetList[1]['value'], '0/Book/');
        $this->assertEquals($facetList[2]['value'], '0/Audio/');
        $this->assertEquals($facetList[3]['value'], '1/Book/BookPart/');
        $this->assertEquals($facetList[4]['value'], '1/Book/Section/');
        $this->assertEquals($facetList[5]['value'], '1/Audio/Music/');
        $this->assertEquals($facetList[6]['value'], '1/Audio/Spoken/');
    }

    public function testBuildFacetArray()
    {
        // Test without active filters
        $facetList = $this->helper->buildFacetArray(
            'format', $this->facetList, array()
        );
        $this->assertEquals($facetList[0]['value'], '0/Book/');
        $this->assertEquals($facetList[0]['level'], 0);
        $this->assertTrue(!$facetList[0]['selected']);
        $this->assertTrue(!$facetList[0]['state']['opened']);
        $this->assertEquals(
            $facetList[0]['children'][0]['value'], '1/Book/BookPart/'
        );
        $this->assertEquals($facetList[0]['children'][0]['level'], 1);
        $this->assertTrue(!$facetList[0]['children'][0]['selected']);
        $this->assertEquals($facetList[1]['value'], '0/AV/');
        $this->assertEquals($facetList[2]['value'], '0/Audio/');
        $this->assertEquals(
            $facetList[2]['children'][0]['value'], '1/Audio/Spoken/'
        );
        $this->assertEquals($facetList[2]['children'][1]['value'], '1/Audio/Music/');

        // Test with active filter
        $facetList = $this->helper->buildFacetArray(
            'format',
            $this->facetList,
            array(
                array(
                    array(
                        'field' => 'format',
                        'value' => '1/Book/BookPart/'
                    )
                )
            )
        );
        $this->assertEquals($facetList[0]['value'], '0/Book/');
        $this->assertTrue(!$facetList[0]['selected']);
        $this->assertTrue($facetList[0]['state']['opened']);
        $this->assertEquals(
            $facetList[0]['children'][0]['value'], '1/Book/BookPart/'
        );
        $this->assertEquals($facetList[0]['children'][0]['selected'], true);

    }

    public function testFlattenFacetHierarchy()
    {
        $facetList = $this->helper->flattenFacetHierarchy(
            $this->helper->buildFacetArray(
                'format', $this->facetList, array()
            )
        );
        $this->assertEquals($facetList[0]['value'], '0/Book/');
        $this->assertEquals($facetList[1]['value'], '1/Book/BookPart/');
        $this->assertEquals($facetList[2]['value'], '1/Book/Section/');
        $this->assertEquals($facetList[3]['value'], '0/AV/');
        $this->assertEquals($facetList[4]['value'], '0/Audio/');
        $this->assertEquals($facetList[5]['value'], '1/Audio/Spoken/');
        $this->assertEquals($facetList[6]['value'], '1/Audio/Music/');
    }

    public function testFormatDisplayText()
    {
        $this->assertEquals(
            $this->helper->formatDisplayText('0/Sound/'),
            'Sound'
        );
        $this->assertEquals(
            $this->helper->formatDisplayText('1/Sound/Noisy/'),
            'Noisy'
        );
        $this->assertEquals(
            $this->helper->formatDisplayText('1/Sound/Noisy/', true),
            'Sound/Noisy'
        );
        $this->assertEquals(
            $this->helper->formatDisplayText('1/Sound/Noisy/', true, ' - '),
            'Sound - Noisy'
        );
    }
}
