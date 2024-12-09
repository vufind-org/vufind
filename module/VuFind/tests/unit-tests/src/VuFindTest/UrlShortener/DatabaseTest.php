<?php

/**
 * "Database" URL shortener test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\UrlShortener;

use Exception;
use PHPUnit\Framework\TestCase;
use VuFind\Db\Entity\ShortlinksEntityInterface;
use VuFind\Db\Service\ShortlinksService;
use VuFind\Db\Service\ShortlinksServiceInterface;
use VuFind\UrlShortener\Database;

/**
 * "Database" URL shortener test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseTest extends TestCase
{
    /**
     * Get the object to test.
     *
     * @param ShortlinksServiceInterface $service   Database service object/mock
     * @param string                     $algorithm Hashing algorithm
     *
     * @return Database
     */
    public function getShortener(ShortlinksServiceInterface $service, string $algorithm = 'md5'): Database
    {
        return new Database('http://foo', $service, 'RAnD0mVuFindSa!t', $algorithm);
    }

    /**
     * Test that the shortener works correctly under "happy path."
     *
     * @return void
     *
     * @throws Exception
     */
    public function testShortener(): void
    {
        $hash = 'a1e7812e2'; // expected hash
        $entity = $this->createMock(ShortlinksEntityInterface::class);
        $entity->method('getId')->willReturn(1);
        $entity->expects($this->once())->method('setPath')->with('/bar')->willReturn($entity);
        $entity->method('getPath')->willReturn('/bar');
        $entity->expects($this->once())->method('setHash')->with($hash)->willReturn($entity);
        $entity->method('getHash')->willReturn($hash);
        $service = $this->createMock(ShortlinksService::class);
        $service->expects($this->once())->method('beginTransaction');
        $service->expects($this->once())->method('getShortLinkByHash')->with($hash)->willReturn(null);
        $service->expects($this->once())->method('createEntity')->willReturn($entity);
        $service->expects($this->once())->method('persistEntity')->with($entity);
        $service->expects($this->once())->method('commitTransaction');
        $db = $this->getShortener($service);
        $this->assertEquals('http://foo/short/a1e7812e2', $db->shorten('http://foo/bar'));
    }

    /**
     * Test that the shortener works correctly with legacy hashing.
     *
     * @return void
     *
     * @throws Exception
     */
    public function testShortenerLegacy(): void
    {
        $hash = '1'; // expected hash
        $entity = $this->createMock(ShortlinksEntityInterface::class);
        $entity->method('getId')->willReturn(1);
        $entity->method('getPath')->willReturn('/bar');
        $entity->expects($this->once())->method('setHash')->with($hash)->willReturn($entity);
        $entity->method('getHash')->willReturn($hash);
        $service = $this->createMock(ShortlinksServiceInterface::class);
        $service->expects($this->once())->method('createAndPersistEntityForPath')->with('/bar')->willReturn($entity);
        $service->expects($this->once())->method('persistEntity')->with($entity);
        $db = $this->getShortener($service, 'base62');
        $this->assertEquals('http://foo/short/1', $db->shorten('http://foo/bar'));
    }

    /**
     * Test that resolve is supported.
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolution(): void
    {
        $hash = '8ef580184';
        $entity = $this->createMock(ShortlinksEntityInterface::class);
        $entity->method('getPath')->willReturn('/bar');
        $service = $this->createMock(ShortlinksServiceInterface::class);
        $service->expects($this->once())->method('getShortLinkByHash')->with($hash)->willReturn($entity);
        $db = $this->getShortener($service);
        $this->assertEquals('http://foo/bar', $db->resolve($hash));
    }

    /**
     * Test that resolve errors correctly when given bad input
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolutionOfBadInput(): void
    {
        $this->expectExceptionMessage('Shortlink could not be resolved: abcd12?');

        $service = $this->createMock(ShortlinksServiceInterface::class);
        $service->expects($this->once())->method('getShortLinkByHash')->with('abcd12?')->willReturn(null);
        $db = $this->getShortener($service);
        $db->resolve('abcd12?');
    }
}
