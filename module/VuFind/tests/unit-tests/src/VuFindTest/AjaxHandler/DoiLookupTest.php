<?php

/**
 * DoiLookup test class.
 *
 * PHP version 8
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

use Laminas\View\Renderer\PhpRenderer;
use VuFind\AjaxHandler\DoiLookup;
use VuFind\AjaxHandler\DoiLookupFactory;
use VuFind\DoiLinker\DoiLinkerInterface;
use VuFind\DoiLinker\PluginManager;

/**
 * DoiLookup test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DoiLookupTest extends \VuFindTest\Unit\AjaxHandlerTestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Set up configuration for a test.
     *
     * @param array $config Configuration to set.
     *
     * @return void
     */
    protected function setupConfig($config)
    {
        $this->container->set(
            \VuFind\Config\PluginManager::class,
            $this->getMockConfigPluginManager(compact('config'))
        );
    }

    /**
     * Create a mock plugin.
     *
     * @param mixed  $value    Value to return in response to DOI request.
     * @param string $times    How many times do we expect this method to be called?
     * @param string $doi      What DOI does this handler return data for?
     * @param array  $expected What is the expected DOI request?
     *
     * @return DoiLinkerInterface
     */
    protected function getMockPlugin(
        $value,
        $times = 'once',
        $doi = 'bar',
        $expected = ['bar']
    ) {
        $mockPlugin = $this->container
            ->createMock(DoiLinkerInterface::class, ['getLinks']);
        $mockPlugin->expects($this->$times())->method('getLinks')
            ->with($this->equalTo($expected))
            ->will(
                $this->returnValue(
                    [
                        $doi => [
                            [
                                'link' => 'http://' . $value,
                                'label' => $value,
                                'icon' => 'remote-icon',
                                'localIcon' => 'local-icon',
                            ],
                        ],
                    ]
                )
            );
        return $mockPlugin;
    }

    /**
     * Set up a plugin manager for a test.
     *
     * @param array $plugins Plugins to insert into container.
     *
     * @return void
     */
    protected function setupPluginManager($plugins)
    {
        $pm = new PluginManager($this->container);
        foreach ($plugins as $name => $plugin) {
            $pm->setService($name, $plugin);
        }
        $this->container->set(PluginManager::class, $pm);
    }

    /**
     * After setupConfig() and setupPluginManager() have been called, run the
     * standard default test.
     *
     * @param array $requested DOI(s) to test request with
     *
     * @return array
     */
    protected function getHandlerResults($requested = ['bar'])
    {
        $plugins = [
            'serverurl' => function ($path) {
                return "http://localhost/$path";
            },
            'url' => function ($route, $options, $params) {
                return "$route?" . http_build_query($params['query'] ?? []);
            },
            'icon' => function ($icon) {
                return "($icon)";
            },
        ];

        $mockRenderer = $this->container->createMock(PhpRenderer::class);
        $mockRenderer->expects($this->any())
            ->method('plugin')
            ->willReturnCallback(
                function ($plugin) use ($plugins) {
                    return $plugins[$plugin] ?? null;
                }
            );

        $this->container->set('ViewRenderer', $mockRenderer);

        $factory = new DoiLookupFactory();
        $handler = $factory($this->container, DoiLookup::class);
        $params = $this->getParamsHelper(['doi' => $requested]);
        return $handler->handleRequest($params);
    }

    /**
     * Data provider for testSingleLookup
     *
     * @return array
     */
    public static function getTestSingleLookupData(): array
    {
        return [
            [
                ['DOI' => ['resolver' => 'foo']],
                false,
                'remote-icon',
            ],
            [
                ['DOI' => ['resolver' => 'foo', 'new_window' => true]],
                true,
                'remote-icon',
            ],
            [
                ['DOI' => ['resolver' => 'foo', 'proxy_icons' => true]],
                false,
                'http://localhost/cover-show?proxy=remote-icon',
            ],
            [
                [
                    'DOI' => [
                        'resolver' => 'foo',
                        'new_window' => true,
                        'proxy_icons' => true,
                    ],
                ],
                true,
                'http://localhost/cover-show?proxy=remote-icon',
            ],
        ];
    }

    /**
     * Test a single DOI lookup.
     *
     * @param array  $config     Configuration
     * @param bool   $newWindow  Expected "new window" setting
     * @param string $remoteIcon Expected icon value
     *
     * @dataProvider getTestSingleLookupData
     *
     * @return void
     */
    public function testSingleLookup(
        array $config,
        bool $newWindow,
        string $remoteIcon
    ): void {
        // Set up config manager:
        $this->setupConfig($config);

        // Set up plugin manager:
        $this->setupPluginManager(
            ['foo' => $this->getMockPlugin('baz')]
        );

        // Test the handler:
        $this->assertEquals(
            [
                [
                    'bar' => [
                        [
                            'link' => 'http://baz',
                            'label' => 'baz',
                            'newWindow' => $newWindow,
                            'icon' => $remoteIcon,
                            'localIcon' => '(local-icon)',
                        ],
                    ],
                ],
            ],
            $this->getHandlerResults()
        );
    }

    /**
     * Test a DOI lookup in two handlers, with "first" mode turned on by default.
     *
     * @return void
     */
    public function testFirstDefaultLookup()
    {
        // Set up config manager:
        $this->setupConfig(['DOI' => ['resolver' => 'foo,foo2']]);

        // Set up plugin manager:
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz'),
                'foo2' => $this->getMockPlugin('baz2', 'never'),
            ]
        );

        // Test the handler:
        $this->assertEquals(
            [
                [
                    'bar' => [
                        [
                            'link' => 'http://baz',
                            'label' => 'baz',
                            'newWindow' => false,
                            'icon' => 'remote-icon',
                            'localIcon' => '(local-icon)',
                        ],
                    ],
                ],
            ],
            $this->getHandlerResults()
        );
    }

    /**
     * Test a DOI lookup in two handlers, with "first" mode turned on explicitly.
     *
     * @return void
     */
    public function testFirstExplicitLookup()
    {
        // Set up config manager:
        $this->setupConfig(
            ['DOI' => ['resolver' => 'foo,foo2', 'multi_resolver_mode' => 'first']]
        );

        // Set up plugin manager:
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz'),
                'foo2' => $this->getMockPlugin('baz2', 'never'),
            ]
        );

        // Test the handler:
        $this->assertEquals(
            [
                [
                    'bar' => [
                        [
                            'link' => 'http://baz',
                            'label' => 'baz',
                            'newWindow' => false,
                            'icon' => 'remote-icon',
                            'localIcon' => '(local-icon)',
                        ],
                    ],
                ],
            ],
            $this->getHandlerResults()
        );
    }

    /**
     * Test a DOI lookup in two handlers, with "first" mode turned on explicitly,
     * where each handler returns results for a different DOI.
     *
     * @return void
     */
    public function testFirstExplicitLookupMultipleDOIs()
    {
        // Set up config manager:
        $this->setupConfig(
            ['DOI' => ['resolver' => 'foo,foo2,foo3', 'multi_resolver_mode' => 'first']]
        );

        // Set up plugin manager:
        $request = ['bar', 'bar2'];
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz', 'once', 'bar', $request),
                'foo2' => $this->getMockPlugin('baz2', 'once', 'bar2', $request),
                // The previous handlers will satisfy the request, so this one will
                // never be called; included to verify short-circuit behavior:
                'foo3' => $this->getMockPlugin('baz', 'never', 'bar', $request),
            ]
        );

        // Test the handler:
        $this->assertEquals(
            [
                [
                    'bar' => [
                        [
                            'link' => 'http://baz',
                            'label' => 'baz',
                            'newWindow' => false,
                            'icon' => 'remote-icon',
                            'localIcon' => '(local-icon)',
                        ],
                    ],
                    'bar2' => [
                        [
                            'link' => 'http://baz2',
                            'label' => 'baz2',
                            'newWindow' => false,
                            'icon' => 'remote-icon',
                            'localIcon' => '(local-icon)',
                        ],
                    ],
                ],
            ],
            $this->getHandlerResults($request)
        );
    }

    /**
     * Test a DOI lookup in two handlers, with "merge" mode turned on.
     *
     * @return void
     */
    public function testMergeLookup()
    {
        // Set up config manager:
        $this->setupConfig(
            ['DOI' => ['resolver' => 'foo,foo2', 'multi_resolver_mode' => 'merge']]
        );

        // Set up plugin manager:
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz'),
                'foo2' => $this->getMockPlugin('baz2'),
            ]
        );
        // Test the handler:
        $this->assertEquals(
            [
                [
                    'bar' => [
                        [
                            'link' => 'http://baz',
                            'label' => 'baz',
                            'newWindow' => false,
                            'icon' => 'remote-icon',
                            'localIcon' => '(local-icon)',
                        ],
                        [
                            'link' => 'http://baz2',
                            'label' => 'baz2',
                            'newWindow' => false,
                            'icon' => 'remote-icon',
                            'localIcon' => '(local-icon)',
                        ],
                    ],
                ],
            ],
            $this->getHandlerResults()
        );
    }
}
