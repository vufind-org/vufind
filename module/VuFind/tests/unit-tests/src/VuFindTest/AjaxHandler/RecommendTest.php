<?php
/**
 * Recommend test class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\Recommend;

/**
 * Recommend test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecommendTest extends \VuFindTest\Unit\AjaxHandlerTest
{
    /**
     * Test the AJAX handler's basic response.
     *
     * @return void
     */
    public function testResponse()
    {
        $ss = $this->getMockService('VuFind\Session\Settings', ['disableWrite']);
        $ss->expects($this->once())->method('disableWrite');
        $mockPlugin = $this->getMockService('VuFind\Recommend\RecommendInterface');
        $rm = $this->getMockService('VuFind\Recommend\PluginManager', ['get']);
        $rm->expects($this->once())->method('get')->with($this->equalTo('foo'))
            ->will($this->returnValue($mockPlugin));
        $r = $this->getMockService('VuFind\Search\Solr\Results');
        $viewHelper = $this->getMockService('VuFind\View\Helper\Root\Recommend');
        $view = $this
            ->getMockService('Zend\View\Renderer\PhpRenderer', ['plugin']);
        $view->expects($this->once())->method('plugin')
            ->with($this->equalTo('recommend'))
            ->will($this->returnValue($viewHelper));
        $handler = new Recommend($ss, $rm, $r, $view);
        $params = $this->getParamsHelper(['mod' => 'foo']);
        $this->assertEquals([null], $handler->handleRequest($params));
    }
}
