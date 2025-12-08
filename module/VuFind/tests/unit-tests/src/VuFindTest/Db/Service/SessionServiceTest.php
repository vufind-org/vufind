<?php

/**
 * SessionService Test Class
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\PluginManager;
use VuFind\Db\Entity\Session;
use VuFind\Db\Entity\SessionEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\SessionService;

/**
 * SessionService Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SessionServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock entity plugin manager.
     *
     * @param bool $setExpectation Flag to set the method expectations.
     *
     * @return PluginManager&MockObject
     */
    protected function getPluginManager($setExpectation = false): PluginManager&MockObject
    {
        $pluginManager = $this->createMock(PluginManager::class);
        if ($setExpectation) {
            $pluginManager->expects($this->once())->method('get')
                ->with(SessionEntityInterface::class)
                ->willReturn(new Session());
        }
        return $pluginManager;
    }

    /**
     * Mock persistence manager.
     *
     * @param int $count Expectation count
     *
     * @return PersistenceManager&MockObject
     */
    protected function getPersistenceManager(int $count = 0): PersistenceManager&MockObject
    {
        $entityManager = $this->createMock(PersistenceManager::class);
        $entityManager->expects($this->exactly($count))->method('persistEntity');
        return $entityManager;
    }

    /**
     * Mock EntityManager
     *
     * @param ?MockObject $session Session returned from repository's findBy method
     *
     * @return MockObject&EntityManager
     */
    protected function getEntityManager(?MockObject $session): MockObject&EntityManager
    {
        $entityManager = $this->createMock(EntityManager::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('findOneBy')
            ->with(['sessionId' => '1'])
            ->willReturn($session);
        $entityManager->expects($this->once())->method('getRepository')
            ->willReturn($repository);
        return $entityManager;
    }

    /**
     * Session service object to test.
     *
     * @param EntityManager           $entityManager      Mock entity manager object
     * @param PluginManager           $pluginManager      Mock plugin manager object
     * @param PersistenceManager      $persistenceManager Persistence manager object
     * @param ?SessionEntityInterface $session            Mock session entity object
     *
     * @return SessionService&MockObject
     */
    protected function getService(
        EntityManager $entityManager,
        PluginManager $pluginManager,
        PersistenceManager $persistenceManager,
        ?SessionEntityInterface $session = null,
    ): SessionService&MockObject {
        $serviceMock = $this->getMockBuilder(SessionService::class)
            ->onlyMethods(['createEntity'])
            ->setConstructorArgs([$entityManager, $pluginManager, $persistenceManager])
            ->getMock();
        if ($session) {
            $serviceMock->expects($this->once())->method('createEntity')
                ->willReturn($session);
        }
        return $serviceMock;
    }

    /**
     * Test retrieving an session object from database.
     *
     * @return void
     */
    public function testGetSessionById(): void
    {
        $session = $this->createMock(Session::class);
        $entityManager = $this->getEntityManager($session);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager();
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager);
        $this->assertEquals($session, $service->getSessionById('1', false));
    }

    /**
     * Test the case where a session is not found and creating a new session
     * is not required.
     *
     * @return void
     */
    public function testSessionNotFound(): void
    {
        $entityManager = $this->getEntityManager(null);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager();
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager);
        $this->assertNull($service->getSessionById('1', false));
    }

    /**
     * Test creating a new session if no existing session is found.
     *
     * @return void
     */
    public function testCreatingSession(): void
    {
        $session = $this->createMock(Session::class);
        $entityManager = $this->getEntityManager(null);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager(1);
        $session->expects($this->once())->method('setSessionId')
            ->with($this->equalTo('1'))
            ->willReturn($session);
        $session->expects($this->once())->method('setCreated')
            ->with($this->anything())
            ->willReturn($session);
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager, $session);
        $this->assertEquals($session, $service->getSessionById('1', true));
    }

    /**
     * Test reading session data.
     *
     * @return void
     */
    public function testReadSession(): void
    {
        $session = $this->createMock(Session::class);
        $entityManager = $this->getEntityManager($session);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager(1);
        $session->expects($this->once())->method('getLastUsed')
            ->willReturn(time() - 1000);
        $session->expects($this->once())->method('setLastUsed')
            ->with($this->anything());
        $session->expects($this->once())->method('getData')
            ->willReturn('foo');
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager);
        $this->assertEquals('foo', $service->readSession('1', 10000000));
    }

    /**
     * Test reading expired session data.
     *
     * @return void
     */
    public function testReadingExpiredSession(): void
    {
        $this->expectException(\VuFind\Exception\SessionExpired::class);
        $this->expectExceptionMessage('Session expired!');
        $session = $this->createMock(Session::class);
        $entityManager = $this->getEntityManager($session);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager();
        $session->expects($this->once())->method('getLastUsed')
            ->willReturn(time() - 1000);
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager);
        $service->readSession('1', 100);
    }

    /**
     * Test storing session data.
     *
     * @return void
     */
    public function testWriteSession(): void
    {
        $session = $this->createMock(Session::class);
        $entityManager = $this->getEntityManager($session);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager(1);
        $session->expects($this->once())->method('setLastUsed')
            ->with($this->anything())
            ->willReturn($session);
        $session->expects($this->once())->method('setData')
            ->with('foo')
            ->willReturn($session);
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager);
        $this->assertEquals(true, $service->WriteSession('1', 'foo'));
    }

    /**
     * Test destroying the session.
     *
     * @return void
     */
    public function testDestroySession(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager();
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('delete')
            ->with(SessionEntityInterface::class, 's')
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('where')
            ->with('s.sessionId = :sid')
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('setParameter')
            ->with('sid', 1)
            ->willReturn($queryBuilder);
        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'getSQL', '_doExecute'])
            ->getMock();
        $query->expects($this->once())->method('execute')
            ->willReturn($this->anything());
        $queryBuilder->expects($this->once())->method('getQuery')
            ->willReturn($query);
        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager);
        $service->destroySession('1');
    }

    /**
     * Test destroying the expired sessions.
     *
     * @return void
     */
    public function testGarbageCollect(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $pluginManager = $this->getPluginManager();
        $persistenceManager = $this->getPersistenceManager();
        $countQuery = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $countQuery->method('getSingleScalarResult')->willReturn(5);
        $countQuery->expects($this->once())->method('setParameter')
            ->with('used', $this->equalToWithDelta(time() - 10000, 1));
        $countDql = "SELECT COUNT(s) FROM VuFind\Db\Entity\SessionEntityInterface s WHERE s.lastUsed < :used";
        $entityManager->expects($this->once())->method('createQuery')->with($countDql)->willReturn($countQuery);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('delete')
            ->with(SessionEntityInterface::class, 's')
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('where')
            ->with('s.lastUsed < :used')
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('setParameter')
            ->with('used', $this->equalToWithDelta(time() - 10000, 1))
            ->willReturn($queryBuilder);
        $deleteQuery = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $deleteQuery->expects($this->once())->method('execute')
            ->willReturn($this->anything());
        $queryBuilder->expects($this->once())->method('getQuery')
            ->willReturn($deleteQuery);
        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $service = $this->getService($entityManager, $pluginManager, $persistenceManager);
        $this->assertEquals(5, $service->garbageCollect(10000));
    }
}
