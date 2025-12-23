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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * @return \Iterator
     */
    public static function htmlAttributesTests(): \Iterator
    {
        yield 'Basic' => [
            '<button class="btn" id="login">text</button>',
            ['button', 'text', ['class' => 'btn', 'id' => 'login']],
        ];
        yield 'String' => [
            '<i class="btn">text</i>',
            ['i', 'text', 'btn'],
        ];
        yield 'Empty text' => [
            '<i class="fa&#x20;fa-awesome"></i>',
            ['i', '', 'fa fa-awesome'],
        ];
        yield 'Truthy attribute' => [
            '<a href="&#x2F;login" data-lightbox="1">Login</a>',
            ['a', 'Login', ['href' => '/login', 'data-lightbox' => true]],
        ];
    }

    /**
     * Void elements for test below
     *
     * @return \Iterator
     */
    public static function helperOptionTests(): \Iterator
    {
        yield 'escapes innerHTML' => [
            '<button>This link is &lt;strong&gt;important&lt;/strong&gt;</button>',
            [
                'button',
                'This link is <strong>important</strong>',
            ],
        ];
        yield 'does not escape innerHTML with option' => [
            '<button>This link is <strong>important</strong></button>',
            [
                'button',
                'This link is <strong>important</strong>',
                [],
                ['escapeContent' => false],
            ],
        ];
        yield 'escape innerHTML with option' => [
            '<button>This link is &lt;strong&gt;important&lt;/strong&gt;</button>',
            [
                'button',
                'This link is <strong>important</strong>',
                [],
                ['escapeContent' => true],
            ],
        ];
    }

    /**
     * Void elements for test below
     *
     * @return \Iterator
     */
    public static function voidTags(): \Iterator
    {
        yield 'self closing tag' => [
            '<img src="book.gif">',
            [
                'img',
                '',
                ['src' => 'book.gif'],
            ],
        ];
        yield 'class only' => [
            '<br class="sm&#x3A;hidden">',
            [
                'br',
                '',
                'sm:hidden',
            ],
        ];
        yield 'non-void tag' => [
            '<span></span>',
            [
                'span',
                '',
            ],
        ];
    }

    /**
     * Test all data providers above
     *
     * @param string $expected Expected value
     * @param array  $params   Parameters to test
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('htmlAttributesTests')]
    #[\PHPUnit\Framework\Attributes\DataProvider('helperOptionTests')]
    #[\PHPUnit\Framework\Attributes\DataProvider('voidTags')]
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
     * @return \Iterator
     */
    public static function validTags(): \Iterator
    {
        yield ['SPAN'];
        // CAPITAL
        yield ['sPaN'];
        // mIxEdCaSe
        yield ['my-custom'];
        yield ['my-long-custom'];
        yield ['is---this---ok'];
        yield ['with-4-number'];
        yield ['unicode-·-test-〃'];
    }

    /**
     * Test tag name edge cases
     *
     * @param string $tagName Tag name to use in test
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validTags')]
    public function testValidTagNames($tagName): void
    {
        $helper = $this->getHelper();

        $this->assertSame(
            $helper($tagName, ''),
            '<' . $tagName . '></' . $tagName . '>'
        );

        // test passes if no errors are thrown
    }

    /**
     * Bad tag names for test below
     *
     * @return \Iterator
     */
    public static function invalidTags(): \Iterator
    {
        yield ['nohyphencustom'];
        yield ['n0numbers'];
        yield ['0-numbers-at-the-start'];
        yield ['-must-start-with-letter'];
        yield ['em—dash'];
        yield ['<double-angles>'];
        yield ['?php'];
    }

    /**
     * Test exception on bad tag names
     *
     * @param string $tagName Tag name to use in test
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTags')]
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
        $errorCallback = function (int $code, string $msg): void {
            throw new \Exception($msg, $code);
        };
        set_error_handler($errorCallback, E_USER_WARNING);
        try {
            $helper('marquee', 'Now Playing: A Simpler Time!');
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            restore_error_handler();
        }
    }
}
