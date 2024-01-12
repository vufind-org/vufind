<?php

/**
 * Class ComponentTest
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\View\Renderer\PhpRenderer;
use VuFind\View\Helper\Root\Component;

/**
 * Component Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ComponentTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get view helper to test.
     *
     * @return Component
     */
    protected function getHelper()
    {
        $helper = new Component();

        $view = $this->getMockBuilder(PhpRenderer::class)->getMock();
        $helper->setView($view);

        $helper->getView()->method('render')->will($this->returnArgument(0));

        return $helper;
    }

    /**
     * Test basic Component conversion
     *
     * @return void
     */
    public function testComponent()
    {
        $helper = $this->getHelper();

        $this->assertEquals('_ui/components/menu', $helper('menu'));
        $this->assertEquals('_ui/components/menu/sub/sub', $helper('menu/sub/sub'));
        $this->assertEquals('_ui/atoms/menu', $helper('@atoms/menu'));
        $this->assertEquals('_ui/atoms/menu/login', $helper('@atoms/menu/login'));
    }
}
