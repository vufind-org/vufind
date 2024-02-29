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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\UrlShortener;

use Exception;
use PHPUnit\Framework\TestCase;
use VuFind\Db\Entity\Shortlinks;
use VuFind\UrlShortener\Database;

/**
 * "Database" URL shortener test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseTest extends TestCase
{
    /**
     * Database object to test.
     *
     * @param MockObject      $entityManager Mock entity manager object
     * @param MockObject      $pluginManager Mock plugin manager object
     * @param MockObject|null $shortlink     Mock shortlink entity object
     * @param string          $hashAlgorithm Hash Algorithm to be used
     *
     * @return Database
     */
    protected function getShortener(
        $entityManager,
        $pluginManager,
        $shortlink = null,
        $hashAlgorithm = 'md5'
    ) {
        $serviceMock = $this->getMockBuilder(
            \VuFind\Db\Service\ShortlinksService::class
        )
            ->onlyMethods(['createEntity'])
            ->setConstructorArgs([$entityManager, $pluginManager])
            ->getMock();
        if ($shortlink) {
            $serviceMock->expects($this->once())->method('createEntity')
                ->willReturn($shortlink);
        }
        $database = new Database(
            'http://foo',
            $serviceMock,
            'RAnD0mVuFindSa!t',
            $hashAlgorithm
        );

        return $database;
    }

    /**
     * Mock entity plugin manager.
     *
     * @param bool $setExpectation Flag to set the method expectations.
     *
     * @return MockObject
     */
    protected function getPluginManager($setExpectation = false)
    {
        $pluginManager = $this->getMockBuilder(
            \VuFind\Db\Entity\PluginManager::class
        )->disableOriginalConstructor()
            ->getMock();
        if ($setExpectation) {
            $pluginManager->expects($this->once())->method('get')
                ->with($this->equalTo(Shortlinks::class))
                ->willReturn(new Shortlinks());
        }
        return $pluginManager;
    }

    /**
     * Mock entity manager.
     *
     * @param string|null $shortlink Input query parameter
     * @param int         $count     Expectation count
     *
     * @return MockObject
     */
    protected function getEntityManager($shortlink = null, $count = 0)
    {
        $entityManager = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($shortlink) {
            $entityManager->expects($this->exactly($count))->method('persist');
            $entityManager->expects($this->exactly($count))->method('flush');
        }
        return $entityManager;
    }

    /**
     * Mock queryBuilder
     *
     * @param string $parameter Input query parameter
     * @param array  $result    Expected return value of getResult method.
     *
     * @return MockObject
     */
    protected function getQueryBuilder($parameter, $result)
    {
        $queryBuilder = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $queryBuilder->expects($this->once())->method('select')
            ->with($this->equalTo('s'))
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('from')
            ->with($this->equalTo(Shortlinks::class), $this->equalTo('s'))
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('where')
            ->with($this->equalTo('s.hash = :hash'))
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('setParameter')
            ->with($this->equalTo('hash'), $this->equalTo($parameter))
            ->willReturn($queryBuilder);
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->expects($this->once())->method('getResult')
            ->willReturn($result);
        $queryBuilder->expects($this->once())->method('getQuery')
            ->willReturn($query);
        return $queryBuilder;
    }

    /**
     * Test that the shortener works correctly under base62 hashing
     *
     * @return void
     *
     * @throws Exception
     */
    public function testGetBase62Hash()
    {
        $shortlink = $this->getMockBuilder(\VuFind\Db\Entity\Shortlinks::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager = $this->getEntityManager($shortlink, 2);
        $pluginManager = $this->getPluginManager();
        $shortlink->expects($this->once())->method('setPath')
            ->with($this->equalTo('/bar'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('setCreated')
            ->with($this->anything())
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('getId')
            ->willReturn(2);
        $shortlink->expects($this->once())->method('setHash')
            ->with($this->equalTo('2'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('getHash')
            ->willReturn('2');
        $db = $this->getShortener(
            $entityManager,
            $pluginManager,
            $shortlink,
            'base62'
        );
        $this->assertEquals('http://foo/short/2', $db->shorten('http://foo/bar'));
    }

    /**
     * Test that the shortener works correctly under "happy path."
     *
     * @return void
     *
     * @throws Exception
     */
    public function testsaveAndShortenHash()
    {
        $shortlink = $this->getMockBuilder(\VuFind\Db\Entity\Shortlinks::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager = $this->getEntityManager($shortlink, 1);
        $pluginManager = $this->getPluginManager(true);
        $queryBuilder = $this->getQueryBuilder('a1e7812e2', []);

        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $shortlink->expects($this->once())->method('setHash')
            ->with($this->equalTo('a1e7812e2'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('setPath')
            ->with($this->equalTo('/bar'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('setCreated')
            ->with($this->anything())
            ->willReturn($shortlink);
        $connection = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager->expects($this->exactly(2))->method('getConnection')
            ->willReturn($connection);
        $connection->expects($this->once())->method('beginTransaction')
            ->willReturn($this->equalTo(true));
        $connection->expects($this->once())->method('commit')
            ->willReturn($this->equalTo(true));
        $db = $this->getShortener($entityManager, $pluginManager, $shortlink);
        $this->assertEquals(
            'http://foo/short/a1e7812e2',
            $db->shorten('http://foo/bar')
        );
    }

    /**
     * Test that resolve is supported.
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolution()
    {
        $shortlink = $this->getMockBuilder(\VuFind\Db\Entity\Shortlinks::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager(true);
        $queryBuilder = $this->getQueryBuilder('8ef580184', [$shortlink]);
        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $shortlink->expects($this->once())->method('getPath')
            ->willReturn('/bar');
        $db = $this->getShortener($entityManager, $pluginManager);
        $this->assertEquals('http://foo/bar', $db->resolve('8ef580184'));
    }

    /**
     * Test that resolve errors correctly when given bad input
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolutionOfBadInput()
    {
        $this->expectExceptionMessage('Shortlink could not be resolved: abcd12?');

        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager(true);
        $queryBuilder = $this->getQueryBuilder('abcd12?', []);
        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $db = $this->getShortener($entityManager, $pluginManager);
        $db->resolve('abcd12?');
    }
}
