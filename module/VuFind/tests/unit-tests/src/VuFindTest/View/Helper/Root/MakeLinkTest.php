<?php

/**
 * MakeLink view helper Test Class
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

use VuFind\View\Helper\Root\MakeLink;

/**
 * MakeLink view helper Test Class
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
     * Get MakeLink helper with mock view
     *
     * @return \Laminas\View\Helper\EscapeHtml
     */
    protected function getHelper()
    {
        $helper = new MakeLink();
        $helper->setView($this->getViewWithHelpers());
        return $helper;
    }

    /**
     * Test that responds to common inputs
     *
     * @return void
     */
    public function testLink()
    {
        $helper = $this->getHelper();

        $this->assertEquals(
            '<a href="https&#x3A;&#x2F;&#x2F;vufind.org">text</a>',
            $helper('text', 'https://vufind.org')
        );

        $this->assertEquals(
            '<a href="&#x2F;Record&#x2F;id">text</a>',
            $helper('text', false, ['href' => '/Record/id'])
        );

        $this->assertEquals(
            '<a href="&#x23;anchor">text</a>',
            $helper('text', '#anchor', ['href' => 'default'])
        );
    }

    /**
     * Test that helper returns unescaped text when href is falsey
     *
     * @return void
     */
    public function testSpan()
    {
        $helper = $this->getHelper();

        $this->assertEquals('<span>text</span>', $helper('text', null, ''));
        $this->assertEquals('<span>text</span>', $helper('text', '', ''));
        $this->assertEquals('<span>text</span>', $helper('text', false, []));
    }

    /**
     * Test that responds to common inputs
     *
     * @return void
     */
    public function testAttributes()
    {
        $helper = $this->getHelper();

        $this->assertEquals(
            '<a class="btn" id="login" href="&#x23;">text</a>',
            $helper('text', '#', ['class' => 'btn', 'id' => 'login'])
        );

        // Skip href
        $this->assertEquals(
            '<a href="&#x23;" class="btn" id="login">text</a>',
            $helper('text', null, ['href' => '#', 'class' => 'btn', 'id' => 'login'])
        );
        $this->assertEquals(
            '<a href="&#x23;" class="btn" id="login">text</a>',
            $helper('text', false, ['href' => '#', 'class' => 'btn', 'id' => 'login'])
        );

        // String
        $this->assertEquals(
            '<a class="btn" href="&#x23;">text</a>',
            $helper('text', '#', 'btn')
        );

        // No href but attributes
        $this->assertEquals(
            '<span class="btn">text</span>',
            $helper('text', null, 'btn') // class only
        );
        $this->assertEquals(
            '<span class="btn">text</span>',
            $helper('text', null, ['class' => 'btn'])
        );
        $this->assertEquals(
            '<span class="btn">text</span>',
            $helper('text', null, ['class' => 'btn'])
        );
        $this->assertEquals(
            '<span class="btn">text</span>',
            $helper('text', false, ['class' => 'btn'])
        );
    }

    /**
     * Test escaping with other plugins
     *
     * @return void
     */
    public function testOptionEscaping()
    {
        $helper = $this->getHelper();

        // Test standard output of recordLink helper:
        $this->assertEquals(
            '<a href="&#x2F;Record&#x2F;foo">recordLink</a>',
            $helper('recordLink', '/Record/foo')
        );

        // Confirm that attributes and HTML contents are escaped
        $this->assertEquals(
            '<a data-foo="this&amp;that" href="&#x2F;Record&#x2F;foo&#x25;2Fbar&#x3F;checkRoute&#x3D;1">'
                . 'contains &lt;b&gt;bold&lt;/b&gt;</a>',
            $helper(
                'contains <b>bold</b>',
                '/Record/foo%2Fbar?checkRoute=1',
                ['data-foo' => 'this&that']
            )
        );

        // Confirm that HTML is NOT escaped when asked politely
        $this->assertEquals(
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
     * Test that helper obeys options
     *
     * @return void
     */
    public function testOptionProxy()
    {
        $helper = $this->getHelper();

        $this->assertEquals(
            '<a href="&#x2F;Record&#x2F;foo">recordLink</a>',
            $helper('recordLink', '/Record/foo', null, null)
        );

        $this->assertEquals(
            '<a href="https&#x3A;&#x2F;&#x2F;super.safe&#x3F;url&#x3D;&#x2F;Record&#x2F;foo">recordLink</a>',
            $helper('recordLink', '/Record/foo', null, ['proxyUrl' => 'https://super.safe?url='])
        );
    }
}
