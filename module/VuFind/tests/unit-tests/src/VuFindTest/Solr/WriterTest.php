<?php

/**
 * Solr Writer Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindTest\Solr;

use VuFind\Db\Table\ChangeTracker;
use VuFind\Solr\Writer;
use VuFindSearch\Backend\Solr\Command\WriteDocumentCommand;
use VuFindSearch\Backend\Solr\Document\CommitDocument;
use VuFindSearch\Backend\Solr\Document\DeleteDocument;
use VuFindSearch\Backend\Solr\Document\OptimizeDocument;

/**
 * Solr Utils Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class WriterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test commit
     *
     * @return void
     */
    public function testCommit()
    {
        $expectedCommand
            = new WriteDocumentCommand('Solr', new CommitDocument(), 60 * 60);
        $this->getWriter($expectedCommand)->commit('Solr');
    }

    /**
     * Test save
     *
     * @return void
     */
    public function testSave()
    {
        $commit = new CommitDocument();
        $expectedCommand = new WriteDocumentCommand('Solr', $commit);
        $this->getWriter($expectedCommand)->save('Solr', $commit);
    }

    /**
     * Test save with non-default parameters
     *
     * @return void
     */
    public function testSaveWithNonDefaults()
    {
        $csv = new \VuFindSearch\Backend\Solr\Document\RawCSVDocument('a,b,c');
        $params = new \VuFindSearch\ParamBag(['foo' => 'bar']);
        $expectedCommand = new WriteDocumentCommand(
            'Solr',
            $csv,
            null,
            'customUpdateHandler',
            $params
        );
        $this->getWriter($expectedCommand)
            ->save('Solr', $csv, 'customUpdateHandler', $params);
    }

    /**
     * Test optimize
     *
     * @return void
     */
    public function testOptimize()
    {
        $expectedCommand
            = new WriteDocumentCommand('Solr', new OptimizeDocument(), 60 * 60 * 24);
        $this->getWriter($expectedCommand)->optimize('Solr');
    }

    /**
     * Test delete all
     *
     * @return void
     */
    public function testDeleteAll()
    {
        $deleteDoc = new DeleteDocument();
        $deleteDoc->addQuery('*:*');
        $expectedCommand = new WriteDocumentCommand('Solr', $deleteDoc);
        $this->getWriter($expectedCommand)->deleteAll('Solr');
    }

    /**
     * Test delete records
     *
     * @return void
     */
    public function testDeleteRecords()
    {
        $deleteDoc = new DeleteDocument();
        $deleteDoc->addKeys(['foo', 'bar']);
        $expectedCommand = new WriteDocumentCommand('Solr', $deleteDoc);
        $this->getWriter($expectedCommand, ['core' => 'biblio'])
            ->deleteRecords('Solr', ['foo', 'bar']);
    }

    /**
     * Get mock change tracker
     *
     * @return ChangeTracker
     */
    protected function getMockChangeTracker()
    {
        return $this->getMockBuilder(\VuFind\Db\Table\ChangeTracker::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Create a mock search service for a single command and its result
     *
     * @param object $expectedCommand Expected command class
     * @param mixed  $result          Result to return for the invoked command
     *
     * @return MockObject&SearchService
     */
    protected function getMockSearchService($expectedCommand, $result)
    {
        $resultCommand = $this->getMockBuilder(get_class($expectedCommand))
            ->disableOriginalConstructor()
            ->getMock();
        $resultCommand->expects($this->once())->method('getResult')
            ->willReturn($result);

        $searchService = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->getMock();
        $searchService->expects($this->once())
            ->method('invoke')
            ->with($expectedCommand)
            ->will($this->returnValue($resultCommand));
        return $searchService;
    }

    /**
     * Create a Writer for a single command and its result
     *
     * @param object $expectedCommand Expected command class
     * @param mixed  $result          Result to return for the invoked command
     *
     * @return Writer
     */
    protected function getWriter($expectedCommand, $result = 'TEST')
    {
        return new Writer(
            $this->getMockSearchService($expectedCommand, $result),
            $this->getMockChangeTracker()
        );
    }
}
