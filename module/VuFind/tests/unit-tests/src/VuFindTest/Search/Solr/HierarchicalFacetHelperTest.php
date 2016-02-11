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
use VuFind\Search\Solr\HierarchicalFacetHelper;

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
    protected $facetList = [
        [
            'value' => '0/Book/',
            'displayText' => 'Book',
            'count' => 1000,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => '0/AV/',
            'displayText' => 'Audiovisual',
            'count' => 600,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => '0/Audio/',
            'displayText' => 'Sound',
            'count' => 400,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => '1/Book/BookPart/',
            'displayText' => 'Book Part',
            'count' => 300,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => '1/Book/Section/',
            'displayText' => 'Book Section',
            'count' => 200,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => '1/Audio/Spoken/',
            'displayText' => 'Spoken Text',
            'count' => 100,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => '1/Audio/Music/',
            'displayText' => 'Music',
            'count' => 50,
            'operator' => 'OR',
            'isApplied' => false
        ]
    ];

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

    /**
     * Tests for sortFacetList (top level only)
     */
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

    /**
     * Tests for sortFacetList (all levels)
     */
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

    /**
     * Tests for buildFacetArray
     */
    public function testBuildFacetArray()
    {
        // Test without active filters
        $facetList = $this->helper->buildFacetArray('format', $this->facetList);
        $this->assertEquals($facetList[0]['value'], '0/Book/');
        $this->assertEquals($facetList[0]['level'], 0);
        $this->assertFalse($facetList[0]['isApplied']);
        $this->assertFalse($facetList[0]['hasAppliedChildren']);
        $this->assertEquals(
            $facetList[0]['children'][0]['value'], '1/Book/BookPart/'
        );
        $this->assertEquals($facetList[0]['children'][0]['level'], 1);
        $this->assertFalse($facetList[0]['children'][0]['isApplied']);
        $this->assertEquals($facetList[1]['value'], '0/AV/');
        $this->assertEquals($facetList[2]['value'], '0/Audio/');
        $this->assertEquals(
            $facetList[2]['children'][0]['value'], '1/Audio/Spoken/'
        );
        $this->assertEquals($facetList[2]['children'][1]['value'], '1/Audio/Music/');

        // Test with active filter
        $facetList = $this->helper->buildFacetArray(
            'format',
            $this->setApplied('1/Book/BookPart/', $this->facetList)
        );
        $this->assertEquals($facetList[0]['value'], '0/Book/');
        $this->assertFalse($facetList[0]['isApplied']);
        $this->assertTrue($facetList[0]['hasAppliedChildren']);
        $this->assertEquals(
            $facetList[0]['children'][0]['value'], '1/Book/BookPart/'
        );
        $this->assertEquals($facetList[0]['children'][0]['isApplied'], true);

    }

    /**
     * Tests for flattenFacetHierarchy
     */
    public function testFlattenFacetHierarchy()
    {
        $facetList = $this->helper->flattenFacetHierarchy(
            $this->helper->buildFacetArray(
                'format', $this->facetList
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

    /**
     * Tests for formatDisplayText
     */
    public function testFormatDisplayText()
    {
        $this->assertEquals(
            $this->helper->formatDisplayText('0/Sound/')->getDisplayString(),
            'Sound'
        );
        $this->assertEquals(
            $this->helper->formatDisplayText('1/Sound/Noisy/')->getDisplayString(),
            'Noisy'
        );
        $this->assertEquals(
            $this->helper->formatDisplayText('1/Sound/Noisy/', true)
                ->getDisplayString(),
            'Sound/Noisy'
        );
        $this->assertEquals(
            $this->helper->formatDisplayText('1/Sound/Noisy/', true, ' - ')
                ->getDisplayString(),
            'Sound - Noisy'
        );
        $this->assertEquals(
            $this->helper->formatDisplayText('0/Sound/'),
            '0/Sound/'
        );
        $this->assertEquals(
            (string)$this->helper->formatDisplayText('1/Sound/Noisy/', true),
            '1/Sound/Noisy/'
        );
        $this->assertEquals(
            (string)$this->helper->formatDisplayText('1/Sound/Noisy/', true, ' - '),
            '1/Sound/Noisy/'
        );
    }

    /**
     * Set 'isApplied' to true in facet item with the given value
     *
     * @param string $facetValue Value to search for
     * @param string $facetList  Facet list
     *
     * @return array Facet list
     */
    protected function setApplied($facetValue, $facetList)
    {
        foreach ($facetList as &$facetItem) {
            if ($facetItem['value'] == $facetValue) {
                $facetItem['isApplied']  = true;
            }
        }
        return $facetList;
    }
}
