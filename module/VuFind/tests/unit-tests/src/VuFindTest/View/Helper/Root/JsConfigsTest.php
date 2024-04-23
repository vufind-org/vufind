<?php

/**
 * JsConfigs view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\JsConfigs;
use VuFindTest\Feature\ConfigPluginManagerTrait;
use VuFindTest\Feature\ViewTrait;

/**
 * JsConfigs view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class JsConfigsTest extends \PHPUnit\Framework\TestCase
{
    use ViewTrait;
    use ConfigPluginManagerTrait;

    /**
     * Test JS configs.
     *
     * @return void
     */
    public function testHelper()
    {
        $configLoader = $this->getMockConfigPluginManager(
            [
                'config1' => [
                    'Section1' => [
                        'key1' => 'val1',
                        'key2' => 'val2',
                        'key3' => 'val3',
                    ],
                    'Section2' => [
                        'key4' => 'val4',
                        'key5' => 'val5',
                        'key6' => 'val6',
                    ],
                ],
                'config2' => [
                    'Section3' => [
                        'key7' => 'val7',
                        'key8' => 'val8',
                        'key9' => 'val9',
                    ],
                    'Section4' => [
                        'key10' => ['val10', 'val11'],
                    ],
                    'Section5' => [
                        'key11' => [
                            'key12' => 'val12',
                        ],
                    ],
                ],
                'config3' => [
                    'Section6' => [
                        'key13' => 'val13',
                        'key14' => 'val14',
                    ],
                ],
            ],
            [],
            $this->any()
        );

        $helper = new JsConfigs($configLoader);

        $helper->addConfigPaths([
            'config1' => [
                'Section1' => 'key2',
            ],
            'config2' => [
                'Section3' => ['missing'],
                'Section4' => ['key10'],
            ],
            'config3' => [
                'Section6' => 'key13',
            ],
            'missing' => ['Missing' => 'missing'],
        ]);
        $helper->addConfigPaths([
            'config1' => [
                'Section1' => 'key3',
                'Section2' => ['key4', 'key6'],
            ],
            'config2' => [
                'Section5' => ['key11' => ['key12']],
                'Missing' => 'missing',
            ],
        ]);
        $json = $helper->getJSON();
        $expected = '{"config1":{"Section1":{"key2":"val2","key3":"val3"},"Section2":{"key4":"val4","key6":"val6"}},'
        . '"config2":{"Section4":{"key10":["val10","val11"]},"Section5":{"key11":{"key12":"val12"}}},'
            . '"config3":{"Section6":{"key13":"val13"}}}';
        $this->assertEquals($expected, $json);
    }
}
