<?php

/**
 * Flashmessages View Helper Test Class
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

namespace VuFindTest\View\Helper\Root;

use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\View\Helper\EscapeHtml;
use VuFind\View\Helper\Root\Flashmessages;
use VuFind\View\Helper\Root\TransEsc;
use VuFind\View\Helper\Root\Translate;

/**
 * Flashmessages View Helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FlashmessagesTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Data provider for testFlashmessageData
     *
     * @return array
     */
    public static function getTestFlashmessageData(): array
    {
        return [
            [
                [],
                '',
            ],
            [
                [
                    'success' => [
                        'Foo',
                    ],
                ],
                '<div role="alert" class="success">Foo</div>',
            ],
            [
                [
                    'error' => [
                        'Fail',
                    ],
                    'success' => [
                        'Good',
                    ],
                ],
                '<div role="alert" class="error">Fail</div>'
                    . '<div role="alert" class="success">Good Translation</div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'Good',
                        ],
                    ],
                ],
                '<div role="alert" class="success">Good Translation</div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'Good',
                            'translate' => false,
                        ],
                    ],
                ],
                '<div role="alert" class="success">Good</div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'foo_placeholder',
                            'translate' => true,
                            'tokens' => [
                                '%%ph%%' => 'Good',
                            ],
                        ],
                    ],
                ],
                '<div role="alert" class="success">foo Good</div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'foo_placeholder',
                            'translate' => true,
                            'tokens' => [
                                '%%ph%%' => 'paragraph',
                            ],
                            'translateTokens' => true,
                        ],
                    ],
                ],
                '<div role="alert" class="success">foo Tag &lt;p&gt;</div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'foo_placeholder',
                            'translate' => true,
                            'html' => true,
                            'tokens' => [
                                '%%ph%%' => 'paragraph',
                            ],
                            'translateTokens' => true,
                        ],
                    ],
                ],
                '<div role="alert" class="success">foo Tag &lt;p&gt;</div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'foo_placeholder',
                            'translate' => true,
                            'html' => true,
                            'tokens' => [
                                '%%ph%%' => 'paragraph',
                            ],
                            'translateTokens' => true,
                            'tokensHtml' => true,
                        ],
                    ],
                ],
                '<div role="alert" class="success">foo Tag <p></div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'foo_placeholder',
                            'translate' => true,
                            'html' => true,
                            'tokens' => [
                                '%%ph%%' => '<b>bold</b>',
                            ],
                            'translateTokens' => false,
                            'tokensHtml' => true,
                        ],
                    ],
                ],
                '<div role="alert" class="success">foo <b>bold</b></div>',
            ],
            [
                [
                    'success' => [
                        [
                            'msg' => 'Goof',
                            'default' => 'Good',
                        ],
                    ],
                ],
                '<div role="alert" class="success">Good</div>',
            ],
        ];
    }

    /**
     * Test Flashmessages.
     *
     * @param array  $messages Messages
     * @param string $expected Expected HTML
     *
     * @return void
     *
     * @dataProvider getTestFlashmessageData
     */
    public function testFlashmessages(array $messages, string $expected): void
    {
        $fm = $this->getFlashmessages($messages);

        $this->assertEquals($expected, $fm());
    }

    /**
     * Get a Flashmessages helper with the given messages in the queue
     *
     * @param array $messages Messages
     *
     * @return Flashmessages
     */
    protected function getFlashmessages(array $messages): Flashmessages
    {
        $getMessages = function ($ns) use ($messages) {
            return $messages[$ns] ?? [];
        };

        $mockMessenger = $this->getMockBuilder(FlashMessenger::class)
            ->getMock();
        $mockMessenger->expects($this->any())
            ->method('getMessages')
            ->with($this->isType('string'))
            ->will($this->returnCallback($getMessages));
        $mockMessenger->expects($this->any())
            ->method('getCurrentMessages')
            ->with($this->isType('string'))
            ->will($this->returnValue([]));

        $fm = new Flashmessages($mockMessenger);

        $layout = new class () {
            /**
             * Set layout template or retrieve "layout" view model
             *
             * If no arguments are given, grabs the "root" or "layout" view model.
             * Otherwise, attempts to set the template for that view model.
             *
             * @param null|string $template Template
             *
             * @return Model|null|self
             */
            public function __invoke($template = null)
            {
                return $this;
            }
        };

        $helpers = array_merge(
            $this->getViewHelpers(),
            [
                'layout' => $layout,
            ]
        );

        $fm->setView($this->getPhpRenderer($helpers));

        return $fm;
    }

    /**
     * Get view helpers needed by test.
     *
     * @return array
     */
    protected function getViewHelpers()
    {
        $getTranslation = function ($str, $tokens = [], $default = null) {
            $strings = [
                'Good' => 'Good Translation',
                'paragraph' => 'Tag <p>',
                'foo_html' => '<p>Foo</p>',
                'foo_placeholder' => 'foo %%ph%%',
            ];
            $translated = $strings[$str] ?? $default ?? $str;
            return str_replace(
                array_keys($tokens),
                array_values($tokens),
                $translated
            );
        };

        $translate = $this->getMockBuilder(Translate::class)->getMock();
        $translate->expects($this->any())
            ->method('__invoke')
            ->will($this->returnCallback($getTranslation));

        $transEsc = new TransEsc();
        $transEsc->setView(
            $this->getPhpRenderer(
                [
                    'escapeHtml' => new EscapeHtml(),
                    'translate' => $translate,
                ]
            )
        );
        return compact('transEsc', 'translate');
    }
}
