<?php

/**
 * IdentifierLinksLookup test class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use Laminas\View\Renderer\PhpRenderer;
use VuFind\AjaxHandler\IdentifierLinksLookup;
use VuFind\AjaxHandler\IdentifierLinksLookupFactory;
use VuFind\IdentifierLinker\IdentifierLinkerInterface;
use VuFind\IdentifierLinker\PluginManager;

use function func_get_args;

/**
 * IdentifierLinksLookup test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class IdentifierLinksLookupTest extends \VuFindTest\Unit\AjaxHandlerTestCase
{
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

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
            \VuFind\Config\ConfigManagerInterface::class,
            $this->getMockConfigManager(compact('config'))
        );
    }

    /**
     * Create a mock plugin.
     *
     * @param mixed  $value    Value to return in response to identifier request.
     * @param string $times    How many times do we expect this method to be called?
     * @param int    $key      Which key is matched in the response?
     * @param array  $expected What is the expected identifier link request?
     *
     * @return IdentifierLinkerInterface
     */
    protected function getMockPlugin(
        mixed $value,
        string $times = 'once',
        int $key = 0,
        array $expected = [['doi' => 'bar']]
    ) {
        $mockPlugin = $this->container
            ->createMock(IdentifierLinkerInterface::class, ['getLinks']);
        $mockPlugin->expects($this->$times())->method('getLinks')
            ->with($this->equalTo($expected))
            ->willReturn(
                [
                    $key => [
                        [
                            'link' => 'http://' . $value,
                            'label' => $value,
                            'icon' => 'remote-icon',
                            'localIcon' => 'local-icon',
                        ],
                    ],
                ]
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
     * @param array $requested Identifier(s) to test request with
     *
     * @return array
     */
    protected function getHandlerResults($requested = [['doi' => 'bar']])
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
        // JSON encode parameters to the render method so that it returns a string
        // that we can make assertions about in our tests.
        $mockRenderer->expects($this->any())
            ->method('render')
            ->willReturnCallback(
                function () {
                    return json_encode(func_get_args());
                }
            );

        $this->container->set('ViewRenderer', $mockRenderer);

        $factory = new IdentifierLinksLookupFactory();
        $handler = $factory($this->container, IdentifierLinksLookup::class);
        $params = $this->getParamsHelper(content: json_encode($requested));
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
                ['IdentifierLinks' => ['resolver' => 'foo']],
                false,
                'remote-icon',
            ],
            [
                ['IdentifierLinks' => ['resolver' => 'foo', 'new_window' => true]],
                true,
                'remote-icon',
            ],
            [
                ['IdentifierLinks' => ['resolver' => 'foo', 'proxy_icons' => true]],
                false,
                'http://localhost/cover-show?proxy=remote-icon',
            ],
            [
                [
                    'IdentifierLinks' => [
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
     * Test a single identifier link lookup.
     *
     * @param array  $config     Configuration
     * @param bool   $newWindow  Expected "new window" setting
     * @param string $remoteIcon Expected icon value
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestSingleLookupData')]
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
        $expectedTemplate = 'ajax/identifierLinks.phtml';
        $expectedLinks = [
            [
                'link' => 'http://baz',
                'label' => 'baz',
                'icon' => $remoteIcon,
                'localIcon' => '(local-icon)',
            ],
        ];
        $this->assertEquals(
            [
                [
                    0 => json_encode([$expectedTemplate, ['data' => $expectedLinks, 'newWindow' => $newWindow]]),
                ],
            ],
            $this->getHandlerResults()
        );
    }

    /**
     * Test an identifier link lookup in two handlers, with "first" mode turned on by default.
     *
     * @return void
     */
    public function testFirstDefaultLookup()
    {
        // Set up config manager:
        $this->setupConfig(['IdentifierLinks' => ['resolver' => 'foo,foo2']]);

        // Set up plugin manager:
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz'),
                'foo2' => $this->getMockPlugin('baz2', 'never'),
            ]
        );

        // Test the handler:
        $expectedTemplate = 'ajax/identifierLinks.phtml';
        $expectedLinks = [
            [
                'link' => 'http://baz',
                'label' => 'baz',
                'icon' => 'remote-icon',
                'localIcon' => '(local-icon)',
            ],
        ];
        $this->assertEquals(
            [
                [
                    0 => json_encode([$expectedTemplate, ['data' => $expectedLinks, 'newWindow' => false]]),
                ],
            ],
            $this->getHandlerResults()
        );
    }

    /**
     * Test an identifier link lookup in two handlers, with "first" mode turned on explicitly.
     *
     * @return void
     */
    public function testFirstExplicitLookup()
    {
        // Set up config manager:
        $this->setupConfig(
            ['IdentifierLinks' => ['resolver' => 'foo,foo2', 'multi_resolver_mode' => 'first']]
        );

        // Set up plugin manager:
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz'),
                'foo2' => $this->getMockPlugin('baz2', 'never'),
            ]
        );

        // Test the handler:
        $expectedTemplate = 'ajax/identifierLinks.phtml';
        $expectedLinks = [
            [
                'link' => 'http://baz',
                'label' => 'baz',
                'icon' => 'remote-icon',
                'localIcon' => '(local-icon)',
            ],
        ];
        $this->assertEquals(
            [
                [
                    0 => json_encode([$expectedTemplate, ['data' => $expectedLinks, 'newWindow' => false]]),
                ],
            ],
            $this->getHandlerResults()
        );
    }

    /**
     * Test an identifier link lookup in two handlers, with "first" mode turned on explicitly,
     * where each handler returns results for a different DOI.
     *
     * @return void
     */
    public function testFirstExplicitLookupMultipleDOIs()
    {
        // Set up config manager:
        $this->setupConfig(
            ['IdentifierLinks' => ['resolver' => 'foo,foo2,foo3', 'multi_resolver_mode' => 'first']]
        );

        // Set up plugin manager:
        $request = [['doi' => 'bar'], ['doi' => 'bar2']];
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz', 'once', 0, $request),
                'foo2' => $this->getMockPlugin('baz2', 'once', 1, $request),
                // The previous handlers will satisfy the request, so this one will
                // never be called; included to verify short-circuit behavior:
                'foo3' => $this->getMockPlugin('baz', 'never', 0, $request),
            ]
        );

        // Test the handler:
        $expectedTemplate = 'ajax/identifierLinks.phtml';
        $expectedLinks0 = [
            [
                'link' => 'http://baz',
                'label' => 'baz',
                'icon' => 'remote-icon',
                'localIcon' => '(local-icon)',
            ],
        ];
        $expectedLinks1 = [
            [
                'link' => 'http://baz2',
                'label' => 'baz2',
                'icon' => 'remote-icon',
                'localIcon' => '(local-icon)',
            ],
        ];
        $this->assertEquals(
            [
                [
                    0 => json_encode([$expectedTemplate, ['data' => $expectedLinks0, 'newWindow' => false]]),
                    1 => json_encode([$expectedTemplate, ['data' => $expectedLinks1, 'newWindow' => false]]),
                ],
            ],
            $this->getHandlerResults($request)
        );
    }

    /**
     * Test an identifier link lookup in two handlers, with "merge" mode turned on.
     *
     * @return void
     */
    public function testMergeLookup()
    {
        // Set up config manager:
        $this->setupConfig(
            ['IdentifierLinks' => ['resolver' => 'foo,foo2', 'multi_resolver_mode' => 'merge']]
        );

        // Set up plugin manager:
        $this->setupPluginManager(
            [
                'foo' => $this->getMockPlugin('baz'),
                'foo2' => $this->getMockPlugin('baz2'),
            ]
        );
        // Test the handler:
        $expectedTemplate = 'ajax/identifierLinks.phtml';
        $expectedLinks = [
            [
                'link' => 'http://baz',
                'label' => 'baz',
                'icon' => 'remote-icon',
                'localIcon' => '(local-icon)',
            ],
            [
                'link' => 'http://baz2',
                'label' => 'baz2',
                'icon' => 'remote-icon',
                'localIcon' => '(local-icon)',
            ],
        ];
        $this->assertEquals(
            [
                [
                    0 => json_encode([$expectedTemplate, ['data' => $expectedLinks, 'newWindow' => false]]),
                ],
            ],
            $this->getHandlerResults()
        );
    }
}
