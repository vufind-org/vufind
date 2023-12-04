<?php

/**
 * InjectTemplateListenerFactory Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest;

use Laminas\ModuleManager\ModuleManager;
use PHPUnit\Framework\TestCase;
use VuFindTheme\InjectTemplateListener;
use VuFindTheme\InjectTemplateListenerFactory;

/**
 * InjectTemplateListenerFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ThemeInjectTemplateListenerFactoryTest extends TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test that the factory correctly processes prefix configuration.
     *
     * @return void
     */
    public function testFactoryPrefixProcessing()
    {
        $factory = new InjectTemplateListenerFactory();
        $container = new \VuFindTest\Container\MockContainer($this);
        $testConfig = [
            'vufind' => [
                'extra_theme_prefixes' => ['Extra/'],
                'excluded_theme_prefixes' => ['Laminas'],
            ],
        ];
        $container->set('config', $testConfig);
        $modules = ['Laminas\Foo', 'LaminasBar', 'VuFind\Foo', 'VuFind'];
        $mockModuleManager = $this->getMockBuilder(ModuleManager::class)
            ->disableOriginalConstructor()->getMock();
        $mockModuleManager->expects($this->once())->method('getModules')
            ->will($this->returnValue($modules));
        $container->set('ModuleManager', $mockModuleManager);
        $listener = $factory($container, InjectTemplateListener::class);
        $this->assertEquals(
            ['Extra/', 'VuFind/Foo/', 'VuFind/'],
            array_values($listener->getPrefixes())
        );
    }
}
