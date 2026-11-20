<?php

/**
 * MakeLink view helper Test Class.
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

use VuFind\View\Helper\Root\MakeLink;

/**
 * MakeLink view helper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MakeLinkTest extends \VuFindTest\Unit\AbstractMakeTagTestCase
{
    /**
     * Get MakeLink helper with mock view.
     *
     * @return MakeLink
     */
    protected function getHelper(): MakeLink
    {
        return new MakeLink($this->getMakeTagHelper());
    }

    /**
     * Test that responds to common inputs.
     *
     * @return void
     */
    public function testLink(): void
    {
        $helper = $this->getHelper();

        $this->assertSame(
            '<a href="https&#x3A;&#x2F;&#x2F;vufind.org">text</a>',
            $helper('text', 'https://vufind.org')
        );

        $this->assertSame(
            '<a href="&#x2F;Record&#x2F;id">text</a>',
            $helper('text', false, ['href' => '/Record/id'])
        );

        $this->assertSame(
            '<a href="&#x23;anchor">text</a>',
            $helper('text', '#anchor', ['href' => 'default'])
        );
    }

    /**
     * Test that helper returns unescaped text when href is falsey.
     *
     * @return void
     */
    public function testSpan(): void
    {
        $helper = $this->getHelper();

        $this->assertSame('<span>text</span>', $helper('text', null, ''));
        $this->assertSame('<span>text</span>', $helper('text', '', ''));
        $this->assertSame('<span>text</span>', $helper('text', false, []));
    }

    /**
     * Test that responds to common inputs.
     *
     * @return void
     */
    public function testAttributes(): void
    {
        $helper = $this->getHelper();

        $this->assertSame(
            '<a class="btn" id="login" href="&#x23;">text</a>',
            $helper('text', '#', ['class' => 'btn', 'id' => 'login'])
        );

        // Skip href
        $this->assertSame(
            '<a href="&#x23;" class="btn" id="login">text</a>',
            $helper('text', null, ['href' => '#', 'class' => 'btn', 'id' => 'login'])
        );
        $this->assertSame(
            '<a href="&#x23;" class="btn" id="login">text</a>',
            $helper('text', false, ['href' => '#', 'class' => 'btn', 'id' => 'login'])
        );

        // String
        $this->assertSame(
            '<a class="btn" href="&#x23;">text</a>',
            $helper('text', '#', 'btn')
        );

        // No href but attributes
        $this->assertSame(
            '<span class="btn">text</span>',
            $helper('text', null, 'btn') // class only
        );
        $this->assertSame(
            '<span class="btn">text</span>',
            $helper('text', null, ['class' => 'btn'])
        );
        $this->assertSame(
            '<span class="btn">text</span>',
            $helper('text', null, ['class' => 'btn'])
        );
        $this->assertSame(
            '<span class="btn">text</span>',
            $helper('text', false, ['class' => 'btn'])
        );
    }

    /**
     * Test escaping with other plugins.
     *
     * @return void
     */
    public function testOptionEscaping(): void
    {
        $helper = $this->getHelper();

        // Test standard output of recordLink helper:
        $this->assertSame(
            '<a href="&#x2F;Record&#x2F;foo">recordLink</a>',
            $helper('recordLink', '/Record/foo')
        );

        // Confirm that attributes and HTML contents are escaped
        $this->assertSame(
            '<a data-foo="this&amp;that" href="&#x2F;Record&#x2F;foo&#x25;2Fbar&#x3F;checkRoute&#x3D;1">'
                . 'contains &lt;b&gt;bold&lt;/b&gt;</a>',
            $helper(
                'contains <b>bold</b>',
                '/Record/foo%2Fbar?checkRoute=1',
                ['data-foo' => 'this&that']
            )
        );

        // Confirm that HTML is NOT escaped when asked politely
        $this->assertSame(
            '<a data-foo="this&amp;that" href="&#x2F;Record&#x2F;foo&#x25;2Fbar&#x3F;checkRoute&#x3D;1">'
                . 'contains <b>bold</b></a>',
            $helper(
                'contains <b>bold</b>',
                '/Record/foo%2Fbar?checkRoute=1',
                ['data-foo' => 'this&that'],
                ['escapeContent' => false]
            )
        );
    }

    /**
     * Test that helper obeys options.
     *
     * @return void
     */
    public function testOptionProxy(): void
    {
        $helper = $this->getHelper();

        $this->assertSame(
            '<a href="&#x2F;Record&#x2F;foo">recordLink</a>',
            $helper('recordLink', '/Record/foo', null, null)
        );

        $this->assertSame(
            '<a href="https&#x3A;&#x2F;&#x2F;super.safe&#x3F;url&#x3D;&#x2F;Record&#x2F;foo">recordLink</a>',
            $helper('recordLink', '/Record/foo', null, ['proxyUrl' => 'https://super.safe?url='])
        );
    }
}
