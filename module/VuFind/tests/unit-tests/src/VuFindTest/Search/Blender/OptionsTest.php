<?php

/**
 * Blender Options Test
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

use VuFind\Search\Blender\Options;
use VuFindTest\Feature\ConfigPluginManagerTrait;

/**
 * Blender Options Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    use ConfigPluginManagerTrait;

    /**
     * Data provider for testOptions
     *
     * @return array
     */
    public static function optionsProvider(): array
    {
        return [
            [
                [],
                false,
            ],
            [
                [
                    'Advanced_Searches' => [
                        'foo' => 'bar',
                    ],
                ],
                'blender-advanced',
            ],
        ];
    }

    /**
     * Test that the Options object returns correct data .
     *
     * @param array        $config    Blender configuration
     * @param string|false $advAction Expected advanced search action
     *
     * @return void
     *
     * @dataProvider optionsProvider
     */
    public function testOptions(array $config, $advAction): void
    {
        $configMgr = $this->getMockConfigPluginManager(
            [
                'Blender' => $config,
            ]
        );
        $options = new Options($configMgr);
        $this->assertEquals('blender-results', $options->getSearchAction());
        $this->assertEquals($advAction, $options->getAdvancedSearchAction());
        $this->assertFalse($options->getFacetListAction());
    }
}
