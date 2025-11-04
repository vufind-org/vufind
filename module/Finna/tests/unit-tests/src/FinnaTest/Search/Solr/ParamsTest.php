<?php

/**
 * Solr Search Object Parameters Test
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\Search\Solr;

use Finna\Search\Solr\AuthorityHelper;
use Finna\Search\Solr\HierarchicalFacetHelper;
use Finna\Search\Solr\Options;
use Finna\Search\Solr\Params;
use VuFind\Config\PluginManager;
use VuFind\Date\Converter as DateConverter;

/**
 * Solr Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Data provider for testSort
     *
     * @return array
     */
    public static function sortDataProvider(): array
    {
        $searchConfigLegacy = [
            'Sorting' => [
                'relevance,id asc' => 'Relevance',
                'title,id asc' => 'Title',
            ],
        ];
        $searchConfigLegacyWithTieBreaker = [
            'General' => [
                'tie_breaker_sort' => 'id asc',
            ],
            'Sorting' => [
                'relevance,id asc' => 'Relevance',
                'title_sort,id asc' => 'Title',
            ],
        ];
        $searchConfigCurrent = [
            'General' => [
                'tie_breaker_sort' => 'id asc',
            ],
            'Sorting' => [
                'relevance' => 'Relevance',
                'title_sort' => 'Title',
            ],
        ];
        return [
            'legacy config, relevance and id'
                => [$searchConfigLegacy, 'relevance,id asc', 'score desc,id asc'],
            'legacy config, relevance only'
                => [$searchConfigLegacy, 'relevance', 'score desc,id asc'],
            'legacy config, title and id'
                => [$searchConfigLegacy, 'title,id asc', 'title_sort asc,id asc'],
            'legacy config, title only'
                => [$searchConfigLegacy, 'title', 'title_sort asc,id asc'],

            'mixed config, relevance and id'
                => [$searchConfigLegacyWithTieBreaker, 'relevance,id asc', 'score desc,id asc'],
            'mixed config, relevance only'
                => [$searchConfigLegacyWithTieBreaker, 'relevance', 'score desc,id asc'],
            'mixed config, title and id'
                => [$searchConfigLegacyWithTieBreaker, 'title,id asc', 'title_sort asc,id asc'],
            'mixed config, title only'
                => [$searchConfigLegacyWithTieBreaker, 'title', 'title_sort asc,id asc'],

            'current config, relevance and id'
                => [$searchConfigCurrent, 'relevance,id asc', 'score desc,id asc'],
            'current config, relevance only'
                => [$searchConfigCurrent, 'relevance', 'score desc,id asc'],
            'current config, title and id'
                => [$searchConfigCurrent, 'title,id asc', 'title_sort asc,id asc'],
            'current config, title only'
                => [$searchConfigCurrent, 'title', 'title_sort asc,id asc'],
        ];
    }

    /**
     * Test sort option handling
     *
     * @param array  $searchConfig Search configuration
     * @param string $sort         Selected sort option
     * @param string $expectedSort Expected Solr sort string
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sortDataProvider')]
    public function testSort(array $searchConfig, string $sort, string $expectedSort): void
    {
        $params = $this->getParams(mockConfig: $this->getMockConfigPluginManager(['searches' => $searchConfig]));
        $params->setSort($sort);
        $backendParams = $params->getBackendParameters();
        $this->assertEquals([$expectedSort], $backendParams->get('sort'));
    }

    /**
     * Get Params object
     *
     * @param ?Options       $options    Options object (null to create)
     * @param ?PluginManager $mockConfig Mock config plugin manager (null to create)
     *
     * @return Params
     */
    protected function getParams(
        ?Options $options = null,
        ?PluginManager $mockConfig = null
    ): Params {
        $mockConfig ??= $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig,
            $this->createMock(HierarchicalFacetHelper::class),
            $this->createMock(AuthorityHelper::class),
            $this->createMock(DateConverter::class)
        );
    }
}
