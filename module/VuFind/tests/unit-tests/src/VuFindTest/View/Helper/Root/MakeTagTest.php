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
class MakeTagTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get makeTag helper with mock view
     *
     * return \Laminas\View\Helper\EscapeHtml
     */
    protected function getHelper()
    {
        $escapeHtml = new \Laminas\View\Helper\EscapeHtml();
        $view = $this->createMock(\Laminas\View\Renderer\PhpRenderer::class);
        $view->method('plugin')->will($this->returnValue($escapeHtml));

        $helper = new MakeTag();
        $helper->setView($view);
        return $helper;
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
            '<i class="fa fa-awesome"></i>',
            $helper('i', '', 'fa fa-awesome')
        );
    }
}
