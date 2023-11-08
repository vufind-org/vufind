<?php

/**
 * Unit tests for Hierarchical Facet Helper.
 *
 * PHP version 8
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Solr;

use VuFind\I18n\Sorter;
use VuFind\Search\Solr\HierarchicalFacetHelper;

/**
 * Unit tests for Hierarchical Facet Helper.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 * @todo     Test buildFacetArray using url helper
 */
class HierarchicalFacetHelperTest extends \PHPUnit\Framework\TestCase
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
            'isApplied' => false,
        ],
        [
            'value' => '0/AV/',
            'displayText' => 'Audiovisual',
            'count' => 600,
            'operator' => 'OR',
            'isApplied' => false,
        ],
        [
            'value' => '0/Audio/',
            'displayText' => 'Sound',
            'count' => 400,
            'operator' => 'OR',
            'isApplied' => false,
        ],
        [
            'value' => '1/Book/BookPart/',
            'displayText' => 'Book Part',
            'count' => 300,
            'operator' => 'OR',
            'isApplied' => false,
        ],
        [
            'value' => '1/Book/Section/',
            'displayText' => 'Book Section',
            'count' => 200,
            'operator' => 'OR',
            'isApplied' => false,
        ],
        [
            'value' => '1/Audio/Spoken/',
            'displayText' => 'Spoken Text',
            'count' => 100,
            'operator' => 'OR',
            'isApplied' => false,
        ],
        [
            'value' => '1/Audio/Music/',
            'displayText' => 'Music',
            'count' => 50,
            'operator' => 'OR',
            'isApplied' => false,
        ],
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
            'isApplied' => false,
        ],
        [
            'value' => 'AV',
            'displayText' => 'Audiovisual',
            'count' => 600,
            'operator' => 'OR',
            'isApplied' => false,
        ],
        [
            'value' => 'Audio',
            'displayText' => 'Sound',
            'count' => 400,
            'operator' => 'OR',
            'isApplied' => false,
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
        $this->helper->setSorter(new Sorter(new \Collator('en')));
    }

    /**
     * Tests for sortFacetList (default/count sort -- at present these should
     * make no changes to the input data and can thus both be tested in a single
     * test method).
     *
     * @return void
     */
    public function testSortFacetListDefault(): void
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
    public function testSortFacetListTopLevelBooleanTrue(): void
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
    public function testSortFacetListTopLevelStringConfig(): void
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
    public function testSortFacetListAllLevelsBooleanFalse(): void
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
    public function testSortFacetListAllLevelsStringConfig(): void
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
    public function testBuildFacetArray(): void
    {
        // Test without active filters
        $facetList = $this->helper->buildFacetArray('format', $this->facetList);
        $this->assertEquals('0/Book/', $facetList[0]['value']);
        $this->assertEquals(0, $facetList[0]['level']);
        $this->assertFalse($facetList[0]['isApplied']);
        $this->assertFalse($facetList[0]['hasAppliedChildren']);
        $this->assertEquals(
            $facetList[0]['children'][0]['value'],
            '1/Book/BookPart/'
        );
        $this->assertEquals(1, $facetList[0]['children'][0]['level']);
        $this->assertFalse($facetList[0]['children'][0]['isApplied']);
        $this->assertEquals('0/AV/', $facetList[1]['value']);
        $this->assertEquals('0/Audio/', $facetList[2]['value']);
        $this->assertEquals(
            $facetList[2]['children'][0]['value'],
            '1/Audio/Spoken/'
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
            $facetList[0]['children'][0]['value'],
            '1/Book/BookPart/'
        );
        $this->assertEquals(true, $facetList[0]['children'][0]['isApplied']);
    }

    /**
     * Tests for buildFacetArray with invalid values
     *
     * @return void
     */
    public function testBuildFacetArrayInvalidValues(): void
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
    public function testFlattenFacetHierarchy(): void
    {
        $facetList = $this->helper->flattenFacetHierarchy(
            $this->helper->buildFacetArray(
                'format',
                $this->facetList
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
    public function testFormatDisplayText(): void
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
    public function testIsDeepestFacetLevel(): void
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
    public function testGetFilterStringParts(): void
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
     * Test hierarchical exclude filters
     *
     * @return void
     */
    public function testHierarchicalExcludeFilters(): void
    {
        $facet = 'format';
        $facetList = $this->helper->buildFacetArray(
            $facet,
            $this->facetList
        );
        $exclude = [
            '0/Book/',
            '1/Audio/Spoken/',
        ];
        // Always test that the proper values are found in the test data
        $testDataIsok = array_column($this->facetList, 'value');
        $this->assertContains('0/Book/', $testDataIsok);
        $this->assertContains('1/Audio/Spoken/', $testDataIsok);
        $expected = [
            [
                'value' => '0/AV/',
                'displayText' => 'Audiovisual',
                'count' => 600,
                'operator' => 'OR',
                'isApplied' => false,
                'level' => '0',
                'parent' => '',
                'hasAppliedChildren' => false,
                'href' => '',
                'exclude' => '',
                'children' => [],
            ],
            [
                'value' => '0/Audio/',
                'displayText' => 'Sound',
                'count' => 400,
                'operator' => 'OR',
                'isApplied' => false,
                'level' => '0',
                'parent' => '',
                'hasAppliedChildren' => false,
                'href' => '',
                'exclude' => '',
                'children' => [
                    [
                        'value' => '1/Audio/Music/',
                        'displayText' => 'Music',
                        'count' => 50,
                        'operator' => 'OR',
                        'isApplied' => false,
                        'level' => '1',
                        'parent' => '0/Audio/',
                        'hasAppliedChildren' => false,
                        'href' => '',
                        'exclude' => '',
                        'children' => [],
                    ],
                ],
            ],
        ];
        $options = $this->getMockOptions();
        $options->expects($this->any())->method('getHierarchicalExcludeFilters')
            ->will($this->returnValue($exclude));
        $options->expects($this->any())->method('getHierarchicalFacetFilters')
            ->will($this->returnValue([]));
        $filtered = $this->helper->filterFacets($facet, $facetList, $options);
        $this->assertEquals($expected, $filtered);
    }

    /**
     * Test hierarchical facet filters
     *
     * @return void
     */
    public function testHierarchicalFacetFilters(): void
    {
        $facet = 'format';
        $facetList = $this->helper->buildFacetArray(
            $facet,
            $this->facetList
        );
        $filters = [
            '0/Audio/',
        ];
        $expected = [
            [
                'value' => '0/Audio/',
                'displayText' => 'Sound',
                'count' => 400,
                'operator' => 'OR',
                'isApplied' => false,
                'level' => '0',
                'parent' => null,
                'hasAppliedChildren' => false,
                'href' => '',
                'exclude' => '',
                'children' => [
                    [
                        'value' => '1/Audio/Spoken/',
                        'displayText' => 'Spoken Text',
                        'count' => 100,
                        'operator' => 'OR',
                        'isApplied' => false,
                        'level' => '1',
                        'parent' => '0/Audio/',
                        'hasAppliedChildren' => false,
                        'href' => '',
                        'exclude' => '',
                        'children' => [],
                    ],
                    [
                        'value' => '1/Audio/Music/',
                        'displayText' => 'Music',
                        'count' => 50,
                        'operator' => 'OR',
                        'isApplied' => false,
                        'level' => '1',
                        'parent' => '0/Audio/',
                        'hasAppliedChildren' => false,
                        'href' => '',
                        'exclude' => '',
                        'children' => [],
                    ],
                ],
            ],
        ];
        $options = $this->getMockOptions();
        $options->expects($this->any())->method('getHierarchicalExcludeFilters')
            ->will($this->returnValue([]));
        $options->expects($this->any())->method('getHierarchicalFacetFilters')
            ->will($this->returnValue($filters));
        $filtered = $this->helper->filterFacets($facet, $facetList, $options);
        $this->assertEquals($expected, $filtered);
    }

    /**
     * Set 'isApplied' to true in facet item with the given value
     *
     * @param string $facetValue Value to search for
     * @param array  $facetList  Facet list
     *
     * @return array Facet list
     */
    protected function setApplied(string $facetValue, array $facetList): array
    {
        foreach ($facetList as &$facetItem) {
            if ($facetItem['value'] == $facetValue) {
                $facetItem['isApplied']  = true;
            }
        }
        return $facetList;
    }

    /**
     * Create mock options class
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function getMockOptions(): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder(\VuFind\Search\Base\Options::class)
            ->disableOriginalConstructor()->getMock();
    }
}
