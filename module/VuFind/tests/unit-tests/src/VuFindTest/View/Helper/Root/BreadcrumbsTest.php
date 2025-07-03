<?php

/**
 * Breadcrumbs view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

use Laminas\View\Helper\Layout;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\View\Helper\Root\Breadcrumbs;

/**
 * Breadcrumbs view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BreadcrumbsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Get a breadcrumb helper with the formatBreadcrumb method mocked.
     *
     * @return Breadcrumbs&MockObject
     */
    protected function getHelperWithFormatMocked(): Breadcrumbs&MockObject
    {
        $builder = $this->getMockBuilder(Breadcrumbs::class)
            ->onlyMethods(['formatBreadcrumb'])
            ->getMock();
        $builder->method('formatBreadcrumb')->willReturnCallback(
            function (string $text, ?string $href = null, bool $active = false): string {
                return $text . '|' . ($href ?? '-') . '|' . ($active ? 'T' : 'F') . '>';
            }
        );
        return $builder;
    }

    /**
     * Test building and deconstructing a chain of breadcrumbs.
     *
     * @return void
     */
    public function testChainBuilding(): void
    {
        $layoutModel = new ViewModel();
        $layout = $this->createMock(Layout::class);
        $layout->method('__invoke')->willReturn($layoutModel);
        $view = $this->getPhpRenderer(compact('layout'));
        $helper = $this->getHelperWithFormatMocked();
        $helper->setView($view);
        $helper->disable();
        $this->assertFalse($layoutModel->breadcrumbs);
        $helper->add('a', 'b');
        $helper->add('c', active: true);
        $helper->prepend('d');
        $this->assertEquals('d|-|F>a|b|F>c|-|T>', $layoutModel->breadcrumbs);
        $helper->set('z', 'y', true);
        $this->assertEquals('z|y|T>', $layoutModel->breadcrumbs);
        $helper->reset();
        $this->assertEquals('', $layoutModel->breadcrumbs);
    }
}
