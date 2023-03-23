<?php

/**
 * Component Test Class
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Integration\View\Helper\Root;

use VuFind\View\Helper\Root\Component;

/**
 * Component Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ComponentTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDetectionTrait;
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
    }

    /**
     * Get plugins to register to support view helper being tested
     *
     * @return array
     */
    protected function getPlugins()
    {
        $currentPath = $this->createMock(\VuFind\View\Helper\Root\CurrentPath::class);
        $currentPath->expects($this->any())->method('__invoke')
            ->will($this->returnValue('/test/path'));

        $icon = $this->createMock(\VuFind\View\Helper\Root\Icon::class);
        $icon->expects($this->any())->method('__invoke')
            ->will($this->returnArgument(0));

        $recordLinker = $this->getMockBuilder(\VuFind\View\Helper\Root\RecordLinker::class)
            ->setConstructorArgs(
                [
                    new \VuFind\Record\Router(
                        new \Laminas\Config\Config([])
                    ),
                ]
            )->getMock();
        $recordLinker->expects($this->any())->method('getUrl')
            ->will($this->returnValue('test/url'));

        $transEsc = $this->createMock(\VuFind\View\Helper\Root\TransEsc::class);
        $transEsc->expects($this->any())->method('__invoke')
            ->will($this->returnArgument(0));

        $serverUrl = $this->createMock(\Laminas\View\Helper\ServerUrl::class);
        $serverUrl->expects($this->any())->method('__invoke')
            ->will($this->returnValue('http://server/url'));

        return compact('currentPath', 'icon', 'recordLinker', 'transEsc') + ['serverurl' => $serverUrl];
    }

    /**
     * Build a page that's just this component.
     * Call just like you'd call $view->component(...)
     *
     * @param string $component  Name of component
     * @param mixed  ...$options Options and parameters for the components
     *
     * @return \Behat\Mink\Page
     */
    protected function getComponentPage($component, ...$options)
    {
        // Get component
        $helper = new Component();
        $helper->setView($this->getPhpRenderer($this->getPlugins()));
        $component = $helper($component, ...$options);
        // Make sure we have HTML
        $this->assertIsString($component);

        $script = '<script src="' . $this->getVuFindUrl() . '/themes/bootstrap3/js/components.js"></script>';
        $html = '<!doctype html><head>' . $script . '</head><body>' . $component . '</body></html>';

        // Create a blank page
        $session = $this->getMinkSession();
        // Insert HTML
        $session->getDriver()->visit('data:text/html;charset=utf-8,' . $html);
        // Get page
        return $session->getPage();
    }

    /**
     * Test confirm menu
     *
     * @return void
     */
    public function testConfirmMenu()
    {
        $page = $this->getComponentPage('confirm-menu', ['label' => 'Working?']);
        $menu = $this->findCss($page, '.confirm-menu');
        $toggle = $this->findCss($page, '.confirm__toggle');

        $assertOpen = function ($menu) {
            $className = $menu->getAttribute("class");
            $this->assertTrue(strstr($className, 'is-open') !== false);
        };
        $assertClosed = function ($menu) {
            $className = $menu->getAttribute("class");
            $this->assertTrue(strstr($className, 'is-open') === false);
        };

        //
        // Clicks
        //

        // Open
        $toggle->click();
        $assertOpen($menu);

        // Close
        $toggle->click();
        $assertClosed($menu);

        //
        // Keys (not working)
        //

        $ESC = 27;
        $ARROW_UP = 38;
        $ARROW_DOWN = 40;

        // Open
        $toggle->keyDown($ARROW_DOWN);
        $assertOpen($menu);

        // Muck about
        $menu->keyDown($ARROW_DOWN);
        $assertOpen($menu);
        $menu->keyDown($ARROW_UP);
        $assertOpen($menu);

        // Close
        $menu->keyDown($ESC);
        $assertClosed($menu);
    }
}
