<?php

/**
 * Channels Test Class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use Laminas\Http\Request;
use Laminas\Stdlib\Parameters;
use VuFind\ChannelProvider\ChannelLoader;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordTab\Channels;

/**
 * Channels Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ChannelsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get the object to test.
     *
     * @param array          $options    Config options
     * @param ?ChannelLoader $mockLoader Channel loader (null for default mock)
     * @param ?RecordDriver  $mockDriver Record driver (null for default mock)
     *
     * @return Channels
     */
    protected function getChannels(
        array $options = [],
        ?ChannelLoader $mockLoader = null,
        ?RecordDriver $mockDriver = null
    ): Channels {
        $channels = new Channels($mockLoader ?? $this->createMock(ChannelLoader::class), $options);
        $channels->setRecordDriver($mockDriver ?? $this->createMock(RecordDriver::class));
        return $channels;
    }

    /**
     * Test getDescription().
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        // Default case:
        $this->assertEquals('Channels', $this->getChannels()->getDescription());
        // Custom case:
        $this->assertEquals('Custom', $this->getChannels(['label' => 'Custom'])->getDescription());
    }

    /**
     * Test supportsAjax().
     *
     * @return void
     */
    public function testSupportsAjax(): void
    {
        $this->assertFalse($this->getChannels()->supportsAjax());
    }

    /**
     * Test getContext() with default config and no request set.
     *
     * @return void
     */
    public function testGetContextWithoutRequest(): void
    {
        $driver = $this->createMock(RecordDriver::class);
        $driver->method('getUniqueID')->willReturn('foo');
        $driver->method('getSearchBackendIdentifier')->willReturn('bar');
        $loader = $this->createMock(ChannelLoader::class);
        $loader->expects($this->once())
            ->method('getRecordContext')
            ->with('foo', null, null, 'bar', ['recordTab', 'record'])
            ->willReturn(['record' => 'context']);
        $channels = $this->getChannels([], $loader, $driver);
        $this->assertEquals(['displaySearchBox' => false, 'record' => 'context'], $channels->getContext());
    }

    /**
     * Test getContext() with default config and a request set.
     *
     * @return void
     */
    public function testGetContextWithRequest(): void
    {
        $driver = $this->createMock(RecordDriver::class);
        $driver->method('getUniqueID')->willReturn('foo');
        $driver->method('getSearchBackendIdentifier')->willReturn('bar');
        $loader = $this->createMock(ChannelLoader::class);
        $loader->expects($this->once())
            ->method('getRecordContext')
            ->with('foo', 'tok', 'prov', 'bar', ['recordTab', 'record'])
            ->willReturn(['record' => 'context']);
        $channels = $this->getChannels([], $loader, $driver);
        $request = new Request();
        $request->setQuery(new Parameters(['channelToken' => 'tok', 'channelProvider' => 'prov']));
        $channels->setRequest($request);
        $this->assertEquals(['displaySearchBox' => false, 'record' => 'context'], $channels->getContext());
    }
}
