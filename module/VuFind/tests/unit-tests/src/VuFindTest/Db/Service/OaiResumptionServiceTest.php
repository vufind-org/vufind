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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Service;

use Exception;
use Generator;
use Laminas\Db\ResultSet\AbstractResultSet;
use VuFind\Db\Entity\OaiResumptionEntityInterface;
use VuFind\Db\Service\OaiResumptionService;
use VuFindTest\Container\MockContainer;

use function count;
use function intval;

/**
 * Oai resumption service test case.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OaiResumptionServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock container
     *
     * @var MockContainer
     */
    protected MockContainer $container;

    /**
     * Setup test environment. Always call parent method here.
     *
     * @return void
     */
    public function setup(): void
    {
        $this->container = new MockContainer($this);
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
        $row = $this->container->createMock(\VuFind\Db\Row\OaiResumption::class, ['save', 'getToken', 'setToken']);
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
        $oaiResumptionService = $this->container->createMock(
            OaiResumptionService::class,
            ['createRandomToken', 'createEntity']
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
        $mockRow = $this->container->createMock(OaiResumptionEntityInterface::class, []);
        $mockDb = [];
        foreach ($this->mockEntities as $entity) {
            $rowClone = clone $mockRow;
            $rowClone->expects($this->any())->method('getId')->willReturn($entity['id']);
            $rowClone->setExpiry(\DateTime::createFromFormat('U', $entity['expires']));
            $rowClone->expects($this->any())->method('getResumptionParameters')->willReturn($entity['params']);
            if ($entity['token']) {
                $rowClone->expects($this->any())->method('getToken')->willReturn($entity['token']);
            }
            $mockDb[] = $rowClone;
        }
        $mockTable = $this->container->createMock(\VuFind\Db\Table\OaiResumption::class, ['select']);

        $mockTable->expects($this->any())->method('select')->willReturnCallback(function ($select) use ($mockDb) {
            $result = [];
            foreach ($mockDb as $entry) {
                if (!empty($select['id'])) {
                    if ($entry->getId() === intval($select['id']) && $entry->getToken() === $select['token']) {
                        $result[] = $entry;
                    }
                    continue;
                }
                if (!empty($select['token'])) {
                    if ($entry->getToken() === $select['token']) {
                        $result[] = $entry;
                    }
                }
            }
            $mockResultSet = $this->container->createMock(AbstractResultSet::class, ['current']);
            $mockResultSet->expects($this->any())->method('current')->willReturn($result[0] ?? null);
            return $mockResultSet;
        });
        $oaiResumptionService = $this->container->createMock(
            OaiResumptionService::class,
            ['getDbTable']
        );
        $oaiResumptionService->expects($this->any())->method('getDbTable')->willReturn($mockTable);
        $token = $oaiResumptionService->findWithTokenOrLegacyIdToken($token);
        $this->assertEquals($expectedParams, $expectedParams ? $token->getResumptionParameters() : null);
    }
}
