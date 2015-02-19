<?php
/**
 * MobileMenu view helper Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\jQueryMobile;
use VuFind\View\Helper\jQueryMobile\MobileMenu;

/**
 * MobileMenu view helper Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class MobileMenuTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test header()
     *
     * @return void
     */
    public function testHeader()
    {
        $extras = ['bar' => 'baz'];
        $mm = $this->getMobileMenu('header.phtml', $extras);
        $this->assertEquals('foo', $mm->header($extras));
    }

    /**
     * Test footer()
     *
     * @return void
     */
    public function testFooter()
    {
        $extras = ['bar' => 'baz'];
        $mm = $this->getMobileMenu('footer.phtml', $extras);
        $this->assertEquals('foo', $mm->footer($extras));
    }

    /**
     * Get mocked out MobileMenu helper
     *
     * @param string $template Template name expected
     * @param array  $extras   Extra parameters expected
     *
     * @return MobileMenu
     */
    protected function getMobileMenu($template, $extras)
    {
        $context = $this->getMock('VuFind\View\Helper\Root\Context');
        $view = $this->getMock('Zend\View\Renderer\PhpRenderer');
        $view->expects($this->once())->method('plugin')->with($this->equalTo('context'))->will($this->returnValue($context));
        $context->expects($this->once())->method('__invoke')->with($this->equalTo($view))->will($this->returnValue($context));
        $context->expects($this->once())->method('renderInContext')->with($this->equalTo($template), $this->equalTo($extras))->will($this->returnValue('foo'));
        $mm = new MobileMenu();
        $mm->setView($view);
        return $mm;
    }
}