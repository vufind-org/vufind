<?php

/**
 * EPF Options Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

namespace VuFindTest\Search\EPF;

use VuFind\Search\EPF\Options;
use VuFindTest\Feature\ConfigPluginManagerTrait;

/**
 * EPF Options Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
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
                [
                    'General' => [
                        'default_view' => 'brief',
                    ],
                ],
                'brief',
            ],
            [
                [
                    'General' => [
                        'default_view' => 'full',
                    ],
                ],
                'full',
            ],
        ];
    }

    /**
     * Test that the Options object returns correct data .
     *
     * @param array  $config    Blender configuration
     * @param string $ebscoView Expected epfView
     *
     * @return void
     *
     * @dataProvider optionsProvider
     */
    public function testOptions(array $config, $ebscoView): void
    {
        $configMgr = $this->getMockConfigPluginManager(
            [
                'EPF' => $config,
            ]
        );
        $options = new Options($configMgr);
        $this->assertEquals($ebscoView, $options->getEbscoView());
    }
}
