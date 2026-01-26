<?php

/**
 * EDS Results Object Test
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\EDS;

use VuFind\Config\Config;
use VuFind\Record\Loader;
use VuFind\Search\EDS\Params;
use VuFind\Search\EDS\Results;
use VuFindSearch\ParamBag;
use VuFindSearch\Service as SearchService;

/**
 * EDS Results Object Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResultsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Params includes limiter test provider.
     *
     * @return \Iterator
     */
    public static function paramsIncludesLimiterProvider(): \Iterator
    {
        yield 'lots of filters' => [
            [
                'EXPAND:relatedsubjects',
                'EXPAND:fulltext',
                'LIMIT|FT1:y',
                'ContentProvider:OR:Complementary Index',
            ],
            true,
        ];
        yield 'traditional filter only' => [
            [
                'ContentProvider:OR:Complementary Index',
            ],
            true,
        ];
        yield 'explicit limiter only' => [
            [
                'LIMIT|FT1:y',
            ],
            true,
        ];
        yield 'expanders only' => [
            [
                'EXPAND:relatedsubjects',
                'EXPAND:fulltext',
            ],
            false,
        ];
    }

    /**
     * Test paramsIncludesLimiter.
     *
     * @param array $filters         A set of filters
     * @param bool  $includesLimiter Whether the filters include at least one that limits the results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('paramsIncludesLimiterProvider')]
    public function testParamsIncludesLimiter(array $filters, bool $includesLimiter): void
    {
        $params = new ParamBag();
        foreach ($filters as $filter) {
            $params->add('filters', $filter);
        }

        $results = new Results(
            $this->createMock(Params::class),
            $this->createMock(SearchService::class),
            $this->createMock(Loader::class),
            $this->createMock(Config::class)
        );
        $this->assertSame($includesLimiter, $results->paramsIncludeLimiter($params));
    }
}
