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
     * Get a Record object ready for testing.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver  Record driver
     * @param array                             $config  Configuration
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
        $record = new Record(new \Zend\Config\Config($config));
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