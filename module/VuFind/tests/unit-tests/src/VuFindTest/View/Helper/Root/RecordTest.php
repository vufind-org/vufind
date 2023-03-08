<?php

/**
 * Record view helper Test Class
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

namespace VuFindTest\View\Helper\Root;

use Laminas\Config\Config;
use Laminas\View\Exception\RuntimeException;
use VuFind\Cover\Loader;
use VuFind\View\Helper\Root\Record;
use VuFindTheme\ThemeInfo;

/**
 * Record view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecordTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Theme to use for testing purposes.
     *
     * @var string
     */
    protected $testTheme = 'bootstrap3';

    /**
     * Test attempting to display a template that does not exist.
     *
     * @return void
     */
    public function testMissingTemplate()
    {
        $this->expectException(\Laminas\View\Exception\RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot find RecordDriver/[brief class name]/core.phtml for class '
            . 'VuFind\\RecordDriver\\SolrMarc or any of its parent classes'
        );

        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $record->getView()->resolver()->expects($this->exactly(4))->method('resolve')
            ->withConsecutive(
                ['RecordDriver/SolrMarc/core.phtml'],
                ['RecordDriver/SolrDefault/core.phtml'],
                ['RecordDriver/DefaultRecord/core.phtml'],
                ['RecordDriver/AbstractBase/core.phtml']
            )
            ->willReturnOnConsecutiveCalls(false, false, false, false);
        $record->getView()->expects($this->any())->method('render')
            ->will($this->throwException(new RuntimeException('boom')));
        $record->getCoreMetadata();
    }

    /**
     * Test attempting to display a template that does not exist without throwing an
     * exception.
     *
     * @return void
     */
    public function testMissingTemplateWithoutException()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $this->assertEquals(
            '',
            $record->renderTemplate('foo', [], false)
        );
    }

    /**
     * Test template inheritance.
     *
     * @return void
     */
    public function testTemplateInheritance()
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $tpl = 'RecordDriver/SolrDefault/collection-record.phtml';
        $record->getView()->resolver()->expects($this->exactly(2))->method('resolve')
            ->withConsecutive(['RecordDriver/SolrMarc/collection-record.phtml'], [$tpl])
            ->willReturnOnConsecutiveCalls(false, true);
        $record->getView()->expects($this->once())->method('render')
            ->with($this->equalTo($tpl))
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
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/export-foo.phtml');
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
            $this->loadRecordFixture('testbug1.json'),
            [],
            $context
        );
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/format-class.phtml');
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
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/format-list.phtml');
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
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/toolbar.phtml');
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
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/collection-info.phtml');
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
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/result-foo.phtml');
        $this->assertEquals('success', $record->getSearchResult('foo'));
    }

    /**
     * Test getListEntry.
     *
     * @return void
     */
    public function testGetListEntry()
    {
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->expects($this->once())->method('getContainingLists')
            ->with($this->equalTo(42))
            ->will($this->returnValue([1, 2, 3]));
        $user = new \StdClass();
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
        // Because we are using a mock object, the first round of testing will
        // include an arbitrary class name in the template path; we need to make
        // that one fail so we can load the parent class' template instead:
        $tpl = 'RecordDriver/AbstractBase/list-entry.phtml';
        $record->getView()->resolver()->expects($this->exactly(2))->method('resolve')
            ->withConsecutive([/* anything */], [$tpl])
            ->willReturnOnConsecutiveCalls(false, true);
        $record->getView()->expects($this->once())->method('render')
            ->with($this->equalTo($tpl))
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
     * Test getPreviews.
     *
     * @return void
     */
    public function testGetPreviews()
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $config = new \Laminas\Config\Config(['foo' => 'bar']);
        $context = $this->getMockContext();
        $context->expects($this->exactly(2))->method('apply')
            ->with($this->equalTo(compact('driver', 'config')))
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->exactly(2))->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord($driver, $config, $context);
        $record->getView()->resolver()->expects($this->any())->method('resolve')
            ->will($this->returnValue(true));
        $tpl1 = 'RecordDriver/SolrMarc/previewdata.phtml';
        $tpl2 = 'RecordDriver/SolrMarc/previewlink.phtml';
        $callback = function ($tpl) use ($tpl1, $tpl2) {
            if ($tpl === $tpl1) {
                return 'success1';
            } elseif ($tpl === $tpl2) {
                return 'success2';
            } else {
                return 'fail';
            }
        };
        $record->getView()->expects($this->any())->method('render')
            ->with($this->logicalOr($this->equalTo($tpl1), $this->equalTo($tpl2)))
            ->will($this->returnCallback($callback));
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
        $callback = function ($arr) {
            return $arr['lookfor'] === 'foo';
        };
        $context->expects($this->once())->method('apply')
            ->with($this->callback($callback))
            ->will($this->returnValue(['bar' => 'baz']));
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'),
            [],
            $context
        );
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/link-bar.phtml');
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
        $context->expects($this->exactly(2))->method('renderInContext')
            ->withConsecutive(
                [
                    'record/checkbox.phtml',
                    ['id' => 'Solr|000105196', 'number' => 1, 'prefix' => 'bar', 'formAttr' => 'foo']
                ],
                [
                    'record/checkbox.phtml',
                    ['id' => 'Solr|000105196', 'number' => 2, 'prefix' => 'bar', 'formAttr' => 'foo']
                ]
            )
            ->willReturnOnConsecutiveCalls('success', 'success');
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'),
            [],
            $context
        );
        // We run the test twice to ensure that checkbox incrementing works properly:
        $this->assertEquals('success', $record->getCheckbox('bar', 'foo', 1));
        $this->assertEquals('success', $record->getCheckbox('bar', 'foo', 2));
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
        $record->getView()->expects($this->once())->method('render')
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
        $record = $this->getRecord($driver, $config, $context, 'qrcode-show');
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/core-qrcode.phtml', 'success', $this->any());
        $this->assertEquals(
            'http://foo/bar?text=success&level=L&size=3&margin=4',
            $record->getQrCode('core', ['extra' => 'xyzzy'])
        );
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
        $record = $this->getRecord($driver);
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
        $record = $this->getRecord($driver, [], null, 'fake-route', true);
        $this->assertEquals(
            [
                [
                    'route' => 'fake-route',
                    'prefix' => 'http://proxy?_=',
                    'desc' => 'a link',
                    'url' => 'http://proxy?_=http://server-foo/baz'
                ]
            ],
            $record->getLinkDetails()
        );
    }

    /**
     * Test getLinkDetails with invalid details
     *
     * @return void
     */
    public function testGetLinkDetailsFailure()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid URL array.');

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
                [
                    'route' => 'fake-route',
                    'prefix' => 'http://proxy?_=',
                    'desc' => 'a link',
                    'url' => 'http://proxy?_=http://server-foo/baz'
                ]
            ],
            $record->getLinkDetails()
        );
    }

    /**
     * Test getLinkDetails with duplicate URLs
     *
     * @return void
     */
    public function testGetLinkDetailsWithDuplicateURLs()
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'URLs' => [
                    ['desc' => 'link 1', 'url' => 'http://foo/baz1'],
                    ['desc' => 'link 2', 'url' => 'http://foo/baz2'],
                    ['desc' => 'link 1', 'url' => 'http://foo/baz1'],
                    ['desc' => 'link 1 (alternate description)',
                        'url' => 'http://foo/baz1'],
                    ['url' => 'http://foo/baz3'],
                    ['url' => 'http://foo/baz3']
                ]
            ]
        );
        $record = $this->getRecord($driver);
        $this->assertEquals(
            [
                ['desc' => 'link 1', 'url' => 'http://foo/baz1'],
                ['desc' => 'link 2', 'url' => 'http://foo/baz2'],
                ['desc' => 'link 1 (alternate description)',
                    'url' => 'http://foo/baz1'],
                ['desc' => 'http://foo/baz3', 'url' => 'http://foo/baz3']
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
        $record = $this->getRecord($driver, [], null, 'fake-route', true);
        $this->assertEquals(
            ['http://proxy?_=http://server-foo/baz'],
            $record->getUrlList()
        );
    }

    /**
     * Get a Record object ready for testing.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    Record driver
     * @param array|Config                      $config    Configuration
     * @param \VuFind\View\Helper\Root\Context  $context   Context helper
     * @param bool|string                       $url       Should we add a URL helper? False if no,
     * expected route if yes.
     * @param bool                              $serverurl Should we add a ServerURL helper?
     *
     * @return Record
     */
    protected function getRecord(
        $driver,
        $config = [],
        $context = null,
        $url = false,
        $serverurl = false
    ) {
        if (null === $context) {
            $context = $this->getMockContext();
        }
        $container = new \VuFindTest\Container\MockViewHelperContainer($this);
        $view = $container->get(
            \Laminas\View\Renderer\PhpRenderer::class,
            ['render', 'resolver']
        );
        $container->set('context', $context);
        $container->set('serverurl', $serverurl ? $this->getMockServerUrl() : false);
        $container->set('url', $url ? $this->getMockUrl($url) : $url);
        $container->set('searchTabs', $this->getMockSearchTabs());
        $view->setHelperPluginManager($container);
        $view->expects($this->any())->method('resolver')
            ->will($this->returnValue($this->getMockResolver()));
        $config = is_array($config) ? new \Laminas\Config\Config($config) : $config;
        $record = new Record($config);
        $record->setCoverRouter(new \VuFind\Cover\Router('http://foo/bar', $this->getCoverLoader()));
        $record->setView($view);
        return $record($driver);
    }

    /**
     * Get a mock resolver object
     *
     * @return \Laminas\View\Resolver\ResolverInterface
     */
    protected function getMockResolver()
    {
        return $this->createMock(\Laminas\View\Resolver\ResolverInterface::class);
    }

    /**
     * Get a mock context object
     *
     * @return \VuFind\View\Helper\Root\Context
     */
    protected function getMockContext()
    {
        $context = $this->createMock(\VuFind\View\Helper\Root\Context::class);
        $context->expects($this->any())->method('__invoke')
            ->will($this->returnValue($context));
        return $context;
    }

    /**
     * Get a mock URL helper
     *
     * @param string $expectedRoute Route expected by mock helper
     *
     * @return \Laminas\View\Helper\Url
     */
    protected function getMockUrl($expectedRoute)
    {
        $url = $this->createMock(\Laminas\View\Helper\Url::class);
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
     * @return \Laminas\View\Helper\ServerUrl
     */
    protected function getMockServerUrl()
    {
        $url = $this->createMock(\Laminas\View\Helper\ServerUrl::class);
        $url->expects($this->once())->method('__invoke')
            ->will($this->returnValue('http://server-foo/baz'));
        return $url;
    }

    /**
     * Get a mock search tabs view helper
     *
     * @return \VuFind\View\Helper\Root\SearchTabs
     */
    protected function getMockSearchTabs()
    {
        $searchTabs = $this->getMockBuilder(\VuFind\View\Helper\Root\SearchTabs::class)
            ->disableOriginalConstructor()->getMock();
        $searchTabs->expects($this->any())->method('getCurrentHiddenFilterParams')
            ->will($this->returnValue(''));
        return $searchTabs;
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
        $json = $this->getJsonFixture('misc/' . $file);
        $record = new \VuFind\RecordDriver\SolrMarc();
        $record->setRawData($json['response']['docs'][0]);
        return $record;
    }

    /**
     * Set up expectations for a template
     *
     * @param Record $record   Record helper
     * @param string $tpl      Template to expect
     * @param string $response Response to send
     * @param mixed  $matcher  Matcher for frequency of calls (default = once)
     *
     * @return void
     */
    protected function setSuccessTemplate(
        $record,
        $tpl,
        $response = 'success',
        $matcher = null
    ) {
        $record->getView()->resolver()->expects($matcher ?? $this->once())->method('resolve')
            ->with($this->equalTo($tpl))
            ->will($this->returnValue(true));
        $record->getView()->expects($matcher ?? $this->once())->method('render')
            ->with($this->equalTo($tpl))
            ->will($this->returnValue($response));
    }

    /**
     * Get a loader object to test.
     *
     * @param array                                $config      Configuration
     * @param \VuFind\Content\Covers\PluginManager $manager     Plugin manager (null to create mock)
     * @param ThemeInfo                            $theme       Theme info object (null to create default)
     * @param \VuFindHttp\HttpService              $httpService HTTP client factory
     * @param array|bool                           $mock        Array of functions to mock, or false for real object
     *
     * @return Loader
     */
    protected function getCoverLoader($config = [], $manager = null, $theme = null, $httpService = null, $mock = false)
    {
        $config = new Config($config);
        if (null === $manager) {
            $manager = $this->createMock(\VuFind\Content\Covers\PluginManager::class);
        }
        if (null === $theme) {
            $theme = new ThemeInfo($this->getThemeDir(), $this->testTheme);
        }
        if (null === $httpService) {
            $httpService = $this->getMockBuilder(\VuFindHttp\HttpService::class)->getMock();
        }
        if ($mock) {
            return $this->getMockBuilder(__NAMESPACE__ . '\MockLoader')
                ->onlyMethods($mock)
                ->setConstructorArgs([$config, $manager, $theme, $httpService])
                ->getMock();
        }
        return new Loader($config, $manager, $theme, $httpService);
    }

    /**
     * Get the theme directory.
     *
     * @return string
     */
    protected function getThemeDir()
    {
        return realpath(__DIR__ . '/../../../../../../../themes');
    }
}
