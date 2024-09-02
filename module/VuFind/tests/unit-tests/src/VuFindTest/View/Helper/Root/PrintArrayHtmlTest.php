<?php

/**
 * PrintArrayHtml Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2023.
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
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\PrintArrayHtml;
use VuFindTest\Unit\AbstractMakeTagTestCase;

use function call_user_func;

/**
 * PrintArrayHtml Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PrintArrayHtmlTest extends AbstractMakeTagTestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Get view helper to test.
     *
     * @return PrintArrayHtml
     */
    protected function getHelper()
    {
        $helper = new PrintArrayHtml();
        $helper->setView($this->getViewWithHelpers());

        return $helper;
    }

    /**
     * Data provider for test
     *
     * @return array
     */
    public static function getPrintArrayHtmlData(): array
    {
        return [
            [ // Set 0
                [],
                '',
            ],
            [ // Set 1
                [
                    'KeyA' => 'ValueA',
                ],
                <<<END
                    <span class="term">KeyA:</span> <span class="detail">ValueA</span><br>

                    END,
            ],
            [ // Set 2
                'Value0',
                <<<END
                    <span class="detail">Value0</span><br>

                    END,
            ],
            [ // Set 3
                [
                    0 => 'Value0',
                ],
                <<<END
                    <span class="detail">Value0</span><br>

                    END,
            ],
            [ // Set 4
                [
                    0 => 'Value0',
                    1 => 'Value1',
                ],
                <<<END
                    <span class="detail">Value0</span><br>
                    <span class="detail">Value1</span><br>

                    END,
            ],
            [ // Set 5
                [
                    0 => "Escaped values <>&'\"",
                ],
                <<<END
                    <span class="detail">Escaped values &lt;&gt;&amp;&#039;&quot;</span><br>

                    END,
            ],
            [ // Set 6
                [
                    'KeyA' => [
                        0 => 'Value0',
                        1 => 'Value1',
                    ],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;<span class="detail">Value1</span><br>

                    END,
            ],
            [ // Set 7
                [
                    0 => [
                        0 => 'Value0',
                        1 => 'Value1',
                    ],
                ],
                <<<END
                    &ndash;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;<span class="detail">Value1</span><br>

                    END,
            ],
            [ // Set 8
                [
                    'KeyA' => [
                        0 => 'Value0',
                        1 => 'Value1',
                    ],
                    'KeyB' => [
                        'KeyX' => 'Value2',
                        'KeyY' => 'Value3',
                    ],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;<span class="detail">Value1</span><br>
                    <span class="term">KeyB:</span><br>
                    &ensp;&ensp;<span class="term">KeyX:</span> <span class="detail">Value2</span><br>
                    &ensp;&ensp;<span class="term">KeyY:</span> <span class="detail">Value3</span><br>

                    END,
            ],
            [ // Set 9
                [
                    0 => [
                        0 => 'Value0',
                        1 => 'Value1',
                    ],
                    1 => [
                        'KeyX' => 'Value2',
                        'KeyY' => 'Value3',
                    ],
                    2 => 'Value4',
                ],
                <<<END
                    &ndash;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;<span class="detail">Value1</span><br>
                    &ndash;&ensp;<span class="term">KeyX:</span> <span class="detail">Value2</span><br>
                    &ensp;&ensp;<span class="term">KeyY:</span> <span class="detail">Value3</span><br>
                    &ndash;&ensp;<span class="detail">Value4</span><br>

                    END,
            ],
            [ // Set 10
                [
                    'KeyA' => [
                        0 => 'Value0',
                        1 => 'Value1',
                    ],
                    'KeyB' => [
                        0 => ['KeyW' => 'Value2', 'KeyX' => 'Value3'],
                        1 => ['KeyY' => 'Value4', 'KeyZ' => 'Value5'],
                    ],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;<span class="detail">Value1</span><br>
                    <span class="term">KeyB:</span><br>
                    &ensp;&ensp;&ndash;&ensp;<span class="term">KeyW:</span> <span class="detail">Value2</span><br>
                    &ensp;&ensp;&ensp;&ensp;<span class="term">KeyX:</span> <span class="detail">Value3</span><br>
                    &ensp;&ensp;&ndash;&ensp;<span class="term">KeyY:</span> <span class="detail">Value4</span><br>
                    &ensp;&ensp;&ensp;&ensp;<span class="term">KeyZ:</span> <span class="detail">Value5</span><br>

                    END,
            ],
            [ // Set 11
                [
                    'KeyA' => [
                        0 => 'Value0',
                        1 => 'Value1',
                    ],
                    '001' => [
                        0 => 'Value2',
                        1 => 'Value3',
                    ],
                    '100' => [
                        0 => 'Value4',
                        1 => 'Value5',
                    ],
                    101 => [
                        'KeyB' => 'Value6',
                        200 => 'Value7',
                    ],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;<span class="detail">Value1</span><br>
                    <span class="term">001:</span><br>
                    &ensp;&ensp;<span class="detail">Value2</span><br>
                    &ensp;&ensp;<span class="detail">Value3</span><br>
                    <span class="term">100:</span><br>
                    &ensp;&ensp;<span class="detail">Value4</span><br>
                    &ensp;&ensp;<span class="detail">Value5</span><br>
                    <span class="term">101:</span><br>
                    &ensp;&ensp;<span class="term">KeyB:</span> <span class="detail">Value6</span><br>
                    &ensp;&ensp;<span class="term">200:</span> <span class="detail">Value7</span><br>

                    END,
            ],
            [ // Set 12
                [
                    '001' => ['Value0'],
                    '002' => [
                        '020' => ['Value1'],
                        '040' => ['Value2'],
                        200 => ['Value3'],
                        '201' => ['Value4'],
                    ],
                    '003' => ['Value5'],
                    '100' => ['Value6'],
                ],
                <<<END
                    <span class="term">001:</span> <span class="detail">Value0</span><br>
                    <span class="term">002:</span><br>
                    &ensp;&ensp;<span class="term">020:</span> <span class="detail">Value1</span><br>
                    &ensp;&ensp;<span class="term">040:</span> <span class="detail">Value2</span><br>
                    &ensp;&ensp;<span class="term">200:</span> <span class="detail">Value3</span><br>
                    &ensp;&ensp;<span class="term">201:</span> <span class="detail">Value4</span><br>
                    <span class="term">003:</span> <span class="detail">Value5</span><br>
                    <span class="term">100:</span> <span class="detail">Value6</span><br>

                    END,
            ],
            [ // Set 13
                [
                    ['001' => ['Value0']],
                    ['002' => ['Value1']],
                    ['049' => ['Value2']],
                    ['100' => ['Value3']],
                ],
                <<<END
                    &ndash;&ensp;<span class="term">001:</span> <span class="detail">Value0</span><br>
                    &ndash;&ensp;<span class="term">002:</span> <span class="detail">Value1</span><br>
                    &ndash;&ensp;<span class="term">049:</span> <span class="detail">Value2</span><br>
                    &ndash;&ensp;<span class="term">100:</span> <span class="detail">Value3</span><br>

                    END,
            ],
            [ // Set 14
                [
                    'KeyA' => [0 => 'Value0'],
                ],
                <<<END
                    <span class="term">KeyA:</span> <span class="detail">Value0</span><br>

                    END,
            ],
            [ // Set 15
                [
                    'KeyA' => ['000' => 'Value0'],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;<span class="term">000:</span> <span class="detail">Value0</span><br>

                    END,
            ],
            [ // Set 16
                [
                    'KeyA' => [0 => [0 => 'Value0']],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;<span class="detail">Value0</span><br>

                    END,
            ],
            [ // Set 17
                [
                    'KeyA' => [0 => [0 => [0 => [0 => 'Value0']]]],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;&ndash;&ensp;&ndash;&ensp;<span class="detail">Value0</span><br>

                    END,
            ],
            [ // Set 18
                [
                    'KeyA' => [
                        0 => [0 => 'Value0'],
                        1 => [0 => 'Value1'],
                        2 => [0 => 'Value2'],
                    ],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;<span class="detail">Value1</span><br>
                    &ensp;&ensp;<span class="detail">Value2</span><br>

                    END,
            ],
            [ // Set 19
                [
                    'KeyA' => [
                        0 => [
                            0 => ['Value0'],
                            1 => ['Value1'],
                        ],
                        1 => [
                            0 => 'Value2',
                            1 => 'Value3',
                        ],
                    ],
                ],
                <<<END
                    <span class="term">KeyA:</span><br>
                    &ensp;&ensp;&ndash;&ensp;<span class="detail">Value0</span><br>
                    &ensp;&ensp;&ensp;&ensp;<span class="detail">Value1</span><br>
                    &ensp;&ensp;&ndash;&ensp;<span class="detail">Value2</span><br>
                    &ensp;&ensp;&ensp;&ensp;<span class="detail">Value3</span><br>

                    END,
            ],
        ];
    }

    /**
     * Test PrintArrayHtml.
     *
     * @param array|string $entry    Array to print
     * @param string       $expected Expected HTML
     *
     * @return void
     *
     * @dataProvider getPrintArrayHtmlData
     */
    public function testPrintArrayHtml($entry, string $expected): void
    {
        $helper = $this->getHelper();

        $this->assertEquals(
            $expected,
            call_user_func([$helper, '__invoke'], $entry)
        );
    }
}
