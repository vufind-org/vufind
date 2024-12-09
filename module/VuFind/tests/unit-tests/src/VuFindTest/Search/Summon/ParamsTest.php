<?php

/**
 * Summon Search Object Parameters Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\Summon;

use VuFind\Config\PluginManager;
use VuFind\Search\Summon\Options;
use VuFind\Search\Summon\Params;

/**
 * Summon Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test that checkbox filters are always visible (or not) as appropriate.
     *
     * @return void
     */
    public function testCheckboxVisibility()
    {
        $config = [
            'Summon' => [
                'CheckboxFacets' => [
                    'IsScholarly:true' => 'scholarly_limit',
                    'holdingsOnly:false' => 'add_other_libraries',
                    'queryExpansion:true' => 'include_synonyms',
                ],
            ],
        ];
        $configManager = $this->getMockConfigPluginManager($config);
        $params = $this->getParams(null, $configManager);
        // We expect "normal" filters to NOT be always visible, and inverted
        // filters to be always visible.
        $this->assertEquals(
            [
                [
                    'desc' => 'scholarly_limit',
                    'filter' => 'IsScholarly:true',
                    'selected' => false,
                    'alwaysVisible' => false,
                    'dynamic' => false,
                ],
                [
                    'desc' => 'add_other_libraries',
                    'filter' => 'holdingsOnly:false',
                    'selected' => false,
                    'alwaysVisible' => true,
                    'dynamic' => false,
                ],
                [
                    'desc' => 'include_synonyms',
                    'filter' => 'queryExpansion:true',
                    'selected' => false,
                    'alwaysVisible' => true,
                    'dynamic' => false,
                ],
            ],
            $params->getCheckboxFacets()
        );
    }

    /**
     * Get Params object
     *
     * @param Options       $options    Options object (null to create)
     * @param PluginManager $mockConfig Mock config plugin manager (null to create)
     *
     * @return Params
     */
    protected function getParams(
        Options $options = null,
        PluginManager $mockConfig = null
    ): Params {
        $mockConfig ??= $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig
        );
    }
}
