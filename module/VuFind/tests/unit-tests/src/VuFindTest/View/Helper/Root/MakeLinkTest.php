<?php
/**
 * makeLink view helper Test Class
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

use VuFind\View\Helper\Root\MakeLink;

/**
 * makeLink view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MakeLinkTest extends \PHPUnit\Framework\TestCase
{
    protected function getHelper()
    {
        $escapeHtml = new \Laminas\View\Helper\EscapeHtml();
        $view = $this->createMock(\Laminas\View\Renderer\PhpRenderer::class);
        $view->method('plugin')->will($this->returnValue($escapeHtml));

        $helper = new MakeLink();
        $helper->setView($view);
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
            '<a href="https://vufind.org">text</a>',
            $helper('text', 'https://vufind.org')
        );

        $this->assertEquals(
            '<a href="/Record/id">text</a>',
            $helper('text', '/Record/id')
        );

        $this->assertEquals(
            '<a href="#anchor">text</a>',
            $helper('text', '#anchor')
        );

        $this->assertEquals(
            '<a href="#">text</a>',
            $helper('text', '#')
        );
    }

    /**
     * Test that helper returns unescaped text when href is falsey
     *
     * @return void
     */
    public function testNoLink()
    {
        $helper = $this->getHelper();

        $this->assertEquals('text', $helper('text', ''));
        // Escapes
        $this->assertEquals('text&', $helper('text&', null));
        $this->assertEquals('text<', $helper('text<', false));
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
            '<a class="btn" id="login" href="#">text</a>',
            $helper('text', '#', ['class' => 'btn', 'id' => 'login'])
        );

        // Skip href
        $this->assertEquals(
            '<a href="#" class="btn" id="login">text</a>',
            $helper('text', ['href' => '#', 'class' => 'btn', 'id' => 'login'])
        );

        // String
        $this->assertEquals(
            '<a class="btn" href="#">text</a>',
            $helper('text', '#', 'btn')
        );

        // No href but attributes
        $this->assertEquals(
            '<span class="btn">text</span>',
            $helper('text', null, 'btn')
        );
        $this->assertEquals(
            '<span class="btn">text</span>',
            $helper('text', ['class' => 'btn'])
        );
        $this->assertEquals(
            '<span class="btn">text</span>',
            $helper('text', null, ['class' => 'btn'])
        );
    }
}
