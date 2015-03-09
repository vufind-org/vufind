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
     *
     * @expectedException        Zend\View\Exception\RuntimeException
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
            ->with($this->equalTo(['format' => 'foo']))
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'), [], $context
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
     * Test getCollectionMetadata.
     *
     * @return void
     */
    public function testGetCollectionMetadata()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/collection-info.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getCollectionMetadata());
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
            ->will($this->returnValue([1, 2, 3]));
        $user = new \StdClass;
        $user->id = 42;
        $expected = [
            'driver' => $driver, 'list' => null, 'user' => $user, 'lists' => [1, 2, 3]
        ];
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo($expected))
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord($driver, [], $context);
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
            [
                'CleanISBN' => '0123456789',
                'LCCN' => '12345',
                'OCLC' => ['1', '2'],
            ]
        );
        $record = $this->getRecord($driver);
        $this->assertEquals(
            ['ISBN0123456789', 'LCCN12345', 'OCLC1', 'OCLC2'],
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
        $config = new \Zend\Config\Config(['foo' => 'bar']);
        $context = $this->getMockContext();
        $context->expects($this->exactly(2))->method('apply')
            ->with($this->equalTo(compact('driver', 'config')))
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->exactly(2))->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
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
            ->with($this->equalTo(['lookfor' => 'foo']))
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'), [], $context
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
            ->with($this->equalTo('record/checkbox.phtml'), $this->equalTo(['id' => 'VuFind|000105196', 'count' => 0, 'prefix' => 'bar']))
            ->will($this->returnValue('success'));
        $context->expects($this->at(2))->method('renderInContext')
            ->with($this->equalTo('record/checkbox.phtml'), $this->equalTo(['id' => 'VuFind|000105196', 'count' => 1, 'prefix' => 'bar']))
            ->will($this->returnValue('success'));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'), [], $context
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
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord($driver, [], $context);
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordTab/description.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('success', $record->getTab($tab));
    }

    /**
     * Test various ways of making getQrCode() fail.
     *
     * @return void
     */
    public function testGetQrCodeFailures()
    {
        // Disabled by default:
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $this->assertFalse($record->getQrCode('core'));

        // Disabled mode:
        $config = ['QRCode' => ['showInCore' => false]];
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'), $config);
        $this->assertFalse($record->getQrCode('core'));

        // Invalid mode:
        $this->assertFalse($record->getQrCode('bad-bad-bad'));
    }

    /**
     * Test successful getQrCode() call.
     *
     * @return void
     */
    public function testGetQrCodeSuccess()
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo(['driver' => $driver, 'extra' => 'xyzzy']))
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $config = ['QRCode' => ['showInCore' => true]];
        $record = $this->getRecord($driver, $config, $context, '2:qrcode-show');
        $record->getView()->expects($this->at(0))->method('render')
            ->with($this->equalTo('RecordDriver/SolrMarc/core-qrcode.phtml'))
            ->will($this->returnValue('success'));
        $this->assertEquals('http://foo/bar?text=success&level=L&size=3&margin=4', $record->getQrCode('core', ['extra' => 'xyzzy']));
    }

    /**
     * Test getThumbnail() - no thumbnail case
     *
     * @return void
     */
    public function testGetThumbnailNone()
    {
        // No thumbnail:
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(['Thumbnail' => false]);
        $record = $this->getRecord($driver);
        $this->assertFalse($record->getThumbnail());
    }

    /**
     * Test getThumbnail() - hardcoded thumbnail case
     *
     * @return void
     */
    public function testGetThumbnailHardCoded()
    {
        // Hard-coded thumbnail:
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(['Thumbnail' => 'http://foo/this.jpg']);
        $record = $this->getRecord($driver);
        $this->assertEquals('http://foo/this.jpg', $record->getThumbnail());
    }

    /**
     * Test getThumbnail() - dynamic thumbnail case
     *
     * @return void
     */
    public function testGetThumbnailDynamic()
    {
        // Hard-coded thumbnail:
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(['Thumbnail' => ['bar' => 'baz']]);
        $record = $this->getRecord($driver, [], null, '1:cover-show');
        $this->assertEquals('http://foo/bar?bar=baz', $record->getThumbnail());
    }

    /**
     * Test getLinkDetails with an empty list
     *
     * @return void
     */
    public function testGetLinkDetailsEmpty()
    {
        // Hard-coded thumbnail:
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $record = $this->getRecord($driver);
        $this->assertEquals([], $record->getLinkDetails());
    }

    /**
     * Test getLinkDetails with valid details
     *
     * @return void
     */
    public function testGetLinkDetailsSuccess()
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'URLs' => [
                    ['route' => 'fake-route', 'prefix' => 'http://proxy?_=', 'desc' => 'a link']
                ]
            ]
        );
        $record = $this->getRecord($driver, [], null, '1:fake-route', 2);
        $this->assertEquals(
            [
                ['route' => 'fake-route', 'prefix' => 'http://proxy?_=', 'desc' => 'a link', 'url' => 'http://proxy?_=http://server-foo/baz']
            ],
            $record->getLinkDetails()
        );
    }

    /**
     * Test getLinkDetails with invalid details
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Invalid URL array.
     */
    public function testGetLinkDetailsFailure()
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'URLs' => [
                    ['bad' => 'junk']
                ]
            ]
        );
        $record = $this->getRecord($driver);
        $this->assertEquals(
            [
                ['route' => 'fake-route', 'prefix' => 'http://proxy?_=', 'desc' => 'a link', 'url' => 'http://proxy?_=http://server-foo/baz']
            ],
            $record->getLinkDetails()
        );
    }

    /**
     * Test getUrlList
     *
     * @return void
     */
    public function testGetUrlList()
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'URLs' => [
                    ['route' => 'fake-route', 'prefix' => 'http://proxy?_=', 'desc' => 'a link']
                ]
            ]
        );
        $record = $this->getRecord($driver, [], null, '1:fake-route', 2);
        $this->assertEquals(
            ['http://proxy?_=http://server-foo/baz'], $record->getUrlList()
        );
    }

    /**
     * Get a Record object ready for testing.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    Record driver
     * @param array|Config                      $config    Configuration
     * @param \VuFind\View\Helper\Root\Context  $context   Context helper
     * @param bool|string                       $url       Should we add a URL helper? False if no, expected timing + : + expected route if yes.
     * @param bool|int                          $serverurl Should we add a ServerURL helper? False if no, expected timing if yes.
     *
     * @return Record
     */
    protected function getRecord($driver, $config = [], $context = null, $url = false, $serverurl = false)
    {
        if (null === $context) {
            $context = $this->getMockContext();
        }
        $view = $this->getMock('Zend\View\Renderer\PhpRenderer');
        $view->expects($this->at(0))->method('plugin')
            ->with($this->equalTo('context'))
            ->will($this->returnValue($context));
        if ($url) {
            list($at, $route) = explode(':', $url);
            $view->expects($this->at($at))->method('plugin')
                ->with($this->equalTo('url'))
                ->will($this->returnValue($this->getMockUrl($route)));
        }
        if (false !== $serverurl) {
            $view->expects($this->at($serverurl))->method('plugin')
                ->with($this->equalTo('serverurl'))
                ->will($this->returnValue($this->getMockServerUrl()));
        }
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
     * Get a mock URL helper
     *
     * @param string $expectedRoute Route expected by mock helper
     *
     * @return \Zend\View\Helper\Url
     */
    protected function getMockUrl($expectedRoute)
    {
        $url = $this->getMock('Zend\View\Helper\Url');
        $url->expects($this->once())->method('__invoke')
            ->with($this->equalTo($expectedRoute))
            ->will($this->returnValue('http://foo/bar'));
        return $url;
    }

    /**
     * Get a mock server URL helper
     *
     * @param string $expectedRoute Route expected by mock helper
     *
     * @return \Zend\View\Helper\ServerUrl
     */
    protected function getMockServerUrl()
    {
        $url = $this->getMock('Zend\View\Helper\ServerUrl');
        $url->expects($this->once())->method('__invoke')
            ->will($this->returnValue('http://server-foo/baz'));
        return $url;
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