<?php

/**
 * ChannelLoader Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\ChannelProvider;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Cache\Manager as CacheManager;
use VuFind\ChannelProvider\AbstractChannelProvider;
use VuFind\ChannelProvider\ChannelLoader;
use VuFind\ChannelProvider\PluginManager;
use VuFind\Http\PhpEnvironment\Request as HttpRequest;
use VuFind\Record\Loader as RecordLoader;
use VuFind\RecordDriver\DefaultRecord;
use VuFind\Search\Base\Results;
use VuFind\Search\SearchRunner;

/**
 * ChannelLoader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ChannelLoaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testGetRecordContext()
     *
     * @return array[]
     */
    public static function getRecordContextProvider(): array
    {
        $defaultConfig = ['batchSize' => 24, 'pageSize' => 6, 'rowSize' => 6];
        return [
            'no configuration' => [[], [], [], ['record']],
            'one provider' => [
                [
                    'source.Solr' => [
                        'record' => ['bar'],
                    ],
                ],
                [['contents' => 'bar', 'providerId' => 'mock', 'config' => $defaultConfig]],
                [],
                ['record'],
            ],
            'two providers, including config' => [
                [
                    'source.Solr' => [
                        'record' => ['bar', 'baz:xyzzy'],
                    ],
                    'xyzzy' => [
                        'extraConfig',
                    ],
                ],
                [
                    ['contents' => 'bar', 'providerId' => 'mock', 'config' => $defaultConfig],
                    ['contents' => 'baz-extraConfig', 'providerId' => 'mock', 'config' => $defaultConfig],
                ],
                [],
                ['record'],
            ],
            'override section' => [
                [
                    'source.Solr' => [
                        'record' => ['bar'],
                        'recordTab' => ['override'],
                    ],
                ],
                [['contents' => 'override', 'providerId' => 'mock', 'config' => $defaultConfig]],
                [],
                ['recordTab', 'record'],
            ],
            'proper section fallback' => [
                [
                    'source.Solr' => [
                        'record' => ['bar'],
                    ],
                ],
                [['contents' => 'bar', 'providerId' => 'mock', 'config' => $defaultConfig]],
                [],
                ['recordTab', 'record'],
            ],
        ];
    }

    /**
     * Test getRecordContext
     *
     * @param array $config              Configuration
     * @param array $expectedChannelData The channel data we expect to retrieve
     * @param array $expectedTokenData   The token data we expect to retrieve
     * @param array $sections            Config sections to look at for provider settings
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getRecordContextProvider')]
    public function testGetRecordContext(
        array $config,
        array $expectedChannelData,
        array $expectedTokenData,
        array $sections
    ): void {
        $mockRecord = $this->createMock(DefaultRecord::class);
        $recordLoader = $this->getMockRecordLoader();
        $recordLoader->expects($this->once())->method('load')->with('foo', 'Solr')->willReturn($mockRecord);
        $loader = $this->getChannelLoader($config, $recordLoader);
        $context = $loader->getRecordContext('foo', configSections: $sections);
        $this->assertEquals(['driver', 'channels', 'token', 'relatedTokens'], array_keys($context));
        $this->assertEquals($mockRecord, $context['driver']);
        $this->assertEquals($expectedChannelData, $context['channels']);
        $this->assertEquals($expectedTokenData, $context['relatedTokens']);
        $this->assertNull($context['token']);
    }

    /**
     * Get a mock record loader.
     *
     * @return MockObject&RecordLoader
     */
    protected function getMockRecordLoader(): MockObject&RecordLoader
    {
        return $this->createMock(RecordLoader::class);
    }

    /**
     * Get a mock plugin manager that creates fake providers that can be used for testing behavior.
     *
     * @return MockObject&PluginManager
     */
    protected function getMockPluginManager(): MockObject&PluginManager
    {
        $manager = $this->createMock(PluginManager::class);
        $manager->method('get')->willReturnCallback(
            function ($settings) {
                return new class ($settings) extends AbstractChannelProvider {
                    /**
                     * Constructor
                     *
                     * @param string $settings Initial settings to save
                     */
                    public function __construct(protected string $settings)
                    {
                    }

                    /**
                     * Set the options for the provider.
                     *
                     * @param array $options Options
                     *
                     * @return void
                     */
                    public function setOptions(array $options)
                    {
                        if (!empty($options)) {
                            $this->settings .= '-' . implode(':', $options);
                        }
                    }

                    /**
                     * Return channel information derived from a record driver object.
                     *
                     * @param RecordDriver $driver       Record driver
                     * @param string       $channelToken Token identifying a single specific channel
                     * to load (if omitted, all channels will be loaded)
                     *
                     * @return array
                     */
                    public function getFromRecord(\VuFind\RecordDriver\AbstractBase $driver, $channelToken = null)
                    {
                        return [['contents' => $this->settings, 'providerId' => 'mock']];
                    }

                    /**
                     * Return channel information derived from a search results object.
                     *
                     * @param Results $results      Search results
                     * @param string  $channelToken Token identifying a single specific channel
                     * to load (if omitted, all channels will be loaded)
                     *
                     * @return array
                     */
                    public function getFromSearch(Results $results, $channelToken = null)
                    {
                        return [['contents' => $this->settings, 'providerId' => 'mock']];
                    }
                };
            }
        );
        return $manager;
    }

    /**
     * Get a channel loader to test.
     *
     * @param array         $config       Configuration
     * @param ?RecordLoader $recordLoader Record loader (null to create default mock)
     *
     * @return ChannelLoader
     */
    protected function getChannelLoader(array $config = [], ?RecordLoader $recordLoader = null): ChannelLoader
    {
        return new ChannelLoader(
            $config,
            $this->createMock(CacheManager::class),
            $this->getMockPluginManager(),
            $this->createMock(SearchRunner::class),
            $recordLoader ?? $this->getMockRecordLoader(),
            $this->createMock(HttpRequest::class)
        );
    }
}
