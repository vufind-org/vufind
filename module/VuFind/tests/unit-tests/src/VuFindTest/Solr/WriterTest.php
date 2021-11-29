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
use VuFind\Search\BackendManager;
use VuFind\Solr\Writer;

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
    use \VuFindTest\Feature\SearchServiceTrait;

    /**
     * Test commit
     *
     * @return void
     */
    public function testCommit()
    {
        $bm = $this->getBackendManagerWithMockSolr();
        $connector = $bm->get('Solr')->getConnector();
        $connector->expects($this->exactly(2))->method('setTimeout')
            ->withConsecutive([60 * 60], [30]);
        $connector->expects($this->once())->method('write')->with($this->isInstanceOf('VuFindSearch\Backend\Solr\Document\CommitDocument'));
        $writer = new Writer($this->getSearchService($bm), $this->getMockChangeTracker());
        $writer->commit('Solr');
    }

    /**
     * Test save
     *
     * @return void
     */
    public function testSave()
    {
        $bm = $this->getBackendManagerWithMockSolr();
        $commit = new \VuFindSearch\Backend\Solr\Document\CommitDocument();
        $connector = $bm->get('Solr')->getConnector();
        $connector->expects($this->once())->method('write')
            ->with(
                $this->equalTo($commit),
                $this->equalTo('update'),
                $this->callback(function ($params) {
                    return count($params) === 0;
                })
            );
        $writer = new Writer($this->getSearchService($bm), $this->getMockChangeTracker());
        $writer->save('Solr', $commit);
    }

    /**
     * Test save with non-default parameters
     *
     * @return void
     */
    public function testSaveWithNonDefaults()
    {
        $bm = $this->getBackendManagerWithMockSolr();
        $csv = new \VuFindSearch\Backend\Solr\Document\RawCSVDocument('a,b,c');
        $params = new \VuFindSearch\ParamBag(['foo' => 'bar']);
        $connector = $bm->get('Solr')->getConnector();
        $connector->expects($this->once())->method('write')
            ->with(
                $this->equalTo($csv),
                $this->equalTo('customUpdateHandler'),
                $this->equalTo($params)
            );
        $writer = new Writer($this->getSearchService($bm), $this->getMockChangeTracker());
        $writer->save('Solr', $csv, 'customUpdateHandler', $params);
    }

    /**
     * Test optimize
     *
     * @return void
     */
    public function testOptimize()
    {
        $bm = $this->getBackendManagerWithMockSolr();
        $connector = $bm->get('Solr')->getConnector();
        $connector->expects($this->exactly(2))->method('setTimeout')
            ->withConsecutive([60 * 60 * 24], [30]);
        $connector->expects($this->once())->method('write')->with($this->isInstanceOf('VuFindSearch\Backend\Solr\Document\OptimizeDocument'));
        $writer = new Writer($this->getSearchService($bm), $this->getMockChangeTracker());
        $writer->optimize('Solr');
    }

    /**
     * Test delete all
     *
     * @return void
     */
    public function testDeleteAll()
    {
        $bm = $this->getBackendManagerWithMockSolr();
        $connector = $bm->get('Solr')->getConnector();
        $callback = function ($i): bool {
            return trim($i->getContent()) == "<?xml version=\"1.0\"?>\n<delete><query>*:*</query></delete>";
        };
        $connector->expects($this->once())->method('write')->with($this->callback($callback));
        $writer = new Writer($this->getSearchService($bm), $this->getMockChangeTracker());
        $writer->deleteAll('Solr');
    }

    /**
     * Test delete records
     *
     * @return void
     */
    public function testDeleteRecords()
    {
        $bm = $this->getBackendManagerWithMockSolr();
        $connector = $bm->get('Solr')->getConnector();
        $callback = function ($i): bool {
            return trim($i->getContent()) == "<?xml version=\"1.0\"?>\n<delete><id>foo</id><id>bar</id></delete>";
        };
        $connector->expects($this->once())->method('write')->with($this->callback($callback));
        $ct = $this->getMockChangeTracker();
        $ct->expects($this->exactly(2))->method('markDeleted')
            ->withConsecutive(['biblio', 'foo'], ['biblio', 'bar']);
        $writer = new Writer($this->getSearchService($bm), $ct);
        $writer->deleteRecords('Solr', ['foo', 'bar']);
    }

    /**
     * Get mock backend manager
     *
     * @return BackendManager
     */
    protected function getBackendManagerWithMockSolr()
    {
        $sm = new \Laminas\ServiceManager\ServiceManager();
        $pm = new BackendManager($sm);
        $mockBackend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnector', 'getIdentifier'])
            ->getMock();
        $mockConnector = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUrl', 'getTimeout', 'setTimeout', 'write'])
            ->getMock();
        $mockBackend->expects($this->any())->method('getConnector')->will($this->returnValue($mockConnector));
        $mockBackend->expects($this->any())->method('getIdentifier')->will($this->returnValue('Solr'));
        $mockConnector->expects($this->any())->method('getTimeout')->will($this->returnValue(30));
        $mockConnector->expects($this->any())->method('getUrl')->will($this->returnValue('http://localhost:8983/solr/biblio'));
        $sm->setService('Solr', $mockBackend);
        return $pm;
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
            ->onlyMethods(['markDeleted'])
            ->getMock();
    }
}
