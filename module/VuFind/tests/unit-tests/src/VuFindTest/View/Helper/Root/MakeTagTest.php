<?php

/**
 * MakeTag view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\MakeTag;

use function call_user_func_array;

/**
 * MakeTag view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MakeTagTest extends \VuFindTest\Unit\AbstractMakeTagTestCase
{
    /**
     * Get makeTag helper with mock view
     *
     * @return MakeTag
     */
    protected function getHelper(): MakeTag
    {
        $helper = new MakeTag();
        $helper->setView($this->getViewWithHelpers());
        return $helper;
    }

    /**
     * Test that responds to common inputs
     *
     * @return array
     */
    public static function htmlAttributesTests(): array
    {
        return [
            'Basic' => [
                '<button class="btn" id="login">text</button>',
                ['button', 'text', ['class' => 'btn', 'id' => 'login']],
            ],

            'String' => [
                '<i class="btn">text</i>',
                ['i', 'text', 'btn'],
            ],

            'Empty text' => [
                '<i class="fa&#x20;fa-awesome"></i>',
                ['i', '', 'fa fa-awesome'],
            ],

            'Truthy attribute' => [
                '<a href="&#x2F;login" data-lightbox="1">Login</a>',
                ['a', 'Login', ['href' => '/login', 'data-lightbox' => true]],
            ],
        ];
    }

    /**
     * Void elements for test below
     *
     * @return array
     */
    public static function helperOptionTests(): array
    {
        return [
            'escapes innerHTML' => [
                '<button>This link is &lt;strong&gt;important&lt;/strong&gt;</button>',
                [
                    'button',
                    'This link is <strong>important</strong>',
                ],
            ],

            'does not escape innerHTML with option' => [
                '<button>This link is <strong>important</strong></button>',
                [
                    'button',
                    'This link is <strong>important</strong>',
                    [],
                    ['escapeContent' => false],
                ],
            ],

            'escape innerHTML with option' => [
                '<button>This link is &lt;strong&gt;important&lt;/strong&gt;</button>',
                [
                    'button',
                    'This link is <strong>important</strong>',
                    [],
                    ['escapeContent' => true],
                ],
            ],
        ];
    }

    /**
     * Void elements for test below
     *
     * @return array
     */
    public static function voidTags(): array
    {
        return [
            'self closing tag' => [
                '<img src="book.gif">',
                [
                    'img',
                    '',
                    ['src' => 'book.gif'],
                ],
            ],

            'class only' => [
                '<br class="sm&#x3A;hidden">',
                [
                    'br',
                    '',
                    'sm:hidden',
                ],
            ],

            'non-void tag' => [
                '<span></span>',
                [
                    'span',
                    '',
                ],
            ],
        ];
    }

    /**
     * Test all data providers above
     *
     * @param string $expected Expected value
     * @param array  $params   Parameters to test
     *
     * @dataProvider htmlAttributesTests
     * @dataProvider helperOptionTests
     * @dataProvider voidTags
     *
     * @return void
     */
    public function testElements($expected, $params): void
    {
        $helper = $this->getHelper();

        $this->assertEquals(
            $expected,
            call_user_func_array([$helper, '__invoke'], $params)
        );
    }

    /**
     * Good tag names for test below
     *
     * @return array
     */
    public static function validTags(): array
    {
        return [
            ['SPAN'], // CAPITAL
            ['sPaN'], // mIxEdCaSe
            ['my-custom'],
            ['my-long-custom'],
            ['is---this---ok'],
            ['with-4-number'],
            ['unicode-·-test-〃'],
        ];
    }

    /**
     * Test tag name edge cases
     *
     * @param string $tagName Tag name to use in test
     *
     * @dataProvider validTags
     *
     * @return void
     */
    public function testValidTagNames($tagName): void
    {
        $helper = $this->getHelper();

        $this->assertEquals(
            $helper($tagName, ''),
            '<' . $tagName . '></' . $tagName . '>'
        );

        // test passes if no errors are thrown
    }

    /**
     * Bad tag names for test below
     *
     * @return array
     */
    public static function invalidTags(): array
    {
        return [
            ['nohyphencustom'],
            ['n0numbers'],
            ['0-numbers-at-the-start'],
            ['-must-start-with-letter'],
            ['em—dash'],
            ['<double-angles>'],
            ['?php'],
        ];
    }

    /**
     * Test exception on bad tag names
     *
     * @param string $tagName Tag name to use in test
     *
     * @dataProvider invalidTags
     *
     * @return void
     */
    public function testInvalidTagNames($tagName): void
    {
        $helper = $this->getHelper();

        // Fulfill plugin quota
        $helper('sanity-check', 'this is good');

        // Test for exception
        $this->expectException(\InvalidArgumentException::class);
        $helper($tagName, '');
    }

    /**
     * Test deprecated elements
     *
     * @return void
     */
    public function testDeprecatedElementTriggersWarning(): void
    {
        $helper = $this->getHelper();

        // Fulfill plugin quota
        $helper('sanity-check', 'this is good');

        $this->expectExceptionMessage("'<marquee>' is deprecated and should be replaced.");
        $errorCallback = function (int $code, string $msg) {
            throw new \Exception($msg, $code);
        };
        set_error_handler($errorCallback, E_USER_WARNING);
        $helper('marquee', 'Now Playing: A Simpler Time!');
        restore_error_handler();
    }
}
