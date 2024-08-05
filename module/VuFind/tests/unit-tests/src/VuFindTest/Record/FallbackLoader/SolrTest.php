<?php

/**
 * Solr fallback loader test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021, 2022.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Record\FallbackLoader;

use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\FallbackLoader\Solr;
use VuFind\Record\RecordIdUpdater;

/**
 * Solr fallback loader test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the fallback loader works when enabled (default settings).
     *
     * @return void
     */
    public function testEnabledLoader(): void
    {
        $record = $this->createMock(\VuFind\RecordDriver\SolrDefault::class);
        $record->expects($this->once())->method('setPreviousUniqueId')
            ->with($this->equalTo('oldId'));
        $record->expects($this->once())->method('getUniqueId')->willReturn('newId');
        $collection = new \VuFindSearch\Backend\Solr\Response\Json\RecordCollection(
            ['recordCount' => 1]
        );
        $collection->add($record);
        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')->willReturn($collection);
        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Command\SearchCommand::class
                && $command->getTargetIdentifier() === 'Solr'
                && $command->getArguments()[0]->getString() ===
                'previous_id_str_mv:"oldId"';
        };
        $search = $this->createMock(\VuFindSearch\Service::class);
        $search->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->willReturn($commandObj);
        $updater = $this->createMock(RecordIdUpdater::class);
        $updater->expects($this->once())->method('updateRecordId')
            ->with(
                $this->equalTo('oldId'),
                $this->equalTo('newId'),
                $this->equalTo('Solr')
            );
        $loader = new Solr($this->createMock(ResourceServiceInterface::class), $updater, $search);
        $this->assertEquals([$record], $loader->load(['oldId']));
    }

    /**
     * Test that the fallback loader can be disabled.
     *
     * @return void
     */
    public function testDisabledLoader(): void
    {
        $search = $this->createMock(\VuFindSearch\Service::class);
        $updater = $this->createMock(RecordIdUpdater::class);
        $loader = new Solr($this->createMock(ResourceServiceInterface::class), $updater, $search, null);
        $this->assertCount(0, $loader->load(['oldId']));
    }
}
