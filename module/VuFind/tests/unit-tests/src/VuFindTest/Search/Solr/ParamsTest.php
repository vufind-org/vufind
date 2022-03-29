<?php
/**
 * Solr Search Object Parameters Test
 *
 * PHP version 7
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
namespace VuFindTest\Search\Solr;

use VuFind\Config\PluginManager;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;

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
    /**
     * Test that filters work as expected.
     *
     * @return void
     */
    public function testFilters(): void
    {
        $params = $this->getParams();
        $params->addFacet('format', 'format_label');
        $params->addFacet('building', 'building_label');

        // No filters:
        $this->assertEquals(null, $params->getBackendParameters()->get('fq'));

        // Add multiple filters:
        $params->addFilter('~format:bar');
        $params->addFilter('~format:baz');
        $params->addFilter('building:main');
        $this->assertEquals(
            [
                'building:"main"',
                '{!tag=format_filter}format:(format:"bar" OR format:"baz")',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Add a hidden filter:
        $params->addHiddenFilter('building:sub');
        $this->assertEquals(
            [
                'building:"sub"',
                'building:"main"',
                '{!tag=format_filter}format:(format:"bar" OR format:"baz")',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Remove format filters:
        $params->removeAllFilters('~format');
        $this->assertEquals(
            [
                'building:"sub"',
                'building:"main"',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Remove building filter:
        $params->removeFilter('building:main');
        $this->assertEquals(
            [
                'building:"sub"',
            ],
            $params->getBackendParameters()->get('fq')
        );
    }

    /**
     * Test that we get a mock search class ID while testing.
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $this->assertEquals('Solr', $this->getParams()->getSearchClassId());
    }

    /**
     * Get Params object
     *
     * @return Params
     */
    protected function getParams(): Params
    {
        $mockConfig = $this->createMock(PluginManager::class);
        return new Params(
            new Options($mockConfig),
            $mockConfig
        );
    }
}
