<?php

/**
 * Unit tests for the MCP Solr search capability.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mcp\Capabilities;

use Exception;
use Generator;
use Mcp\Exception\InvalidArgumentException;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ResourceReadException;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VuFind\Config\YamlReader;
use VuFind\Exception\RecordMissing;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\Record\Loader;
use VuFind\Search\Base\Results as BaseResults;
use VuFind\Search\EmptySet\Results as EmptySetResults;
use VuFind\Search\SearchRunner;
use VuFindApi\Formatter\RecordFormatter;
use VuFindApi\Mcp\Capabilities\SearchSolr;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Unit tests for the MCP Solr search capability.
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchSolrTest extends TestCase
{
    use ReflectionTrait;

    /**
     * Build a SearchSolr instance. Any collaborator left null gets a bare mock with sensible defaults;
     * pass a pre-configured mock for any collaborator a test needs to set expectations on.
     *
     * @param array            $config          ModelContextProtocol.yaml contents
     * @param ?SearchRunner    $searchRunner    Search runner
     * @param ?RecordFormatter $recordFormatter Record formatter
     * @param ?Loader          $recordLoader    Record loader
     * @param ?RouteHelper     $routeHelper     Route helper
     * @param ?ServerUrlHelper $serverUrlHelper Server URL helper
     *
     * @return SearchSolr
     */
    protected function getSearchSolr(
        array $config = [],
        ?SearchRunner $searchRunner = null,
        ?RecordFormatter $recordFormatter = null,
        ?Loader $recordLoader = null,
        ?RouteHelper $routeHelper = null,
        ?ServerUrlHelper $serverUrlHelper = null
    ): SearchSolr {
        $yamlReader = $this->createMock(YamlReader::class);
        $yamlReader->method('get')->willReturn($config);

        if (!$routeHelper) {
            $routeHelper = $this->createMock(RouteHelper::class);
            $routeHelper->method('getUrlFromRoute')->willReturn('/Search/Results');
        }
        if (!$serverUrlHelper) {
            $serverUrlHelper = $this->createMock(ServerUrlHelper::class);
            $serverUrlHelper->method('getBaseUrl')->willReturn('https://library.example.org');
        }

        return new SearchSolr(
            $yamlReader,
            $recordLoader ?? $this->createMock(Loader::class),
            $recordFormatter ?? $this->createMock(RecordFormatter::class),
            $searchRunner ?? $this->createMock(SearchRunner::class),
            $routeHelper,
            $serverUrlHelper,
        );
    }

    /**
     * Test that searchRecords() with no content type runs a plain keyword search and shapes the
     * response as search_results/search_results_page.
     *
     * @return void
     */
    public function testSearchRecordsWithNoContentType(): void
    {
        $results = $this->createMock(BaseResults::class);
        $results->method('getResults')->willReturn(['recordA']);

        $searchRunner = $this->createMock(SearchRunner::class);
        $searchRunner->expects($this->once())
            ->method('run')
            ->with(['lookfor' => 'test keywords'], 'Solr', $this->isType('callable'))
            ->willReturn($results);

        $recordFormatter = $this->createMock(RecordFormatter::class);
        $recordFormatter->expects($this->once())
            ->method('format')
            ->with(['recordA'], ['recordPageAbsoluteLink', 'title', 'authors'])
            ->willReturn([['id' => 'recordA', 'title' => 'A Title']]);

        $routeHelper = $this->createMock(RouteHelper::class);
        $routeHelper->method('getUrlFromRoute')->willReturn('/Search/Results?lookfor=test+keywords');

        $searchSolr = $this->getSearchSolr([], $searchRunner, $recordFormatter, null, $routeHelper);

        $this->assertSame(
            [
                'search_results' => [['id' => 'recordA', 'title' => 'A Title']],
                'search_results_page' => 'https://library.example.org/Search/Results?lookfor=test+keywords',
            ],
            $searchSolr->searchRecords('test keywords')
        );
    }

    /**
     * Test that searchRecords() merges a configured content type's filter into the raw search
     * request.
     *
     * @return void
     */
    public function testSearchRecordsWithValidContentTypeAddsConfiguredFilter(): void
    {
        $config = ['ContentTypes' => ['books' => ['filter' => '(format:Book)']]];
        $results = $this->createMock(BaseResults::class);
        $results->method('getResults')->willReturn([]);

        $searchRunner = $this->createMock(SearchRunner::class);
        $searchRunner->expects($this->once())
            ->method('run')
            ->with(['lookfor' => 'keywords', 'filter' => '(format:Book)'], 'Solr', $this->isType('callable'))
            ->willReturn($results);

        $searchSolr = $this->getSearchSolr($config, $searchRunner);
        $searchSolr->searchRecords('keywords', 'books');
    }

    /**
     * Test that searchRecords() rejects a content type not defined in config, without ever running a
     * search.
     *
     * @return void
     */
    public function testSearchRecordsWithUnknownContentTypeThrowsException(): void
    {
        $searchRunner = $this->createMock(SearchRunner::class);
        $searchRunner->expects($this->never())->method('run');

        $searchSolr = $this->getSearchSolr(
            ['ContentTypes' => ['books' => ['filter' => '(format:Book)']]],
            $searchRunner
        );

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Unknown content type: videos');
        $searchSolr->searchRecords('keywords', 'videos');
    }

    /**
     * Test that searchRecords() reports an unparseable query (the only case SearchRunner substitutes
     * an EmptySet\Results for) as a ToolCallException with an accurate message -- not as though the
     * query had simply matched nothing, which is a normal, non-error outcome.
     *
     * @return void
     */
    public function testSearchRecordsWithUnparseableQueryThrowsException(): void
    {
        $searchRunner = $this->createMock(SearchRunner::class);
        $searchRunner->method('run')->willReturn($this->createMock(EmptySetResults::class));

        $searchSolr = $this->getSearchSolr([], $searchRunner);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage(
            'Search could not be performed; check for invalid search syntax in the keywords.'
        );
        $searchSolr->searchRecords('keywords');
    }

    /**
     * Test that getRecord() rejects an empty record ID before ever touching the record loader.
     *
     * @return void
     */
    public function testGetRecordWithEmptyIdThrowsException(): void
    {
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->expects($this->never())->method('load');

        $searchSolr = $this->getSearchSolr([], null, null, $recordLoader);

        $this->expectException(InvalidArgumentException::class);
        $searchSolr->getRecord('');
    }

    /**
     * Test that getRecord() reports a genuinely missing record (Loader::load() throws RecordMissing)
     * as a ResourceNotFoundException -- a "not found" error, not a generic read failure.
     *
     * @return void
     */
    public function testGetRecordThrowsResourceNotFoundExceptionWhenRecordDoesNotExist(): void
    {
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->method('load')->willThrowException(new RecordMissing('Record Solr:missing123 does not exist.'));

        $searchSolr = $this->getSearchSolr([], null, null, $recordLoader);

        $this->expectException(ResourceNotFoundException::class);
        $searchSolr->getRecord('missing123');
    }

    /**
     * Test that getRecord() wraps any other Loader failure (e.g. a backend connectivity problem) as a
     * ResourceReadException with the original exception preserved -- distinct from a record simply not
     * existing.
     *
     * @return void
     */
    public function testGetRecordWrapsOtherLoaderExceptionAsResourceReadException(): void
    {
        $loaderException = new Exception('backend unavailable');
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->method('load')->willThrowException($loaderException);

        $searchSolr = $this->getSearchSolr([], null, null, $recordLoader);

        try {
            $searchSolr->getRecord('missing123');
            $this->fail('Expected ResourceReadException was not thrown.');
        } catch (ResourceReadException $e) {
            $this->assertSame('Failed to load record for ID: missing123', $e->getMessage());
            $this->assertSame($loaderException, $e->getPrevious());
        }
    }

    /**
     * Test that getRecord() looks up the record by ID and search class, and returns the single
     * formatted record.
     *
     * @return void
     */
    public function testGetRecordReturnsFormattedRecord(): void
    {
        $record = new \stdClass();
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->expects($this->once())->method('load')->with('rec1', 'Solr')->willReturn($record);

        $recordFormatter = $this->createMock(RecordFormatter::class);
        $recordFormatter->expects($this->once())
            ->method('format')
            ->with([$record], ['recordPageAbsoluteLink', 'title', 'authors'])
            ->willReturn([['id' => 'rec1', 'title' => 'A Book']]);

        $searchSolr = $this->getSearchSolr([], null, $recordFormatter, $recordLoader);
        $this->assertSame(['id' => 'rec1', 'title' => 'A Book'], $searchSolr->getRecord('rec1'));
    }

    /**
     * Data provider for testConstructorSetsResponseFieldsFromConfigOrDefault().
     *
     * @return Generator
     */
    public static function getResponseFieldsData(): Generator
    {
        yield 'no config -> hardcoded default' => [
            [],
            ['recordPageAbsoluteLink', 'title', 'authors'],
        ];
        yield 'configured -> uses configured value' => [
            ['ResponseFields' => ['title', 'formats']],
            ['title', 'formats'],
        ];
    }

    /**
     * Test that the constructor sets responseFields from config when present, and falls back to the
     * hardcoded default otherwise.
     *
     * @param array $config   ModelContextProtocol.yaml contents
     * @param array $expected Expected value of the responseFields property afterward
     *
     * @return void
     */
    #[DataProvider('getResponseFieldsData')]
    public function testConstructorSetsResponseFieldsFromConfigOrDefault(array $config, array $expected): void
    {
        $searchSolr = $this->getSearchSolr($config);
        $this->assertSame($expected, $this->getProperty($searchSolr, 'responseFields'));
    }
}
