<?php

/**
 * Unit tests for WriteDocumentCommand.
 *
 * PHP version 7
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Backend\Solr\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Command\WriteDocumentCommand;
use VuFindSearch\Backend\Solr\Document\CommitDocument;

/**
 * Unit tests for WriteDocumentCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WriteDocumentCommandTest extends TestCase
{
    /**
     * Test that an error is thrown for unsupported backends.
     *
     * @return void
     */
    public function testUnsupportedBackend(): void
    {
        $command = new WriteDocumentCommand('foo', new CommitDocument());
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\BrowZine\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('foo'));
        $this->expectExceptionMessage('Connector not found');
        $command->execute($backend);
    }

    /**
     * Test that a supported backend behaves as expected.
     *
     * @return void
     */
    public function testSupportedBackend(): void
    {
        $doc = new CommitDocument();
        $connector = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->disableOriginalConstructor()->getMock();
        $connector->expects($this->once())->method('write')
            ->with(
                $this->equalTo($doc),
                $this->equalTo('update'),
                $this->equalTo(new \VuFindSearch\ParamBag([]))
            );
        $connector->expects($this->once())->method('getUrl')
            ->will($this->returnValue('http://localhost:8983/solr/core/biblio'));
        $connector->expects($this->once())->method('getTimeout')
            ->will($this->returnValue(30));
        $connector->expects($this->exactly(2))->method('setTimeout')
            ->withConsecutive([60], [30]);
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('Solr'));
        $backend->expects($this->once())->method('getConnector')
            ->will($this->returnValue($connector));
        $command = new WriteDocumentCommand('Solr', $doc, 60);
        $this->assertEquals(
            ['core' => 'biblio'],
            $command->execute($backend)->getResult()
        );
    }
}
