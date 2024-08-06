<?php

/**
 * GetIdsWithTermsCommand Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Sitemap\Command;

use VuFind\Sitemap\Plugin\Index\TermsIdFetcher;
use VuFindSearch\Backend\Solr\Response\Json\Terms;
use VuFindSearch\Command\GetUniqueKeyCommand;
use VuFindSearch\Command\TermsCommand;
use VuFindSearch\Service;

use function array_slice;

/**
 * GetIdsWithTermsCommand Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TermsIdFetcherTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Unique key field to use in tests
     *
     * @var string
     */
    protected $uniqueKey = 'id';

    /**
     * Page size to use in tests
     *
     * @var int
     */
    protected $countPerPage = 100;

    /**
     * Get a terms response
     *
     * @param int[] $expectedIds IDs to return in response
     *
     * @return Terms
     */
    protected function getTermsResponse(array $expectedIds): Terms
    {
        $ids = [];
        foreach ($expectedIds as $id) {
            $ids[] = [$id, $id];
        }
        return new Terms(
            [
                'terms' => [
                    $this->uniqueKey => $ids,
                ],
            ]
        );
    }

    /**
     * Get a function to test that a TermsCommand is as expected.
     *
     * @param string $expectedCursorMark Expected cursor mark
     *
     * @return callable
     */
    protected function getIdsExpectation(string $expectedCursorMark)
    {
        return function ($command) use ($expectedCursorMark) {
            $this->assertEquals(
                [$this->uniqueKey, $expectedCursorMark, $this->countPerPage],
                array_slice($command->getArguments(), 0, 3)
            );
            $this->assertInstanceOf(TermsCommand::class, $command);
            return true;
        };
    }

    /**
     * Get mock search service
     *
     * @return Service
     */
    protected function getMockService(): Service
    {
        return $this->getMockBuilder(Service::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock "TermsCommand" to use as a container for a test value.
     *
     * @param Terms $terms Terms response
     *
     * @return TermsCommand
     */
    protected function getMockTermsCommand(Terms $terms): TermsCommand
    {
        $command = $this->getMockBuilder(TermsCommand::class)
            ->disableOriginalConstructor()->getMock();
        $command->expects($this->once())->method('getResult')
            ->will($this->returnValue($terms));
        return $command;
    }

    /**
     * Get a mock "GetUniqueKeyCommand" for testing purposes.
     *
     * @return GetUniqueKeyCommand
     */
    protected function getMockKeyCommand(): GetUniqueKeyCommand
    {
        $command = $this->getMockBuilder(GetUniqueKeyCommand::class)
            ->disableOriginalConstructor()->getMock();
        $command->expects($this->once())->method('getResult')
            ->will($this->returnValue($this->uniqueKey));
        return $command;
    }

    /**
     * Test that filters are unsupported.
     *
     * @return void
     */
    public function testFilters(): void
    {
        $this->expectExceptionMessage('extraFilters[] option incompatible with terms');
        $fetcher = new TermsIdFetcher($this->getMockService());
        $fetcher->getIdsFromBackend(
            'foo',
            0,
            $this->countPerPage,
            ['format:Book']
        );
    }

    /**
     * Test the terms retrieval process.
     *
     * @return void
     */
    public function testFetching(): void
    {
        $expectedIds1 = range(0, $this->countPerPage - 1);
        $expectedResponse1 = $this->getTermsResponse($expectedIds1);
        $expectedIds2 = [];
        $expectedResponse2 = $this->getTermsResponse($expectedIds2);
        $service = $this->getMockService();

        // Set up all the expected commands...
        $this->expectConsecutiveCalls(
            $service,
            'invoke',
            [
                [$this->isInstanceOf(GetUniqueKeyCommand::class)],
                [$this->callback($this->getIdsExpectation(''))],
                [$this->isInstanceOf(GetUniqueKeyCommand::class)],
                [$this->callback($this->getIdsExpectation('99'))],
            ],
            [
                $this->getMockKeyCommand(),
                $this->getMockTermsCommand($expectedResponse1),
                $this->getMockKeyCommand(),
                $this->getMockTermsCommand($expectedResponse2),
            ]
        );
        $fetcher = new TermsIdFetcher($service);
        $this->assertEquals(
            ['ids' => $expectedIds1, 'nextOffset' => 99],
            $fetcher->getIdsFromBackend(
                'foo',
                $fetcher->getInitialOffset(),
                $this->countPerPage,
                []
            )
        );
        $this->assertEquals(
            ['ids' => $expectedIds2],
            $fetcher->getIdsFromBackend(
                'foo',
                99,
                $this->countPerPage,
                []
            )
        );
    }
}
