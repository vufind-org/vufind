<?php

/**
 * Oai resumption service test case.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db\Service;

use Doctrine\ORM\EntityManager;
use Exception;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\OaiResumption;
use VuFind\Db\Entity\OaiResumptionEntityInterface;
use VuFind\Db\Entity\PluginManager;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\OaiResumptionService;

use function count;
use function intval;

/**
 * Oai resumption service test case.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OaiResumptionServiceTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * OaiResumption service object to test.
     *
     * @param MockObject&EntityManager      $entityManager Mock entity manager object
     * @param MockObject&PluginManager      $pluginManager Mock plugin manager object
     * @param ?OaiResumptionEntityInterface $oaiResumption Mock OaiResumption entity object
     *
     * @return MockObject
     */
    protected function getService(
        MockObject&EntityManager $entityManager,
        MockObject&PluginManager $pluginManager,
        ?OaiResumptionEntityInterface $oaiResumption = null,
    ): MockObject&OaiResumptionService {
        $persistenceManager = $this->createMock(PersistenceManager::class);
        $serviceMock = $this->getMockBuilder(OaiResumptionService::class)
            ->onlyMethods(['createEntity'])
            ->setConstructorArgs([$entityManager, $pluginManager, $persistenceManager])
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
     * @return MockObject&PluginManager
     */
    protected function getPluginManager(bool $setExpectation = false): MockObject&PluginManager
    {
        $pluginManager = $this->createMock(PluginManager::class);
        if ($setExpectation) {
            $pluginManager->expects($this->once())->method('get')
                ->with($this->equalTo(OaiResumptionEntityInterface::class))
                ->willReturn(new OaiResumption());
        }
        return $pluginManager;
    }

    /**
     * Mock entity manager.
     *
     * @param int $count Expectation count
     *
     * @return MockObject&EntityManager
     */
    protected function getEntityManager(int $count = 0): MockObject&EntityManager
    {
        $entityManager = $this->createMock(EntityManager::class);
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
        $pluginManager = $this->getPluginManager();
        $resumptionService = $this->getService($entityManager, $pluginManager);
        $queryStmt = "DELETE FROM VuFind\Db\Entity\OaiResumptionEntityInterface O WHERE O.expires <= :now";

        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
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
    public function testFindToken(): void
    {
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager();
        $resumptionService = $this->getService($entityManager, $pluginManager);
        $queryStmt = "SELECT O FROM VuFind\Db\Entity\OaiResumptionEntityInterface O WHERE O.id = :id";

        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $entityManager->expects($this->once())->method('createQuery')
            ->with($this->equalTo($queryStmt))
            ->willReturn($query);
        $oaiResumption = $this->createMock(\VuFind\Db\Entity\OaiResumption::class);
        $query->expects($this->once())->method('getOneOrNullResult')
            ->willReturn($oaiResumption);
        $query->expects($this->once())->method('setParameters')
            ->with(['id' => 'foo'])
            ->willReturn($query);
        $this->assertEquals($oaiResumption, $resumptionService->findToken('foo'));
    }

    /**
     * Data provide for testEncodeParams()
     *
     * @return array
     */
    public static function encodeParamsProvider(): array
    {
        // The expected result is encoded in the test below; both data sets represent the
        // same values, but in different orders. We want to be sure the result is the same
        // regardless of order.
        return [
            'sorted keys' => [['cursor' => 20, 'cursorMark' => 100, 'foo' => 'bar']],
            'unsorted keys' => [['foo' => 'bar', 'cursorMark' => 100, 'cursor' => 20]],
        ];
    }

    /**
     * Test encoding parameters.
     *
     * @param array $params Parameters to encode.
     *
     * @return void
     *
     * @dataProvider encodeParamsProvider
     */
    public function testEncodeParams(array $params): void
    {
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager();
        $resumptionService = $this->getService($entityManager, $pluginManager);
        $this->assertEquals(
            'cursor=20&cursorMark=100&foo=bar',
            $this->callMethod($resumptionService, 'encodeParams', [$params])
        );
    }

    /**
     * Data provider for testCreateTokenSuccess
     *
     * @return Generator
     */
    public static function getTestDuplicatesData(): Generator
    {
        yield 'one token and success' => [
          [
            'params' => [
              'param5' => 'cat',
            ],
            'timestamp' => 1739870677 + 99999,
          ],
          [
            'onetokenonly',
          ],
        ];
        yield 'token duplicate and success' => [
          [
            'params' => [
              'param1' => 0,
              'param2' => 'mainecoon',
              'param3' => 'calico',
            ],
            'timestamp' => 1739870677 + 99999,
          ],
          [
            'testtokenfirstduplicate',
            'testtokenfirstduplicate',
            'testtokensecondsuccess',
          ],
        ];
        yield 'token 6 duplicates and error' => [
          [
            'params' => [
              'param1' => 0,
              'param2' => 'norwegianforestcat',
              'param3' => 'turle',
            ],
            'timestamp' => 1739870677 + 99999,
          ],
          [
            'testtokenfirstduplicate',
            'testtokenfirstduplicate',
            'testtokenfirstduplicate',
            'testtokenfirstduplicate',
            'testtokenfirstduplicate',
            'testtokenfirstduplicate',
            'testtokenfirstduplicate',
          ],
          'Test error: Duplicate token found.',
        ];
    }

    /**
     * Test duplicate tokens but success on the second try
     *
     * @param array  $token               Array with params and timestamp
     * @param array  $randomTokenSequence Array containing strings to simulate duplicate tokens
     * @param string $error               If set, will expect this iteration to throw this error message
     *
     * @return       void
     * @dataProvider getTestDuplicatesData
     */
    public function testDuplicates(array $token, array $randomTokenSequence, string $error = ''): void
    {
        if ($error) {
            $this->expectExceptionMessage($error);
        }
        $previousToken = '';
        $container = new \VuFindTest\Container\MockContainer($this);
        $row = $container->createMock(OaiResumption::class, ['getToken', 'setToken']);
        $row->expects($this->any())->method('getToken')->willReturnCallback(
            function () use (&$previousToken) {
                return $previousToken;
            }
        );
        $row->expects($this->any())->method('setToken')->willReturnCallback(
            function ($t) use (&$previousToken, $row) {
                $previousToken = $t;
                return $row;
            }
        );
        $oaiResumptionService = $container->createMock(
            OaiResumptionService::class,
            ['createRandomToken', 'createEntity', 'persistEntity']
        );
        $oaiResumptionService->expects($this->any())->method('createRandomToken')->willReturnCallback(
            function () use (&$randomTokenSequence, $row) {
                $newToken = array_shift($randomTokenSequence);
                if ($newToken === $row->getToken()) {
                    throw new Exception('Test error: Duplicate token found.');
                }
                return $newToken;
            }
        );
        $oaiResumptionService->expects($this->any())->method('createEntity')->willReturn($row);

        // Create first token as baseline
        $oaiResumptionService->createAndPersistToken(['params' => $token['params']], $token['timestamp']);

        if (count($randomTokenSequence) > 1) {
            // Create second token and try to assign new random token sequences
            $oaiResumptionService->createAndPersistToken(['params' => $token['params']], $token['timestamp']);
        }
        $this->assertEmpty($randomTokenSequence, 'Used all the tokens in random token generation.');
    }

    /**
     * Test simple get tokens data provider
     *
     * @return Generator
     */
    public static function getTestTokenRetrieval(): Generator
    {
        yield 'get token with random generated string' => [
          '694ae4fb77426d7b72fff63b584a39a77e37339440d291b55da78352220ece57',
          'param1=dog',
        ];
        yield 'get token with legacy id' => [
          '25',
          'param1=cat',
        ];
        yield 'get non-existing token' => [
          '512',
          null,
        ];
    }

    /**
     * Very simple array acting as a database
     *
     * @var array
     */
    protected array $mockEntities = [
      [
        'id' => 25,
        'token' => null,
        'params' => 'param1=cat',
        'expires' => 99999999,
      ],
      [
        'id' => 26,
        'token' => '694ae4fb77426d7b72fff63b584a39a77e37339440d291b55da78352220ece57',
        'params' => 'param1=dog',
        'expires' => 99999999,
      ],
    ];

    /**
     * Test legacy retrieval
     *
     * @param string  $token          Token used to search for row
     * @param ?string $expectedParams Expected parameters to be returned or null for no results
     *
     * @return       void
     * @dataProvider getTestTokenRetrieval
     */
    public function testTokenRetrieval(string $token, ?string $expectedParams): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $mockRow = $container->createMock(OaiResumptionEntityInterface::class, []);
        $mockDb = [];
        foreach ($this->mockEntities as $entity) {
            $rowClone = clone $mockRow;
            $rowClone->expects($this->any())->method('getId')->willReturn($entity['id']);
            $rowClone->setExpiry(\DateTime::createFromFormat('U', $entity['expires']));
            $rowClone->expects($this->any())->method('getResumptionParameters')->willReturn($entity['params']);
            $rowClone->expects($this->any())->method('getToken')->willReturn($entity['token']);
            $mockDb[] = $rowClone;
        }
        $mockService = $container->createMock(OaiResumptionService::class, ['findWithToken', 'findWithLegacyIdToken']);

        $lookupFunction = function ($select) use ($mockDb) {
            foreach ($mockDb as $entry) {
                if (!empty($select['id']) && $entry->getId() === intval($select['id'])) {
                    return $entry;
                }
                if (!empty($select['token']) && $entry->getToken() === $select['token']) {
                    return $entry;
                }
            }
            return null;
        };
        $mockService->expects($this->any())->method('findWithToken')->willReturnCallback(
            function ($token) use ($lookupFunction) {
                return $lookupFunction(compact('token'));
            }
        );
        $mockService->expects($this->any())->method('findWithLegacyIdToken')->willReturnCallback(
            function ($id) use ($lookupFunction) {
                return $lookupFunction(compact('id'));
            }
        );
        $token = $mockService->findWithTokenOrLegacyIdToken($token);
        $this->assertEquals($expectedParams, $expectedParams ? $token->getResumptionParameters() : null);
    }
}
