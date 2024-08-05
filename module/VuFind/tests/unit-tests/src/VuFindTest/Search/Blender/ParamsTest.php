<?php

/**
 * Blender Params Test
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\Blender;

use Laminas\Config\Config;
use Laminas\Stdlib\Parameters;
use VuFind\Search\Blender\Options;
use VuFind\Search\Blender\Params;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Blender Params Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Blender config
     *
     * @var array
     */
    protected $config = [
        'Backends' => [
            'Solr' => 'Local',
            'Primo' => 'CDI',
            'EDS' => 'EBSCO',
        ],
        'Blending' => [
            'initialResults' => [
                'Solr',
                'Solr',
                'Primo',
                'EDS',
                'Primo',
                'EDS',
            ],
        ],
    ];

    /**
     * Mappings
     *
     * @var array
     */
    protected $mappings = [
        'Facets' => [
            'Fields' => [
                'building' => [
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'building',
                        ],
                        'EDS' => [
                            'Field' => 'building',
                            'Hierarchical' => true,
                            'Values' => [
                                'main' => '0/Main/',
                                'sub' => '1/Sub/Fiction/',
                            ],
                        ],
                    ],
                ],
                'format' => [
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'formatSolr',
                        ],
                        'Primo' => [
                            'Field' => 'formatPrimo',
                            'Values' => [
                                'barPrimo' => 'bar',
                                'bazPrimo' => 'baz',
                                'double1' => 'double',
                                'double2' => 'double',
                            ],
                        ],
                    ],
                ],
                'fulltext' => [
                    'Type' => 'boolean',
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'fulltext_boolean',
                        ],
                        'Primo' => [
                            'Field' => 'pcAvailability',
                            'Values' => [
                                'false' => '1',

                            ],
                            'DefaultValue' => 'true',
                        ],
                        'EDS' => [
                            'Field' => 'LIMIT|FT',
                            'Values' => [
                                'y' => '1',
                            ],
                        ],
                    ],
                ],
                'publish_date' => [
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'publishDate',
                        ],
                        'Primo' => [
                            'Field' => 'creationdate',
                        ],
                        'EDS' => [
                            'Field' => 'PublicationDate',
                        ],
                    ],
                ],
            ],
        ],
        'Search' => [
            'Fields' => [
                'AllFields' => [
                    'Mappings' => [
                        'Solr' => 'AllFields',
                        'Primo' => 'AllFields',
                        'EDS' => 'AllFields',
                    ],
                ],
                'Title' => [
                    'Mappings' => [
                        'Solr' => 'Title',
                        'Primo' => 'Title',
                        'EDS' => 'TI',
                    ],
                ],
            ],
        ],
        'Sorting' => [
            'Fields' => [
                'relevance' => [
                    'Mappings' => [
                        'Solr' => 'relevance',
                        'Primo' => 'relevance',
                        'EDS' => 'relevance',
                    ],
                ],
                'year' => [
                    'Mappings' => [
                        'Solr' => 'year',
                        'Primo' => 'scdate',
                        'EDS' => 'date',
                    ],
                ],
            ],
        ],
    ];

    /**
     * EDS API configuration
     *
     * @var array
     */
    protected $edsApiConfig = [
        'AvailableSearchCriteria' => [
            'AvailableSorts' => [
                [
                    'Id' => 'date',
                    'Label' => 'Date Newest',
                ],
            ],
        ],
    ];

    /**
     * EDS configuration
     *
     * @var array
     */
    protected $edsConfig = [
        'Sorting' => [
            'relevance' => 'relevance',
            'date' => 'year',
        ],
    ];

    /**
     * Primo configuration
     *
     * @var array
     */
    protected $primoConfig = [
        'Sorting' => [
            'relevance' => 'relevance',
            'scdate' => 'year',
        ],
    ];

    /**
     * Blender configuration
     *
     * @var array
     */
    protected $blenderConfig = [
        'SpecialFacets' => [
            'dateRange' => [
                'publish_date',
            ],
        ],
    ];

    /**
     * Mock config manager
     *
     * @var object
     */
    protected $configManager = null;

    /**
     * Test that we get the correct search class ID.
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $this->assertEquals('Blender', $this->getParams()->getSearchClassId());
    }

    /**
     * Test that facets and filters work as expected.
     *
     * @return void
     */
    public function testFacetsAndFilters(): void
    {
        // None by default:
        $params = $this->getParams();
        $this->assertEquals([], $params->getCheckboxFacets());

        // Adding one works:
        $params->addCheckboxFacet('format:bar', 'checkbox_label');
        $this->assertEquals(
            [
                [
                    'desc' => 'checkbox_label',
                    'filter' => 'format:bar',
                    'selected' => false,
                    'alwaysVisible' => false,
                    'dynamic' => false,
                ],
            ],
            $params->getCheckboxFacets()
        );

        // Selecting one works:
        $params->addFilter('format:bar');
        $this->assertEquals(
            [
                [
                    'desc' => 'checkbox_label',
                    'filter' => 'format:bar',
                    'selected' => true,
                    'alwaysVisible' => false,
                    'dynamic' => false,
                ],
            ],
            $params->getCheckboxFacets()
        );

        $backendParams = $params->getBackendParameters();
        // Make sure EDS is disabled since we don't have a mapping for it:
        $this->assertEquals(
            [
                'format:"bar"',
                '-blender_backend:"EDS"',
            ],
            $backendParams->get('fq')
        );

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $this->assertInstanceOf(ParamBag::class, $solrParams);
        $this->assertInstanceOf(ParamBag::class, $primoParams);

        $this->assertEquals(
            ['formatSolr:"bar"'],
            $solrParams->get('fq')
        );
        $this->assertEquals(
            [
                [
                    'field' => 'formatPrimo',
                    'facetOp' => 'AND',
                    'values' => ['barPrimo'],
                ],
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );

        // Remove a filter and check that EDS is enabled again:
        $params->removeFilter('format:bar');
        $backendParams = $params->getBackendParameters();
        $this->assertNull($backendParams->get('fq'));

        // Add multiple filters:
        $params->addFilter('~format:bar');
        $params->addFilter('~format:baz');
        $params->addFilter('fulltext:1');
        $this->assertTrue($params->hasFilter('~format:bar'));
        $this->assertTrue($params->hasFilter('~format:baz'));
        $this->assertTrue($params->hasFilter('fulltext:1'));
        $backendParams = $params->getBackendParameters();
        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertEquals(
            [
                'fulltext_boolean:"1"',
                '{!tag=formatSolr_filter}formatSolr:(formatSolr:"bar"'
                    . ' OR formatSolr:"baz")',
            ],
            $solrParams->get('fq')
        );
        $this->assertEquals(
            [
                [
                    'field' => 'formatPrimo',
                    'facetOp' => 'OR',
                    'values' => [
                        'barPrimo',
                        'bazPrimo',
                    ],
                ],
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['false'],
                ],
            ],
            $primoParams->get('filterList')
        );
        $this->assertEquals(['LIMIT|FT:y'], $edsParams->get('filters'));

        // Remove format filters and verify:
        $params->removeFilter('~format:bar');
        $params->removeFilter('~format:baz');
        $backendParams = $params->getBackendParameters();
        $this->assertEquals(['fulltext:"1"'], $backendParams->get('fq'));

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertEquals(['fulltext_boolean:"1"'], $solrParams->get('fq'));
        $this->assertEquals(
            [
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['false'],
                ],
            ],
            $primoParams->get('filterList')
        );
        $this->assertEquals(['LIMIT|FT:y'], $edsParams->get('filters'));

        $params->removeAllFilters('building');
        $params->removeFilter('fulltext:1');
        $backendParams = $params->getBackendParameters();
        $this->assertNull($backendParams->get('fq'));

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertNull($solrParams->get('fq'));
        $this->assertEquals(
            [
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );
        $this->assertNull($edsParams->get('filters'));

        // Set an overriding value:
        $params = $this->getParams();
        $params->addFilter('fulltext:1');
        $backendParams = $params->getBackendParameters();
        $this->assertEquals(['fulltext:"1"'], $backendParams->get('fq'));

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertEquals(['fulltext_boolean:"1"'], $solrParams->get('fq'));
        $this->assertEquals(
            [
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['false'],
                ],
            ],
            $primoParams->get('filterList')
        );
        $this->assertEquals(['LIMIT|FT:y'], $edsParams->get('filters'));

        // A filter that no backend supports:
        $params = $this->getParams();
        $params->addFilter('nonexistent:1');
        $backendParams = $params->getBackendParameters();
        $this->assertEquals(
            [
                'nonexistent:"1"',
                '-blender_backend:"Solr"',
                '-blender_backend:"Primo"',
                '-blender_backend:"EDS"',
            ],
            $backendParams->get('fq')
        );

        // Add a filter that maps to two values:
        $params->addFilter('format:double');
        $primoParams = $params->getBackendParameters()->get('params_Primo')[0];
        $this->assertEquals(
            [
                [
                    'field' => 'formatPrimo',
                    'facetOp' => 'OR',
                    'values' => [
                        'double1',
                        'double2',
                    ],
                ],
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );

        // Add a NOT filter that maps to two values in Primo:
        $params->removeAllFilters('format');
        $params->addFilter('-format:double');
        $backendParams = $params->getBackendParameters();
        $solrParams = $backendParams->get('params_Solr')[0];
        $this->assertEquals(['-formatSolr:"double"'], $solrParams->get('fq'));
        $primoParams = $backendParams->get('params_Primo')[0];
        $this->assertEquals(
            [
                [
                    'field' => 'formatPrimo',
                    'facetOp' => 'NOT',
                    'values' => [
                        'double1',
                        'double2',
                    ],
                ],
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );

        // Remove the NOT filter and check results:
        $params->removeFilter('-format:double');
        $backendParams = $params->getBackendParameters();
        $solrParams = $backendParams->get('params_Solr')[0];
        $this->assertEquals(null, $solrParams->get('fq'));
        $primoParams = $backendParams->get('params_Primo')[0];
        $this->assertEquals(
            [
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );

        // Test removeAllFilters:
        $params->addFilter('nonexistent:1');
        $params->addFilter('format:double');
        $params->removeAllFilters();
        $backendParams = $params->getBackendParameters();
        $this->assertNull($backendParams->get('fq'));

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertNull($solrParams->get('fq'));
        $this->assertEquals(
            [
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );
        $this->assertNull($edsParams->get('filters'));

        // Check that simply adding a checkbox facet does not cause backends to be
        // disabled:
        $params = $this->getParams();
        $params->addFilter('fulltext:1');
        $backendParams = $params->getBackendParameters();
        $this->assertEquals(
            [
                'fulltext:"1"',
            ],
            $backendParams->get('fq')
        );

        // Test a daterange filter:
        $params = $this->getParams();
        $params->addFilter('publish_date:[2020 TO 2022]');
        $backendParams = $params->getBackendParameters();
        $this->assertEquals(
            ['publish_date:[2020 TO 2022]'],
            $backendParams->get('fq')
        );

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertEquals(['publishDate:[2020 TO 2022]'], $solrParams->get('fq'));
        $this->assertEquals(
            [
                [
                    'field' => 'creationdate',
                    'facetOp' => 'AND',
                    'values' => ['[2020 TO 2022]'],
                ],
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );
        $this->assertEquals(
            ['PublicationDate:[2020 TO 2022]'],
            $edsParams->get('filters')
        );
    }

    /**
     * Test that hidden filters work as expected.
     *
     * @return void
     */
    public function testHiddenFilters(): void
    {
        $params = $this->getParams();

        $params->addHiddenFilter('format:bar');
        $backendParams = $params->getBackendParameters();
        // Make sure EDS is disabled since we don't have a mapping for it:
        $this->assertEquals(
            [
                'format:"bar"',
                '-blender_backend:"EDS"',
            ],
            $backendParams->get('fq')
        );

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $this->assertInstanceOf(ParamBag::class, $solrParams);
        $this->assertInstanceOf(ParamBag::class, $primoParams);

        $this->assertEquals(
            ['formatSolr:"bar"'],
            $solrParams->get('fq')
        );
        $this->assertEquals(
            [
                [
                    'field' => 'formatPrimo',
                    'facetOp' => 'AND',
                    'values' => ['barPrimo'],
                ],
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );
    }

    /**
     * Test that special blender_backend filters work as expected.
     *
     * @return void
     */
    public function testBlenderFilters(): void
    {
        // Add as a normal filter:
        $params = $this->getParams();
        $params->addFilter('blender_backend:Primo');
        $backendParams = $params->getBackendParameters();

        $this->assertEquals(['blender_backend:"Primo"'], $backendParams->get('fq'));
        $solrParams = $backendParams->get('params_Solr')[0];
        $this->assertInstanceOf(ParamBag::class, $solrParams);
        $this->assertNull($solrParams->get('fq'));

        // Remove the filter and check:
        $params->removeFilter('blender_backend:Primo');
        $backendParams = $params->getBackendParameters();
        $this->assertNull($backendParams->get('fq'));

        // Add as a hidden filter:
        $params = $this->getParams();
        $params->addHiddenFilter('~blender_backend:Primo');
        $params->addHiddenFilter('~blender_backend:EDS');
        $backendParams = $params->getBackendParameters();

        $this->assertEquals(
            [
                '{!tag=blender_backend_filter}blender_backend:('
                . 'blender_backend:"Primo" OR blender_backend:"EDS")',
            ],
            $backendParams->get('fq')
        );
        $solrParams = $backendParams->get('params_Solr')[0];
        $this->assertInstanceOf(ParamBag::class, $solrParams);
        $this->assertNull($solrParams->get('fq'));
    }

    /**
     * Test that special blender_backend checkbox facets work as expected.
     *
     * @return void
     */
    public function testBlenderFacets(): void
    {
        $params = $this->getParams();
        $params->addCheckboxFacet('blender_backend:Primo', 'Primo');
        $this->assertEquals(
            [
                [
                    'desc' => 'Primo',
                    'filter' => 'blender_backend:Primo',
                    'selected' => false,
                    'alwaysVisible' => false,
                    'dynamic' => false,
                ],
            ],
            $params->getCheckboxFacets()
        );
        $backendParams = $params->getBackendParameters();

        // Make sure no backend was disabled:
        $this->assertNull($backendParams->get('fq'));
        $primoParams = $backendParams->get('params_Primo')[0];
        $this->assertInstanceOf(ParamBag::class, $primoParams);
        $this->assertEquals(
            [
                [
                    'field' => 'pcAvailability',
                    'facetOp' => 'AND',
                    'values' => ['true'],
                ],
            ],
            $primoParams->get('filterList')
        );
    }

    /**
     * Test that ignoring filters works as expected.
     *
     * @return void
     */
    public function testIgnoredFilters(): void
    {
        $params = $this->getParams();
        $params->addHiddenFilter('format:bar');
        $backendParams = $params->getBackendParameters();
        // Make sure EDS is disabled since we don't have a mapping for it:
        $this->assertEquals(
            [
                'format:"bar"',
                '-blender_backend:"EDS"',
            ],
            $backendParams->get('fq')
        );

        // Test ignoring all values:
        $mappings = $this->mappings;
        $mappings['Facets']['Fields']['format']['Mappings']['EDS'] = [
            'Ignore' => true,
        ];
        $params = $this->getParams(null, $mappings);
        $params->addHiddenFilter('format:bar');
        $backendParams = $params->getBackendParameters();
        // Make sure EDS is NOT disabled:
        $this->assertEquals(
            [
                'format:"bar"',
            ],
            $backendParams->get('fq')
        );
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertInstanceOf(ParamBag::class, $edsParams);
        $this->assertNull($edsParams->get('filters'));

        // Test ignoring one value:
        $mappings = $this->mappings;
        $mappings['Facets']['Fields']['format']['Mappings']['EDS'] = [
            'Ignore' => [
                'bar',
            ],
        ];
        $params = $this->getParams(null, $mappings);
        $params->addHiddenFilter('format:bar');
        $backendParams = $params->getBackendParameters();
        // Make sure EDS is NOT disabled but doesn't have filters either:
        $this->assertEquals(
            [
                'format:"bar"',
            ],
            $backendParams->get('fq')
        );
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertInstanceOf(ParamBag::class, $edsParams);
        $this->assertNull($edsParams->get('filters'));

        // Add another value and verify that EDS gets disabled:
        $params->addHiddenFilter('format:baz');
        $backendParams = $params->getBackendParameters();
        // Make sure EDS is NOT disabled:
        $this->assertEquals(
            [
                'format:"bar"',
                'format:"baz"',
                '-blender_backend:"EDS"',
            ],
            $backendParams->get('fq')
        );
    }

    /**
     * Test that getFacetLabel works as expected.
     *
     * Note: This just makes sure that things look the same as with Base\Params
     * without exercising Blender-specific functionality.
     *
     * @return void
     */
    public function testGetFacetLabel(): void
    {
        $params = $this->getParams();
        // If we haven't set up any facets yet, labels will be unrecognized:
        $this->assertEquals('unrecognized_facet_label', $params->getFacetLabel('foo'));

        // Now if we add a facet, we should get the label back:
        $params->addFacet('foo', 'foo_label');
        $this->assertEquals('foo_label', $params->getFacetLabel('foo'));

        // Make sure that a checkbox facet with correct value gets the label for the
        // checkbox:
        $params->addCheckboxFacet('foo:bar', 'checkbox_label');
        $this->assertEquals('checkbox_label', $params->getFacetLabel('foo', 'bar'));
        $this->assertEquals('foo_label', $params->getFacetLabel('foo', 'baz'));
        $this->assertEquals('foo_label', $params->getFacetLabel('foo'));

        // Check that reset works:
        $params->resetFacetConfig();
        $this->assertEquals([], $params->getFacetConfig());
    }

    /**
     * Test that facet mappings work as expected.
     *
     * @return void
     */
    public function testFacetMappings(): void
    {
        $params = $this->getParams();
        $params->addFacet('building', 'Building', true);
        $params->addFilter('building:0/Main/');

        $backendParams = $params->getBackendParameters();
        $this->assertEquals(
            [
                'building:"0/Main/"',
                '-blender_backend:"Primo"',
            ],
            $backendParams->get('fq')
        );

        $solrParams = $backendParams->get('params_Solr')[0];
        $edsParams = $backendParams->get('params_EDS')[0];

        $this->assertEquals(
            [
                'spellcheck' => [
                    'true',
                ],
                'facet' => [
                    'true',
                ],
                'facet.limit' => [
                    30,
                ],
                'facet.field' => [
                    '{!ex=building_filter}building',
                ],
                'facet.sort' => [
                    'count',
                ],
                'facet.mincount' => [
                    1,
                ],
                'fq' => [
                    'building:"0/Main/"',
                ],
                'hl' => [
                    'false',
                ],
            ],
            $solrParams->getArrayCopy()
        );

        $this->assertEquals(
            [
                'sort' => [
                    null,
                ],
                'view' => [
                    'brief',
                ],
                'filters' => [
                    'building:OR:main',
                ],
            ],
            $edsParams->getArrayCopy()
        );

        // Test sub-level facet mapping:
        $params = $this->getParams();
        $params->addFacet('building', 'Building', true);
        $params->addFilter('building:0/Sub/');

        $backendParams = $params->getBackendParameters();
        $this->assertEquals(
            [
                'building:"0/Sub/"',
                '-blender_backend:"Primo"',
            ],
            $backendParams->get('fq')
        );

        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertEquals(['building:OR:sub'], $edsParams->get('filters'));
    }

    /**
     * Test that search type mappings work as expected.
     *
     * @return void
     */
    public function testSearchTypeMappings(): void
    {
        $params = $this->getParams();
        $params->setBasicSearch('foo', 'AllFields');
        $backendParams = $params->getBackendParameters();

        $solrQuery = $backendParams->get('query_Solr')[0];
        $primoQuery = $backendParams->get('query_Primo')[0];
        $edsQuery = $backendParams->get('query_EDS')[0];
        $this->assertInstanceOf(Query::class, $solrQuery);
        $this->assertInstanceOf(Query::class, $primoQuery);
        $this->assertInstanceOf(Query::class, $edsQuery);

        $this->assertEquals('foo', $solrQuery->getString());
        $this->assertEquals('AllFields', $solrQuery->getHandler());
        $this->assertEquals('foo', $primoQuery->getString());
        $this->assertEquals('AllFields', $primoQuery->getHandler());
        $this->assertEquals('foo', $edsQuery->getString());
        $this->assertEquals('AllFields', $edsQuery->getHandler());

        $params->setBasicSearch('foo', 'Title');
        $backendParams = $params->getBackendParameters();

        $solrQuery = $backendParams->get('query_Solr')[0];
        $primoQuery = $backendParams->get('query_Primo')[0];
        $edsQuery = $backendParams->get('query_EDS')[0];

        $this->assertEquals('foo', $solrQuery->getString());
        $this->assertEquals('Title', $solrQuery->getHandler());
        $this->assertEquals('foo', $primoQuery->getString());
        $this->assertEquals('Title', $primoQuery->getHandler());
        $this->assertEquals('foo', $edsQuery->getString());
        $this->assertEquals('TI', $edsQuery->getHandler());
    }

    /**
     * Test that sort mappings work as expected.
     *
     * @return void
     */
    public function testSortMappings(): void
    {
        $params = $this->getParams();
        $params->setSort('year');
        $backendParams = $params->getBackendParameters();

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertInstanceOf(ParamBag::class, $solrParams);
        $this->assertInstanceOf(ParamBag::class, $primoParams);
        $this->assertInstanceOf(ParamBag::class, $edsParams);

        $this->assertEquals(['publishDateSort desc'], $solrParams->get('sort'));
        $this->assertEquals(['scdate'], $primoParams->get('sort'));
        $this->assertEquals(['date'], $edsParams->get('sort'));
    }

    /**
     * Test that initFromRequest works as expected.
     *
     * @return void
     */
    public function testInitFromRequest(): void
    {
        $query = [
            'lookfor' => 'foo',
            'type'  => 'Title',
            'sort' => 'year',
            'page' => '2',
            'hiddenFilters' => ['foo:"baz"'],
        ];
        $params = $this->getParams();
        $params->initFromRequest(new Parameters($query));
        $backendParams = $params->getBackendParameters();

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertInstanceOf(ParamBag::class, $solrParams);
        $this->assertInstanceOf(ParamBag::class, $primoParams);
        $this->assertInstanceOf(ParamBag::class, $edsParams);
        $this->assertNull($solrParams->get('fq'));

        $solrQuery = $backendParams->get('query_Solr')[0];
        $primoQuery = $backendParams->get('query_Primo')[0];
        $edsQuery = $backendParams->get('query_EDS')[0];
        $this->assertInstanceOf(Query::class, $solrQuery);
        $this->assertInstanceOf(Query::class, $primoQuery);
        $this->assertInstanceOf(Query::class, $edsQuery);

        $this->assertEquals('foo', $solrQuery->getString());
        $this->assertEquals('Title', $solrQuery->getHandler());
        $this->assertEquals('foo', $primoQuery->getString());
        $this->assertEquals('Title', $primoQuery->getHandler());
        $this->assertEquals('foo', $edsQuery->getString());
        $this->assertEquals('TI', $edsQuery->getHandler());

        // Test empty request:
        $params = $this->getParams();
        $params->initFromRequest(new Parameters([]));
        $backendParams = $params->getBackendParameters();

        $solrParams = $backendParams->get('params_Solr')[0];
        $primoParams = $backendParams->get('params_Primo')[0];
        $edsParams = $backendParams->get('params_EDS')[0];
        $this->assertInstanceOf(ParamBag::class, $solrParams);
        $this->assertInstanceOf(ParamBag::class, $primoParams);
        $this->assertInstanceOf(ParamBag::class, $edsParams);

        $solrQuery = $backendParams->get('query_Solr')[0];
        $primoQuery = $backendParams->get('query_Primo')[0];
        $edsQuery = $backendParams->get('query_EDS')[0];
        $this->assertInstanceOf(Query::class, $solrQuery);
        $this->assertInstanceOf(Query::class, $primoQuery);
        $this->assertInstanceOf(Query::class, $edsQuery);
    }

    /**
     * Test that an invalid backend in configuration is handled properly.
     *
     * @return void
     */
    public function testInvalidBackend(): void
    {
        $configMgr = $this->createMock(\VuFind\Config\PluginManager::class);
        $baseParams = new \VuFind\Search\EmptySet\Params(
            new \VuFind\Search\EmptySet\Options($configMgr),
            $configMgr
        );
        $params = new Params(
            new Options($configMgr),
            $configMgr,
            new HierarchicalFacetHelper(),
            [
                'Base' => $baseParams,
            ],
            new Config($this->config),
            $this->mappings
        );

        $this->expectExceptionMessage(
            'Backend EmptySet missing support for getBackendParameters'
        );
        $params->getBackendParameters();
    }

    /**
     * Get mock config manager
     *
     * @return object
     */
    protected function getConfigManager()
    {
        $configs = [
            'EDS' => new Config($this->edsConfig),
            'Primo' => new Config($this->primoConfig),
            'Blender' => new Config($this->blenderConfig),
        ];

        $callback = function (string $configName) use ($configs) {
            return $configs[$configName] ?? null;
        };

        $configManager = $this->getMockBuilder(\VuFind\Config\PluginManager::class)
                ->disableOriginalConstructor()
                ->getMock();
        $configManager
            ->expects($this->any())
            ->method('get')
            ->will($this->returnCallback($callback));

        return $configManager;
    }

    /**
     * Get params classes for an array of backends
     *
     * @return array
     */
    protected function getParamsClassesArray(): array
    {
        $solrConfigMgr = $this->createMock(\VuFind\Config\PluginManager::class);
        $configMgr = $this->getConfigManager();

        $result = [];
        $result[] = new \VuFind\Search\Solr\Params(
            new \VuFind\Search\Solr\Options($solrConfigMgr),
            $solrConfigMgr
        );
        $result[] = new \VuFind\Search\Primo\Params(
            new \VuFind\Search\Primo\Options($configMgr),
            $configMgr
        );
        $result[] = new \VuFind\Search\EDS\Params(
            new \VuFind\Search\EDS\Options($configMgr, $this->edsApiConfig),
            $configMgr
        );

        return $result;
    }

    /**
     * Get Params class
     *
     * @param array $config   Blender configuration, overrides defaults
     * @param array $mappings Blender mappings, overrides defaults
     *
     * @return Params
     */
    protected function getParams($config = null, $mappings = null): Params
    {
        $configMgr = $this->createMock(\VuFind\Config\PluginManager::class);
        return new Params(
            new Options($configMgr),
            $configMgr,
            new HierarchicalFacetHelper(),
            $this->getParamsClassesArray(),
            new Config($config ?? $this->config),
            $mappings ?? $this->mappings
        );
    }
}
