<?php
/**
 * ConsoleRouter Test Class
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
namespace VuFindTest\Mvc\Router;
use VuFindConsole\Mvc\Router\ConsoleRouter;

/**
 * InjectTemplateListener Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ConsoleRouterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test routing.
     *
     * @return void
     */
    public function testRoute()
    {
        $router = ConsoleRouter::factory();
        $router->setCliDir(__DIR__);
        $request = $this->getMock('Zend\Console\Request', ['getScriptName']);
        $request->expects($this->any())->method('getScriptName')
            ->will($this->returnValue('ConsoleRouterTest.php'));
        $result = $router->match($request);
        $this->assertEquals($result->getParam('controller'), 'Router');
        $this->assertEquals($result->getParam('action'), 'ConsoleRouterTest');
    }
}