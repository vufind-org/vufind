<?php

/**
 * Unit tests for Hierarchical Facet Helper.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFindTest\Search\Solr;

use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFindTest\Unit\TestCase;

/**
 * Unit tests for Hierarchical Facet Helper.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 * @todo     Test buildFacetArray using url helper
 */
class HierarchicalFacetHelperTest extends TestCase
{
    /**
     * Test input data.
     *
     * @var array
     */
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
     * Invalid test input data.
     *
     * @var array
     */
    protected $invalidFacetList = [
        [
            'value' => 'Book',
            'displayText' => 'Book',
            'count' => 1000,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => 'AV',
            'displayText' => 'Audiovisual',
            'count' => 600,
            'operator' => 'OR',
            'isApplied' => false
        ],
        [
            'value' => 'Audio',
            'displayText' => 'Sound',
            'count' => 400,
            'operator' => 'OR',
            'isApplied' => false
        ],
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
    protected function setUp(): void
    {
        $this->helper = new HierarchicalFacetHelper();
    }

    /**
     * Tests for sortFacetList (default/count sort -- at present these should
     * make no changes to the input data and can thus both be tested in a single
     * test method).
     *
     * @return void
     */
    public function testSortFacetListDefault()
    {
        $facetList = $this->facetList;
        $this->helper->sortFacetList($facetList);
        $this->assertEquals('0/Book/', $facetList[0]['value']);
        $this->assertEquals('0/AV/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals('1/Book/BookPart/', $facetList[3]['value']);
        $this->assertEquals('1/Book/Section/', $facetList[4]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facetList[5]['value']);
        $this->assertEquals('1/Audio/Music/', $facetList[6]['value']);
        $this->helper->sortFacetList($facetList, 'count');
        $this->assertEquals('0/Book/', $facetList[0]['value']);
        $this->assertEquals('0/AV/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals('1/Book/BookPart/', $facetList[3]['value']);
        $this->assertEquals('1/Book/Section/', $facetList[4]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facetList[5]['value']);
        $this->assertEquals('1/Audio/Music/', $facetList[6]['value']);
    }

    /**
     * Tests for sortFacetList (top level only, specified with boolean)
     *
     * @return void
     */
    public function testSortFacetListTopLevelBooleanTrue()
    {
        $facetList = $this->facetList;
        $this->helper->sortFacetList($facetList, true);
        $this->assertEquals('0/AV/', $facetList[0]['value']);
        $this->assertEquals('0/Book/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals('1/Book/BookPart/', $facetList[3]['value']);
        $this->assertEquals('1/Book/Section/', $facetList[4]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facetList[5]['value']);
        $this->assertEquals('1/Audio/Music/', $facetList[6]['value']);
    }

    /**
     * Tests for sortFacetList (top level only, specified with string)
     *
     * @return void
     */
    public function testSortFacetListTopLevelStringConfig()
    {
        $facetList = $this->facetList;
        $this->helper->sortFacetList($facetList, 'top');
        $this->assertEquals('0/AV/', $facetList[0]['value']);
        $this->assertEquals('0/Book/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals('1/Book/BookPart/', $facetList[3]['value']);
        $this->assertEquals('1/Book/Section/', $facetList[4]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facetList[5]['value']);
        $this->assertEquals('1/Audio/Music/', $facetList[6]['value']);
    }

    /**
     * Tests for sortFacetList (all levels, specified with boolean)
     *
     * @return void
     */
    public function testSortFacetListAllLevelsBooleanFalse()
    {
        $facetList = $this->facetList;
        $this->helper->sortFacetList($facetList, false);
        $this->assertEquals('0/AV/', $facetList[0]['value']);
        $this->assertEquals('0/Book/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals('1/Book/BookPart/', $facetList[3]['value']);
        $this->assertEquals('1/Book/Section/', $facetList[4]['value']);
        $this->assertEquals('1/Audio/Music/', $facetList[5]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facetList[6]['value']);
    }

    /**
     * Tests for sortFacetList (all levels, specified with string)
     *
     * @return void
     */
    public function testSortFacetListAllLevelsStringConfig()
    {
        $facetList = $this->facetList;
        $this->helper->sortFacetList($facetList, 'all');
        $this->assertEquals('0/AV/', $facetList[0]['value']);
        $this->assertEquals('0/Book/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals('1/Book/BookPart/', $facetList[3]['value']);
        $this->assertEquals('1/Book/Section/', $facetList[4]['value']);
        $this->assertEquals('1/Audio/Music/', $facetList[5]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facetList[6]['value']);
    }

    /**
     * Tests for buildFacetArray
     *
     * @return void
     */
    public function testBuildFacetArray()
    {
        // Test without active filters
        $facetList = $this->helper->buildFacetArray('format', $this->facetList);
        $this->assertEquals('0/Book/', $facetList[0]['value']);
        $this->assertEquals(0, $facetList[0]['level']);
        $this->assertFalse($facetList[0]['isApplied']);
        $this->assertFalse($facetList[0]['hasAppliedChildren']);
        $this->assertEquals(
            $facetList[0]['children'][0]['value'], '1/Book/BookPart/'
        );
        $this->assertEquals(1, $facetList[0]['children'][0]['level']);
        $this->assertFalse($facetList[0]['children'][0]['isApplied']);
        $this->assertEquals('0/AV/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals(
            $facetList[2]['children'][0]['value'], '1/Audio/Spoken/'
        );
        $this->assertEquals('1/Audio/Music/', $facetList[2]['children'][1]['value']);

        // Test with active filter
        $facetList = $this->helper->buildFacetArray(
            'format',
            $this->setApplied('1/Book/BookPart/', $this->facetList)
        );
        $this->assertEquals('0/Book/', $facetList[0]['value']);
        $this->assertFalse($facetList[0]['isApplied']);
        $this->assertTrue($facetList[0]['hasAppliedChildren']);
        $this->assertEquals(
            $facetList[0]['children'][0]['value'], '1/Book/BookPart/'
        );
        $this->assertEquals(true, $facetList[0]['children'][0]['isApplied']);
    }

    /**
     * Tests for buildFacetArray with invalid values
     *
     * @return void
     */
    public function testBuildFacetArrayInvalidValues()
    {
        // Test without active filters
        $facetList = $this->helper
            ->buildFacetArray('format', $this->invalidFacetList);
        $this->assertEquals('Book', $facetList[0]['value']);
        $this->assertEquals(0, $facetList[0]['level']);
        $this->assertFalse($facetList[0]['isApplied']);
        $this->assertFalse($facetList[0]['hasAppliedChildren']);
        $this->assertEquals('AV', $facetList[1]['value']);
        $this->assertEquals('Audio', $facetList[2]['value']);

        // Test with active filter
        $facetList = $this->helper->buildFacetArray(
            'format',
            $this->setApplied('Book', $facetList)
        );
        $this->assertEquals('Book', $facetList[0]['value']);
        $this->assertTrue($facetList[0]['isApplied']);
        $this->assertFalse($facetList[0]['hasAppliedChildren']);
    }

    /**
     * Tests for flattenFacetHierarchy
     *
     * @return void
     */
    public function testFlattenFacetHierarchy()
    {
        $facetList = $this->helper->flattenFacetHierarchy(
            $this->helper->buildFacetArray(
                'format', $this->facetList
            )
        );
        $this->assertEquals('0/Book/', $facetList[0]['value']);
        $this->assertEquals('1/Book/BookPart/', $facetList[1]['value']);
        $this->assertEquals('1/Book/Section/', $facetList[2]['value']);
        $this->assertEquals('0/AV/', $facetList[3]['value']);
        $this->assertEquals('0/Audio/', $facetList[4]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facetList[5]['value']);
        $this->assertEquals('1/Audio/Music/', $facetList[6]['value']);
    }

    /**
     * Tests for formatDisplayText
     *
     * @return void
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
     * Tests for isDeepestFacetLevel
     *
     * @return void
     */
    public function testIsDeepestFacetLevel()
    {
        $facetList = [
            '0/Audio/',
            '1/Audio/Music/',
            '0/AV/',
        ];
        $this->assertFalse(
            $this->helper->isDeepestFacetLevel($facetList, '0/Audio/')
        );
        $this->assertTrue(
            $this->helper->isDeepestFacetLevel($facetList, '1/Audio/Music/')
        );
        $this->assertTrue(
            $this->helper->isDeepestFacetLevel($facetList, '0/AV/')
        );
        $this->assertTrue(
            $this->helper->isDeepestFacetLevel($facetList, '0/XYZZY/')
        );
        $this->assertTrue(
            $this->helper->isDeepestFacetLevel($facetList, 'XYZ')
        );
    }

    /**
     * Tests for getFilterStringParts
     *
     * @return void
     */
    public function testGetFilterStringParts()
    {
        $result = $this->helper->getFilterStringParts('0/Foo/');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('0/Foo/', (string)$result[0]);
        $this->assertEquals('Foo', $result[0]->getDisplayString());

        $result = $this->helper->getFilterStringParts('1/Foo/Bar/');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('0/Foo/', (string)$result[0]);
        $this->assertEquals('1/Foo/Bar/', (string)$result[1]);
        $this->assertEquals('Foo', $result[0]->getDisplayString());
        $this->assertEquals('Bar', $result[1]->getDisplayString());

        $result = $this->helper->getFilterStringParts('Foo/Bar/');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Foo/Bar/', $result[0]);

        $result = $this->helper->getFilterStringParts('Foo');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Foo', $result[0]);
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
