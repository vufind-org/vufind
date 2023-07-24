<?php

/**
 * OaiResumptionService Test Class
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

namespace VuFindTest\Db\Service;

use VuFind\Db\Entity\OaiResumption;

/**
 * OaiResumptionService Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OaiResumptionServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * OaiResumption service object to test.
     *
     * @param MockObject  $entityManager Mock entity manager object
     * @param MockObject  $pluginManager Mock plugin manager object
     * @param ?MockObject $oaiResumption Mock OaiResumption entity object
     *
     * @return MockObject
     */
    protected function getService(
        $entityManager,
        $pluginManager,
        $oaiResumption = null,
    ) {
        $serviceMock = $this->getMockBuilder(
            \VuFind\Db\Service\OaiResumptionService::class
        )
            ->onlyMethods(['createEntity'])
            ->setConstructorArgs([$entityManager, $pluginManager])
            ->getMock();
        if ($oaiResumption) {
            $serviceMock->expects($this->once())->method('createEntity')
                ->willReturn($oaiResumption);
        }
        return $serviceMock;
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
                ->with($this->equalTo(OaiResumption::class))
                ->willReturn(new OaiResumption());
        }
        return $pluginManager;
    }

    /**
     * Mock entity manager.
     *
     * @param int $count Expectation count
     *
     * @return MockObject
     */
    protected function getEntityManager($count = 0)
    {
        $entityManager = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager->expects($this->exactly($count))->method('persist');
        $entityManager->expects($this->exactly($count))->method('flush');
        return $entityManager;
    }

    /**
     * Test removing all expired tokens from the database.
     *
     * @return void
     */
    public function testRemoveExpired(): void
    {
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager(true);
        $resumptionService = $this->getService($entityManager, $pluginManager);
        $queryStmt = "DELETE FROM VuFind\Db\Entity\OaiResumption O WHERE O.expires <= :now";

        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'setParameters'])
            ->getMockForAbstractClass();
        $entityManager->expects($this->once())->method('createQuery')
            ->with($this->equalTo($queryStmt))
            ->willReturn($query);
        $query->expects($this->once())->method('execute');
        $query->expects($this->once())->method('setParameters')
            ->with($this->anything())
            ->willReturn($query);
        $resumptionService->removeExpired();
    }

    /**
     * Test retrieving a row from the database based on primary key.
     *
     * @return void
     */
    public function testfindToken(): void
    {
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager(true);
        $resumptionService = $this->getService($entityManager, $pluginManager);
        $queryStmt = "SELECT O FROM VuFind\Db\Entity\OaiResumption O WHERE O.id = :token";

        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult', 'setParameters'])
            ->getMockForAbstractClass();
        $entityManager->expects($this->once())->method('createQuery')
            ->with($this->equalTo($queryStmt))
            ->willReturn($query);
        $oaiResumption = $this->getMockBuilder(\VuFind\Db\Entity\OaiResumption::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->once())->method('getResult')
            ->willReturn([$oaiResumption]);
        $query->expects($this->once())->method('setParameters')
            ->with(['token' => 'foo'])
            ->willReturn($query);
        $this->assertEquals($oaiResumption, $resumptionService->findToken('foo'));
    }

    /**
     * Test encoding parameters.
     *
     * @return void
     */
    public function testEncodeParams(): void
    {
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager();
        $resumptionService = $this->getService($entityManager, $pluginManager);
        $params = ['cursor' => 20, 'cursorMark' => 100, 'foo' => 'bar'];
        $queryString  = 'cursor=20&cursorMark=100&foo=bar';
        $this->assertEquals($queryString, $resumptionService->encodeParams($params));
    }

    /**
     * Test encoding parameters (with unsorted keys, to confirm that ksort works).
     *
     * @return void
     */
    public function testEncodeParamsWithUnsortedKeys(): void
    {
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager();
        $resumptionService = $this->getService($entityManager, $pluginManager);
        $params = ['foo' => 'bar', 'cursorMark' => 100, 'cursor' => 20];
        $queryString  = 'cursor=20&cursorMark=100&foo=bar';
        $this->assertEquals($queryString, $resumptionService->encodeParams($params));
    }

    /**
     * Test saving a new token.
     *
     * @return void
     */
    public function testSaveToken(): void
    {
        $entityManager = $this->getEntityManager(1);
        $pluginManager = $this->getPluginManager();
        $oaiResumption = $this->getMockBuilder(\VuFind\Db\Entity\OaiResumption::class)
            ->disableOriginalConstructor()
            ->getMock();
        $params = ['cursor' => 20,
            'cursorMark' => 100,
            'foo' => 'bar'];
        $queryString  = 'cursor=20&cursorMark=100&foo=bar';
        $oaiResumption->expects($this->once())->method('setResumptionParameters')
            ->with($queryString)
            ->willReturn($oaiResumption);
        $oaiResumption->expects($this->once())->method('setExpiry')
            ->with($this->anything())
            ->willReturn($oaiResumption);
        $oaiResumption->expects($this->once())->method('getId')
            ->willReturn(1);
        $resumptionService = $this->getService($entityManager, $pluginManager, $oaiResumption);
        $this->assertEquals(1, $resumptionService->saveToken($params, 1666782990));
    }
}
