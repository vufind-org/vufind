<?php
/**
 * Solr Writer Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Solr;
use VuFind\Db\Table\ChangeTracker, VuFind\Search\BackendManager;
use VuFind\Solr\Writer;

/**
 * Solr Utils Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class WriterTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test commit
     *
     * @return void
     */
    public function testCommit()
    {
        $bm = $this->getBackendManagerWithMockSolr();
        $connector = $bm->get('Solr')->getConnector();
        $connector->expects($this->at(0))->method('setTimeout')->with($this->equalTo(60 * 60));
        $connector->expects($this->once())->method('write')->with($this->isInstanceOf('VuFindSearch\Backend\Solr\Document\CommitDocument'));
        $connector->expects($this->at(2))->method('setTimeout')->with($this->equalTo(30));
        $writer = new Writer($bm, $this->getMockChangeTracker());
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
        $connector->expects($this->once())->method('write')->with($this->equalTo($commit));
        $writer = new Writer($bm, $this->getMockChangeTracker());
        $writer->save('Solr', $commit);
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
        $connector->expects($this->at(0))->method('setTimeout')->with($this->equalTo(60 * 60 * 24));
        $connector->expects($this->once())->method('write')->with($this->isInstanceOf('VuFindSearch\Backend\Solr\Document\OptimizeDocument'));
        $connector->expects($this->at(2))->method('setTimeout')->with($this->equalTo(30));
        $writer = new Writer($bm, $this->getMockChangeTracker());
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
        $callback = function ($i) {
            return trim($i->asXML()) == "<?xml version=\"1.0\"?>\n<delete><query>*:*</query></delete>";
        };
        $connector->expects($this->once())->method('write')->with($this->callback($callback));
        $writer = new Writer($bm, $this->getMockChangeTracker());
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
        $callback = function ($i) {
            return trim($i->asXML()) == "<?xml version=\"1.0\"?>\n<delete><id>foo</id><id>bar</id></delete>";
        };
        $connector->expects($this->once())->method('write')->with($this->callback($callback));
        $ct = $this->getMockChangeTracker();
        $ct->expects($this->at(0))->method('markDeleted')->with($this->equalTo('biblio'), $this->equalTo('foo'));
        $ct->expects($this->at(1))->method('markDeleted')->with($this->equalTo('biblio'), $this->equalTo('bar'));
        $writer = new Writer($bm, $ct);
        $writer->deleteRecords('Solr', ['foo', 'bar']);
    }

    /**
     * Get mock backend manager
     *
     * @return BackendManager
     */
    protected function getBackendManagerWithMockSolr()
    {
        $sm = new \Zend\ServiceManager\ServiceManager();
        $pm = new BackendManager($sm);
        $mockBackend = $this->getMockBuilder('VuFindSearch\Backend\Solr\Backend')
            ->disableOriginalConstructor()
            ->getMock();
        $mockConnector = $this->getMockBuilder('VuFindSearch\Backend\Solr\Connector')
            ->disableOriginalConstructor()
            ->setMethods(['getUrl', 'setTimeout', 'write'])
            ->getMock();
        $mockBackend->expects($this->any())->method('getConnector')->will($this->returnValue($mockConnector));
        $mockConnector->expects($this->any())->method('getTimeout')->will($this->returnValue(30));
        $mockConnector->expects($this->any())->method('getUrl')->will($this->returnValue('http://localhost:8080/solr/biblio'));
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
        return $this->getMockBuilder('VuFind\Db\Table\ChangeTracker')
            ->disableOriginalConstructor()
            ->setMethods(['markDeleted'])
            ->getMock();
    }
}