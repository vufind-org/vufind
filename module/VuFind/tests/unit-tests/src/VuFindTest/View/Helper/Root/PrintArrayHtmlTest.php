<?php
/**
 * PrintArrayHtml Test Class
 *
 * PHP version 7
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
use VuFindTest\Unit\AbstractMakeTagTest;

/**
 * PrintArrayHtml Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PrintArrayHtmlTest extends AbstractMakeTagTest
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
    public function getPrintArrayHtmlData(): array
    {
        return [
            [
                [],
                ''
            ],
            [
                [
                    'KeyA' => "ValueA",
                ],
                <<<END
                <span class="term">KeyA:</span> <span class="detail">ValueA</span><br/>

                END
            ],
            [
                "Value0",
                <<<END
                <span class="detail">Value0</span><br/>

                END
            ],
            [
                [
                    0 => "Value0",
                ],
                <<<END
                <span class="detail">Value0</span><br/>

                END
            ],
            [
                [
                    0 => "Value0",
                    1 => "Value1",
                ],
                <<<END
                <span class="detail">Value0</span><br/>
                <span class="detail">Value1</span><br/>

                END
            ],
            [
                [
                    0 => "Escaped vals <>&'\"",
                ],
                <<<END
                <span class="detail">Escaped vals &lt;&gt;&amp;&#039;&quot;</span><br/>

                END
            ],
            [
                [
                    "KeyA" => [
                        0 => "Value0",
                        1 => "Value1",
                    ],
                ],
                <<<END
                <span class="term">KeyA:</span><br/>
                &ensp;&ensp;<span class="detail">Value0</span><br/>
                &ensp;&ensp;<span class="detail">Value1</span><br/>

                END
            ],
            [
                [
                    0 => [
                        0 => "Value0",
                        1 => "Value1",
                    ],
                ],
                <<<END
                &ndash;&ensp;<span class="detail">Value0</span><br/>
                &ensp;&ensp;<span class="detail">Value1</span><br/>

                END
            ],
            [
                [
                    "KeyA" => [
                        0 => "Value0",
                        1 => "Value1",
                    ],
                    "KeyB" => [
                        "KeyX" => "Value2",
                        "KeyY" => "Value3",
                    ],
                ],
                <<<END
                <span class="term">KeyA:</span><br/>
                &ensp;&ensp;<span class="detail">Value0</span><br/>
                &ensp;&ensp;<span class="detail">Value1</span><br/>
                <span class="term">KeyB:</span><br/>
                &ensp;&ensp;<span class="term">KeyX:</span> <span class="detail">Value2</span><br/>
                &ensp;&ensp;<span class="term">KeyY:</span> <span class="detail">Value3</span><br/>

                END
            ],
            [
                [
                    0 => [
                        0 => "Value0",
                        1 => "Value1",
                    ],
                    1 => [
                        "KeyX" => "Value2",
                        "KeyY" => "Value3",
                    ],
                    99 => "Value4",
                ],
                <<<END
                &ndash;&ensp;<span class="detail">Value0</span><br/>
                &ensp;&ensp;<span class="detail">Value1</span><br/>
                &ndash;&ensp;<span class="term">KeyX:</span> <span class="detail">Value2</span><br/>
                &ensp;&ensp;<span class="term">KeyY:</span> <span class="detail">Value3</span><br/>
                &ndash;&ensp;<span class="detail">Value4</span><br/>

                END
            ],
            [
                [
                    "KeyA" => [
                        0 => "Value0",
                        1 => "Value1",
                    ],
                    "KeyB" => [
                        0 => ["KeyW" => "Value2", "KeyX" => "Value3"],
                        1 => ["KeyY" => "Value4", "KeyZ" => "Value5"],
                    ],
                ],
                <<<END
                <span class="term">KeyA:</span><br/>
                &ensp;&ensp;<span class="detail">Value0</span><br/>
                &ensp;&ensp;<span class="detail">Value1</span><br/>
                <span class="term">KeyB:</span><br/>
                &ensp;&ensp;&ndash;&ensp;<span class="term">KeyW:</span> <span class="detail">Value2</span><br/>
                &ensp;&ensp;&ensp;&ensp;<span class="term">KeyX:</span> <span class="detail">Value3</span><br/>
                &ensp;&ensp;&ndash;&ensp;<span class="term">KeyY:</span> <span class="detail">Value4</span><br/>
                &ensp;&ensp;&ensp;&ensp;<span class="term">KeyZ:</span> <span class="detail">Value5</span><br/>

                END
            ],
        ];
    }

    /**
     * Test PrintArrayHtml.
     *
     * @param array|string  $entry    Array to print
     * @param string        $expected Expected HTML
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
