<?php
/**
 * makeTag view helper Test Class
 *
 * PHP version 7
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\MakeTag;

/**
 * makeTag view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MakeTagTest extends \VuFindTest\Unit\AbstractMakeTagTest
{
    /**
     * Get makeTag helper with mock view
     *
     * return \Laminas\View\Helper\EscapeHtml
     */
    protected function getHelper()
    {
        $helper = new MakeTag();
        $helper->setView($this->getViewWithHelpers());
        return $helper;
    }

    /**
     * Test that responds to common inputs
     */
    public function testAttributes()
    {
        $helper = $this->getHelper();

        $this->assertEquals(
            '<button class="btn" id="login">text</button>',
            $helper('button', 'text', ['class' => 'btn', 'id' => 'login'])
        );

        // String
        $this->assertEquals(
            '<i class="btn">text</i>',
            $helper('i', 'text', 'btn')
        );

        // Empty text
        $this->assertEquals(
            '<i class="fa&#x20;fa-awesome"></i>',
            $helper('i', '', 'fa fa-awesome')
        );

        // Truthy attribute
        $this->assertEquals(
            '<a href="&#x2F;login" data-lightbox="1">Login</a>',
            $helper('a', 'Login', ['href' => '/login', 'data-lightbox' => true])
        );
    }

    /**
     * Test escapeContent
     */
    public function testOptionEscape()
    {
        $helper = $this->getHelper();

        // escapes innerHTML
        $this->assertEquals(
            '<button>This link is &lt;strong&gt;important&lt;/strong&gt;</button>',
            $helper(
                'button',
                'This link is <strong>important</strong>',
            )
        );

        // does not escape innerHTML with option
        $this->assertEquals(
            '<button>This link is <strong>important</strong></button>',
            $helper(
                'button',
                'This link is <strong>important</strong>',
                [],
                ['escapeContent' => false]
            )
        );

        // escape innerHTML with option
        $this->assertEquals(
            '<button>This link is &lt;strong&gt;important&lt;/strong&gt;</button>',
            $helper(
                'button',
                'This link is <strong>important</strong>',
                [],
                ['escapeContent' => true]
            )
        );
    }

    /**
     * Test escapeContent
     */
    public function testVoidElements()
    {
        $helper = $this->getHelper();

        // self closing tag
        $this->assertEquals(
            '<img src="book.gif" />',
            $helper(
                'img',
                '',
                ['src' => 'book.gif']
            )
        );

        // Class only
        $this->assertEquals(
            '<br class="sm&#x3A;hidden" />',
            $helper(
                'br',
                '',
                'sm:hidden'
            )
        );

        // Non void tag
        $this->assertEquals(
            '<span></span>',
            $helper('span', '')
        );
    }

    /**
     * Test tag name edge cases
     */
    public function testValidTagNames()
    {
        $helper = $this->getHelper();

        $helper('CAPITAL', '');
        $helper('mIxEdCaSe', '');
        $helper('my-custom', '');
        $helper('my-long-custom', '');
        $helper('is---this---ok', '');

        // test passes if no errors are thrown
    }

    /*
     * Bad tag names for test below
     */
    public function invalidTags()
    {
        return [
            ['n0numbers'],
            ['-must-start-with-letter'],
            ['em—dash'],
            ['<doubleangles>'],
            ['?php'],
        ];
    }

    /**
     * Test exception on bad tag names
     *
     * @dataProvider invalidTags
     */
    public function testInvalidTagNames($tagName)
    {
        $helper = $this->getHelper();

        // Fulfill plugin quota
        $helper('sanitycheck', 'this is good');

        // Test for exception
        $this->expectException(\InvalidArgumentException::class);
        $helper($tagName, '');
    }
}
