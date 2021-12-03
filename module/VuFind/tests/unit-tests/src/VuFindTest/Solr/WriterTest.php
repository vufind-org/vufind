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

use Laminas\Http\Client as HttpClient;
use VuFind\Db\Table\ChangeTracker;
use VuFind\Search\BackendManager;
use VuFind\Solr\Writer;
use VuFindSearch\Backend\Solr\HandlerMap;

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
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)
            ->onlyMethods(['setOptions'])
            ->getMock();
        $client->expects($this->exactly(1))->method('setOptions')
            ->with(['timeout' => 60 * 60]);
        $bm = $this->getBackendManagerWithMockSolr($client);
        $connector = $bm->get('Solr')->getConnector();
        $connector->expects($this->once())->method('write')->with($this->isInstanceOf(\VuFindSearch\Backend\Solr\Document\CommitDocument::class));
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
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)
            ->onlyMethods(['setOptions'])
            ->getMock();
        $client->expects($this->exactly(1))->method('setOptions')
            ->with(['timeout' => 60 * 60 * 24]);
        $bm = $this->getBackendManagerWithMockSolr($client);
        $connector = $bm->get('Solr')->getConnector();
        $connector->expects($this->once())->method('write')->with($this->isInstanceOf(\VuFindSearch\Backend\Solr\Document\OptimizeDocument::class));
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
     * @param HttpClient $client HTTP Client (optional)
     *
     * @return BackendManager
     */
    protected function getBackendManagerWithMockSolr($client = null)
    {
        $sm = new \Laminas\ServiceManager\ServiceManager();
        $pm = new BackendManager($sm);
        $mockBackend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnector', 'getIdentifier'])
            ->getMock();
        $handlerMap = new HandlerMap();
        $client = $client ?? new HttpClient();
        $mockConnector = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->setConstructorArgs(['http://localhost:8983/solr/biblio', $handlerMap, $client])
            ->onlyMethods(['write'])
            ->getMock();
        $mockBackend->expects($this->any())->method('getConnector')->will($this->returnValue($mockConnector));
        $mockBackend->expects($this->any())->method('getIdentifier')->will($this->returnValue('Solr'));
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
