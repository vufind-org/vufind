<?php

/**
 * Flashmessages View Helper Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\View\Helper\EscapeHtml;
use VuFind\View\FlashMessenger\FlashMessenger;
use VuFind\View\Helper\Root\Flashmessages;
use VuFind\View\Helper\Root\Globals;
use VuFind\View\Helper\Root\TransEsc;
use VuFind\View\Helper\Root\Translate;

/**
 * Flashmessages View Helper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FlashmessagesTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\TranslatorTrait;

    /**
     * Data provider for testFlashmessageData.
     *
     * @return \Iterator
     */
    public static function getTestFlashmessageData(): \Iterator
    {
        yield [
            [],
            '',
        ];
        yield [
            [
                'success' => [
                    'Foo',
                ],
            ],
            '<div role="alert" class="success">Foo</div>',
        ];
        yield [
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
        ];
        yield [
            [
                'success' => [
                    [
                        'msg' => 'Good',
                    ],
                ],
            ],
            '<div role="alert" class="success">Good Translation</div>',
        ];
        yield [
            [
                'success' => [
                    [
                        'msg' => 'Good',
                        'translate' => false,
                    ],
                ],
            ],
            '<div role="alert" class="success">Good</div>',
        ];
        yield [
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
        ];
        yield [
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
        ];
        yield [
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
        ];
        yield [
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
        ];
        yield [
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
        ];
        yield [
            [
                'success' => [
                    [
                        'msg' => 'Goof',
                        'default' => 'Good',
                    ],
                ],
            ],
            '<div role="alert" class="success">Good</div>',
        ];
    }

    /**
     * Test Flashmessages.
     *
     * @param array  $messages Messages
     * @param string $expected Expected HTML
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestFlashmessageData')]
    public function testFlashmessages(array $messages, string $expected): void
    {
        $fm = $this->getFlashmessages($messages);

        $this->assertSame($expected, $fm());
    }

    /**
     * Get a Flashmessages helper with the given messages in the queue.
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

        $mockMessenger = $this->createMock(FlashMessenger::class);
        $mockMessenger->method('getErrorMessages')->willReturnCallback(fn (): array => $getMessages('error'));
        $mockMessenger->method('getInfoMessages')->willReturnCallback(fn (): array => $getMessages('info'));
        $mockMessenger->method('getSuccessMessages')->willReturnCallback(fn (): array => $getMessages('success'));
        $mockMessenger->method('getWarningMessages')->willReturnCallback(fn (): array => $getMessages('warning'));

        $mockGlobals = $this->createMock(Globals::class);

        $dependencies = $this->getViewHelpers();

        return new Flashmessages(
            $mockMessenger,
            $mockGlobals,
            $dependencies['translate'],
            $dependencies['escapeHtml'],
            $dependencies['transEsc']
        );
    }

    /**
     * Get view helpers needed by test.
     *
     * @return array
     */
    protected function getViewHelpers(): array
    {
        $translations = [
            'default' => [
                'Good' => 'Good Translation',
                'paragraph' => 'Tag <p>',
                'foo_html' => '<p>Foo</p>',
                'foo_placeholder' => 'foo %%ph%%',
            ],
        ];
        $translator = $this->getMockTranslator($translations);
        $translate = new Translate();
        $translate->setTranslator($translator);
        $escapeHtml = new EscapeHtml();
        $transEsc = new TransEsc($translate, $escapeHtml);
        return compact('translate', 'escapeHtml', 'transEsc');
    }
}
