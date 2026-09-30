<?php

/**
 * Breadcrumbs view helper Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\View\GlobalsContainer;
use VuFind\View\Helper\Root\Breadcrumbs;

/**
 * Breadcrumbs view helper Test Class.
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
     * @param \Laminas\View\Renderer\RendererInterface $view             View renderer
     * @param GlobalsContainer                         $globalsContainer Global data container
     *
     * @return Breadcrumbs&MockObject
     */
    protected function getHelperWithFormatMocked(
        RendererInterface $view,
        GlobalsContainer $globalsContainer
    ): Breadcrumbs&MockObject {
        $builder = $this->getMockBuilder(Breadcrumbs::class)
            ->setConstructorArgs([$view, $globalsContainer])
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
        $globalsContainer = new GlobalsContainer();
        $view = $this->getPhpRenderer();
        $helper = $this->getHelperWithFormatMocked($view, $globalsContainer);
        $helper->disable();
        $this->assertFalse($globalsContainer['breadcrumbs']);
        $helper->add('a', 'b');
        $helper->add('c', active: true);
        $helper->prepend('d');
        $this->assertSame('d|-|F>a|b|F>c|-|T>', $globalsContainer['breadcrumbs']);
        $helper->set('z', 'y', true);
        $this->assertSame('z|y|T>', $globalsContainer['breadcrumbs']);
        $helper->reset();
        $this->assertSame('', $globalsContainer['breadcrumbs']);
    }
}
