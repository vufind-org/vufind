<?php
/**
 * DoiLookup test class.
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

use VuFind\AjaxHandler\DoiLookup;
use VuFind\AjaxHandler\DoiLookupFactory;
use VuFind\Config\PluginManager as ConfigManager;
use VuFind\DoiLinker\DoiLinkerInterface;
use VuFind\DoiLinker\PluginManager;
use Zend\Config\Config;

/**
 * DoiLookup test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DoiLookupTest extends \VuFindTest\Unit\AjaxHandlerTest
{
    /**
     * Test a DOI lookup.
     *
     * @return void
     */
    public function testLookup()
    {
        // Set up config manager:
        $config = new Config(['DOI' => ['resolver' => 'foo']]);
        $cm = $this->container->createMock(ConfigManager::class, ['get']);
        $cm->expects($this->once())->method('get')->with($this->equalTo('config'))
            ->will($this->returnValue($config));
        $this->container->set(ConfigManager::class, $cm);

        // Set up plugin manager:
        $pm = new PluginManager($this->container);
        $mockPlugin = $this->container
            ->createMock(DoiLinkerInterface::class, ['getLinks']);
        $mockPlugin->expects($this->once())->method('getLinks')
            ->with($this->equalTo(['bar']))
            ->will($this->returnValue(['bar' => 'baz']));
        $pm->setService('foo', $mockPlugin);
        $this->container->set(PluginManager::class, $pm);

        // Test the handler:
        $factory = new DoiLookupFactory();
        $handler = $factory($this->container, DoiLookup::class);
        $params = $this->getParamsHelper(['doi' => ['bar']]);
        $this->assertEquals([['bar' => 'baz']], $handler->handleRequest($params));
    }
}
