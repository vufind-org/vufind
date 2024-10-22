<?php

/**
 * EDS Search Request Model Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\EDS;

use VuFindSearch\Backend\EDS\SearchRequestModel;

/**
 * EDS Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SearchRequestModelTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test filter values in AND mode.
     *
     * @return void
     */
    public function testAndFilters()
    {
        $parameters = [
            'filters' => [
                'SubjectEDS:AND:reading rainbow',
                'SubjectEDS:AND:books',
            ],
        ];
        $model = new SearchRequestModel($parameters);

        $this->assertEquals([
            'facetfilter' => [
                '1,SubjectEDS:reading rainbow',
                '2,SubjectEDS:books',
            ],
            'highlight' => 'n',
        ], $model->convertToQueryStringParameterArray());

        $this->assertEquals(
            '{
    "SearchCriteria": {
        "FacetFilters": [
            {
                "FilterId": 1,
                "FacetValues": [
                    {
                        "Id": "SubjectEDS",
                        "Value": "reading rainbow"
                    }
                ]
            },
            {
                "FilterId": 2,
                "FacetValues": [
                    {
                        "Id": "SubjectEDS",
                        "Value": "books"
                    }
                ]
            }
        ],
        "IncludeFacets": "y"
    },
    "RetrievalCriteria": {
        "Highlight": "n"
    },
    "Actions": null
}',
            $model->convertToSearchRequestJSON()
        );
    }

    /**
     * Test filter values in OR mode.
     *
     * @return void
     */
    public function testOrFilters()
    {
        $parameters = [
            'filters' => [
                'SubjectEDS:OR:reading rainbow',
                'SubjectEDS:OR:books',
            ],
        ];
        $model = new SearchRequestModel($parameters);

        $this->assertEquals([
            'facetfilter' => [
                '1,SubjectEDS:reading rainbow,SubjectEDS:books',
            ],
            'highlight' => 'n',
        ], $model->convertToQueryStringParameterArray());

        $this->assertEquals(
            '{
    "SearchCriteria": {
        "FacetFilters": [
            {
                "FilterId": 1,
                "FacetValues": [
                    {
                        "Id": "SubjectEDS",
                        "Value": "reading rainbow"
                    },
                    {
                        "Id": "SubjectEDS",
                        "Value": "books"
                    }
                ]
            }
        ],
        "IncludeFacets": "y"
    },
    "RetrievalCriteria": {
        "Highlight": "n"
    },
    "Actions": null
}',
            $model->convertToSearchRequestJSON()
        );
    }

    /**
     * Test limiter values, always in OR mode.
     *
     * @return void
     */
    public function testLimiters()
    {
        $parameters = [
            'filters' => [
                'LIMIT|FT1:y',
                'LIMIT|LA99:Polish',
                'LIMIT|LA99:Romanian',
            ],
        ];
        $model = new SearchRequestModel($parameters);

        $this->assertEquals([
            'highlight' => 'n',
            'limiter' => [
                'FT1' => [
                    'y',
                ],
                'LA99' => [
                    'Polish',
                    'Romanian',
                ],
            ],
        ], $model->convertToQueryStringParameterArray());

        $this->assertEquals(
            '{
    "SearchCriteria": {
        "Limiters": [
            {
                "Id": "FT1",
                "Values": [
                    "y"
                ]
            },
            {
                "Id": "LA99",
                "Values": [
                    "Polish",
                    "Romanian"
                ]
            }
        ],
        "IncludeFacets": "y"
    },
    "RetrievalCriteria": {
        "Highlight": "n"
    },
    "Actions": null
}',
            $model->convertToSearchRequestJSON()
        );
    }
}
