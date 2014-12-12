<?php
/**
 * Record view helper Test Class
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
namespace VuFindTest\View\Helper\Root;
use VuFind\View\Helper\Root\Record, Zend\View\Exception\RuntimeException;

/**
 * Record view helper Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class RecordTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test attempting to display a template that does not exist.
     *
     * @return void
     * @expectedException Zend\View\Exception\RuntimeException
     * @expectedExceptionMessage Cannot find core.phtml template for record driver: VuFind\RecordDriver\SolrMarc
     */
    public function testMissingTemplate()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->expects($this->any())->method('render')
            ->will($this->throwException(new RuntimeException('boom')));
        $record->getCoreMetadata();
    }

    /**
     * Test template inheritance.
     *
     * @return void
     */
    public function testTemplateInheritance()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/collection-record.phtml'))
            ->will($this->throwException(new RuntimeException('boom')));
        $record->getView()->expects($this->at(1))->method('render')
            ->with($this->equalTo('RecordDriver/SolrDefault/collection-record.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getCollectionBriefRecord());
    }

    /**
     * Test getExport.
     *
     * @return void
     */
    public function testGetExport()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/export-foo.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getExport('foo'));
    }

    /**
     * Test getFormatClass.
     *
     * @return void
     */
    public function testGetFormatClass()
    {
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo(array('format' => 'foo')))
            ->will($this->returnValue(array('bar' => 'baz')));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(array('bar' => 'baz')));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'), array(), $context
        );
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/format-class.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getFormatClass('foo'));
    }

    /**
     * Test getFormatList.
     *
     * @return void
     */
    public function testGetFormatList()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/format-list.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getFormatList());
    }

    /**
     * Test getToolbar.
     *
     * @return void
     */
    public function testGetToolbar()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/toolbar.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getToolbar());
    }

    /**
     * Test getSearchResult.
     *
     * @return void
     */
    public function testGetSearchResult()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/result-foo.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getSearchResult('foo'));
    }

    /**
     * Test getListEntry.
     *
     * @return void
     */
    public function testGetListEntry()
    {
        $driver = $this->getMock('VuFind\RecordDriver\AbstractBase');
        $driver->expects($this->once())->method('getContainingLists')
            ->with($this->equalTo(42))
            ->will($this->returnValue(array(1, 2, 3)));
        $user = new \StdClass;
        $user->id = 42;
        $expected = array(
            'driver' => $driver, 'list' => null, 'user' => $user, 'lists' => array(1, 2, 3)
        );
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo($expected))
            ->will($this->returnValue(array('bar' => 'baz')));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(array('bar' => 'baz')));
        $record = $this->getRecord($driver, array(), $context);
        $record->getView()->expects($this->at(0))->method('render')
            ->will($this->throwException(new RuntimeException('boom')));
        $record->getView()->expects($this->at(1))->method('render')
            ->with($this->equalTo('RecordDriver/AbstractBase/list-entry.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getListEntry(null, $user));
    }

    /**
     * Test getPreviewIds.
     *
     * @return void
     */
    public function testGetPreviewIds()
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            array(
                'CleanISBN' => '0123456789',
                'LCCN' => '12345',
                'OCLC' => array('1', '2'),
            )
        );
        $record = $this->getRecord($driver);
        $this->assertEquals(
            array('ISBN0123456789', 'LCCN12345', 'OCLC1', 'OCLC2'),
            $record->getPreviewIds()
        );
    }

    /**
     * Test getController.
     *
     * @return void
     */
    public function testGetController()
    {
        // Default (Solr) case:
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $record = $this->getRecord($driver);
        $this->assertEquals('Record', $record->getController());

        // Custom source case:
        $driver->setSourceIdentifier('Foo');
        $this->assertEquals('Foorecord', $record->getController());
    }

    /**
     * Test getPreviews.
     *
     * @return void
     */
    public function testGetPreviews()
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $config = new \Zend\Config\Config(array('foo' => 'bar'));
        $context = $this->getMockContext();
        $context->expects($this->exactly(2))->method('apply')
            ->with($this->equalTo(compact('driver', 'config')))
            ->will($this->returnValue(array('bar' => 'baz')));
        $context->expects($this->exactly(2))->method('restore')
            ->with($this->equalTo(array('bar' => 'baz')));
        $record = $this->getRecord($driver, $config, $context);
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/previewdata.phtml'))
            ->will($this->returnValue('success1'));
        $record->getView()->expects($this->at(1))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/previewlink.phtml'))
            ->will($this->returnValue('success2'));
        $this->assertEquals('success1success2', $record->getPreviews());
    }

    /**
     * Test getLink.
     *
     * @return void
     */
    public function testGetLink()
    {
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo(array('lookfor' => 'foo')))
            ->will($this->returnValue(array('bar' => 'baz')));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(array('bar' => 'baz')));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'), array(), $context
        );
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/link-bar.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getLink('bar', 'foo'));
    }

    /**
     * Test getCheckbox.
     *
     * @return void
     */
    public function testGetCheckbox()
    {
        $context = $this->getMockContext();
        $context->expects($this->at(1))->method('renderInContext')
            ->with($this->equalTo('record/checkbox.phtml'), $this->equalTo(array('id' => 'VuFind|000105196', 'count' => 0, 'prefix' => 'bar')))
            ->will($this->returnValue('success'));
        $context->expects($this->at(2))->method('renderInContext')
            ->with($this->equalTo('record/checkbox.phtml'), $this->equalTo(array('id' => 'VuFind|000105196', 'count' => 1, 'prefix' => 'bar')))
            ->will($this->returnValue('success'));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'), array(), $context
        );
        // We run the test twice to ensure that checkbox incrementing works properly:
        $this->assertEquals('success', $record->getCheckbox('bar', 'foo'));
        $this->assertEquals('success', $record->getCheckbox('bar', 'foo'));
    }

    /**
     * Test getTab.
     *
     * @return void
     */
    public function testGetTab()
    {
        $tab = new \VuFind\RecordTab\Description();
        $driver = $this->loadRecordFixture('testbug1.json');
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo(compact('driver', 'tab')))
            ->will($this->returnValue(array('bar' => 'baz')));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(array('bar' => 'baz')));
        $record = $this->getRecord($driver, array(), $context);
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordTab/description.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getTab($tab));
    }

    /**
     * Get a Record object ready for testing.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver  Record driver
     * @param array|Config                      $config  Configuration
     * @param \VuFind\View\Helper\Root\Context  $context Context helper
     *
     * @return Record
     */
    protected function getRecord($driver, $config = array(), $context = null)
    {
        if (null === $context) {
            $context = $this->getMockContext();
        }
        $view = $this->getMock('Zend\View\Renderer\PhpRenderer');
        $view->expects($this->at(0))->method('plugin')
            ->with($this->equalTo('context'))
            ->will($this->returnValue($context));
        $config = is_array($config) ? new \Zend\Config\Config($config) : $config;
        $record = new Record($config);
        $record->setView($view);
        return $record->__invoke($driver);
    }

    /**
     * Get a mock context object
     *
     * @return \VuFind\View\Helper\Root\Context
     */
    protected function getMockContext()
    {
        $context = $this->getMock('VuFind\View\Helper\Root\Context');
        $context->expects($this->any())->method('__invoke')
            ->will($this->returnValue($context));
        return $context;
    }

    /**
     * Load a fixture file.
     *
     * @param string $file File to load from fixture directory.
     *
     * @return array
     */
    protected function loadRecordFixture($file)
    {
        $json = json_decode(
            file_get_contents(
                realpath(
                    VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/misc/' . $file
                )
            ),
            true
        );
        $record = new \VuFind\RecordDriver\SolrMarc();
        $record->setRawData($json['response']['docs'][0]);
        return $record;
    }
}