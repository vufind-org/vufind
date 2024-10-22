<?php

/**
 * Record view helper Test Class
 *
 * PHP version 8
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
use Laminas\View\Helper\ServerUrl;
use Laminas\View\Helper\Url;
use Laminas\View\Resolver\ResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Cover\Loader;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\PluginManager;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Tags\TagsService;
use VuFind\View\Helper\Root\Context;
use VuFind\View\Helper\Root\Record;
use VuFind\View\Helper\Root\SearchTabs;
use VuFindTheme\ThemeInfo;

use function is_array;

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
    use \VuFindTest\Feature\WithConsecutiveTrait;

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
    public function testMissingTemplate(): void
    {
        $this->expectException(\Laminas\View\Exception\RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot find RecordDriver/[brief class name]/core.phtml for class '
            . 'VuFind\\RecordDriver\\SolrMarc or any of its parent classes'
        );

        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $this->expectConsecutiveCalls(
            $record->getView()->resolver(),
            'resolve',
            [
                ['RecordDriver/SolrMarc/core.phtml'],
                ['RecordDriver/SolrDefault/core.phtml'],
                ['RecordDriver/DefaultRecord/core.phtml'],
                ['RecordDriver/AbstractBase/core.phtml'],
            ],
            false
        );
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
    public function testMissingTemplateWithoutException(): void
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
    public function testTemplateInheritance(): void
    {
        $record = $this->getRecord($this->loadRecordFixture('testbug1.json'));
        $tpl = 'RecordDriver/SolrDefault/collection-record.phtml';
        $this->expectConsecutiveCalls(
            $record->getView()->resolver(),
            'resolve',
            [['RecordDriver/SolrMarc/collection-record.phtml'], [$tpl]],
            [false, true]
        );
        $record->getView()->expects($this->once())->method('render')
            ->with($this->equalTo($tpl))
            ->willReturn('success');
        $this->assertEquals('success', $record->getCollectionBriefRecord());
    }

    /**
     * Test getExport.
     *
     * @return void
     */
    public function testGetExport(): void
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
    public function testGetFormatClass(): void
    {
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo(['format' => 'foo']))
            ->willReturn(['bar' => 'baz']);
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
    public function testGetFormatList(): void
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
    public function testGetToolbar(): void
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
    public function testGetCollectionMetadata(): void
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
    public function testGetSearchResult(): void
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
    public function testGetListEntry(): void
    {
        $driver = $this->createMock(RecordDriver::class);
        $driver->method('getUniqueID')->willReturn('foo');
        $driver->method('getSourceIdentifier')->willReturn('bar');
        $user = $this->createMock(UserEntityInterface::class);
        $listService = $this->createMock(UserListServiceInterface::class);
        $listService->expects($this->once())->method('getListsContainingRecord')
            ->with('foo', 'bar', $user)
            ->willReturn([1, 2, 3]);
        $serviceManager = $this->createMock(PluginManager::class);
        $serviceManager->expects($this->once())->method('get')->with(UserListServiceInterface::class)
            ->willReturn($listService);
        $expected = [
            'driver' => $driver, 'list' => null, 'user' => $user, 'lists' => [1, 2, 3],
        ];
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo($expected))
            ->willReturn(['bar' => 'baz']);
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord($driver, [], $context);
        $record->setDbServiceManager($serviceManager);
        // Because we are using a mock object, the first round of testing will
        // include an arbitrary class name in the template path; we need to make
        // that one fail so we can load the parent class' template instead:
        $tpl = 'RecordDriver/AbstractBase/list-entry.phtml';
        $this->expectConsecutiveCalls(
            $record->getView()->resolver(),
            'resolve',
            [[/* anything */], [$tpl]],
            [false, true]
        );
        $record->getView()->expects($this->once())->method('render')
            ->with($this->equalTo($tpl))
            ->willReturn('success');
        $this->assertEquals('success', $record->getListEntry(null, $user));
    }

    /**
     * Test getPreviewIds.
     *
     * @return void
     */
    public function testGetPreviewIds(): void
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
    public function testGetPreviews(): void
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $config = new \Laminas\Config\Config(['foo' => 'bar']);
        $context = $this->getMockContext();
        $context->expects($this->exactly(2))->method('apply')
            ->with($this->equalTo(compact('driver', 'config')))
            ->willReturn(['bar' => 'baz']);
        $context->expects($this->exactly(2))->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord($driver, $config, $context);
        $record->getView()->resolver()->expects($this->any())->method('resolve')
            ->willReturn(true);
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
            ->willReturnCallback($callback);
        $this->assertEquals('success1success2', $record->getPreviews());
    }

    /**
     * Data provider for testGetLink()
     *
     * @return array
     */
    public static function getLinkProvider(): array
    {
        return [
            'no hidden filters' => ['http://foo', '?', '', 'http://foo'],
            'URL with parameters' => [
                'http://foo?bar=baz',
                '&amp;',
                '&amp;filter=hidden',
                'http://foo?bar=baz&amp;filter=hidden',
            ],
            'URL without parameters' => ['http://foo', '?', '?filter=hidden', 'http://foo?filter=hidden'],
        ];
    }

    /**
     * Test getLink.
     *
     * @param string $linkUrl           Base link returned by link template
     * @param string $expectedSeparator Separator expected by getCurrentHiddenFilterParams
     * @param string $hiddenFilter      Return value from getCurrentHiddenFilterParams
     * @param string $expected          Expected final result
     *
     * @return void
     *
     * @dataProvider getLinkProvider
     */
    public function testGetLink(
        string $linkUrl,
        string $expectedSeparator,
        ?string $hiddenFilter,
        string $expected
    ): void {
        $context = $this->getMockContext();
        $callback = function ($arr) {
            return $arr['lookfor'] === 'foo';
        };
        $context->expects($this->once())->method('apply')
            ->with($this->callback($callback))
            ->willReturn(['bar' => 'baz']);
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord(
            $this->loadRecordFixture('testbug1.json'),
            [],
            $context,
            false,
            false,
            false
        );
        $container = $record->getView()->getHelperPluginManager();
        $container->get('searchTabs')->expects($this->once())
            ->method('getCurrentHiddenFilterParams')
            ->with($this->equalTo('Solr'), $this->equalTo(false), $this->equalTo($expectedSeparator))
            ->willReturn($hiddenFilter);
        $this->setSuccessTemplate($record, 'RecordDriver/SolrMarc/link-bar.phtml', $linkUrl);
        $this->assertEquals($expected, $record->getLink('bar', 'foo'));
    }

    /**
     * Test getCheckbox.
     *
     * @return void
     */
    public function testGetCheckbox(): void
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $tpl = 'record/checkbox.phtml';
        $context = $this->getMockContext();
        $randomIdentifier = 'baz';
        $driver->setResultSetIdentifier($randomIdentifier);

        $expectedCalls = [
            [
                $tpl,
                [
                    'number' => 1,
                    'id' => 'Solr|000105196',
                    'checkboxElementId' => "bar-{$randomIdentifier}-000105196",
                    'prefix' => 'bar',
                    'formAttr' => 'foo',
                ],
            ],
            [
                $tpl,
                [
                    'number' => 2,
                    'id' => 'Solr|000105196',
                    'checkboxElementId' => "bar-{$randomIdentifier}-000105196",
                    'prefix' => 'bar',
                    'formAttr' => 'foo',
                ],
            ],
        ];

        $this->expectConsecutiveCalls(
            $context,
            'renderInContext',
            $expectedCalls,
            ['success', 'success']
        );

        $record = $this->getRecord($driver, [], $context);

        // We run the test twice to ensure that checkbox incrementing works properly:
        $this->assertEquals('success', $record->getCheckbox('bar', 'foo', 1));
        $this->assertEquals('success', $record->getCheckbox('bar', 'foo', 2));
    }

    /**
     * Test getCheckboxWithoutIdAndWithoutPrefix.
     *
     * @return void
     */
    public function testGetCheckboxWithoutIdAndWithEmptyPrefix(): void
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $tpl = 'record/checkbox.phtml';
        $context = $this->getMockContext();

        $expectedCalls = [
            [
                $tpl,
                [
                    'number' => 1,
                    'id' => 'Solr|000105196',
                    'checkboxElementId' => '000105196',
                    'prefix' => '',
                    'formAttr' => 'foo',
                ],
            ],
            [
                $tpl,
                [
                    'number' => 2,
                    'id' => 'Solr|000105196',
                    'checkboxElementId' => '000105196',
                    'prefix' => '',
                    'formAttr' => 'foo',
                ],
            ],
        ];

        $record = $this->getRecord($driver, [], $context);

        $this->expectConsecutiveCalls(
            $context,
            'renderInContext',
            $expectedCalls,
            ['success', 'success']
        );

        $record = $this->getRecord($driver, [], $context);

        // We run the test twice to ensure that checkbox incrementing works properly:
        $this->assertEquals('success', $record->getCheckbox(formAttr: 'foo', number: 1));
        $this->assertEquals('success', $record->getCheckbox('', 'foo', 2));
    }

    /**
     * Test getUniqueHtmlElementId.
     *
     * @return void
     */
    public function testGetUniqueHtmlElementId()
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $record = $this->getRecord($driver);
        $contextPrefix = 'foo';
        $randomIdentifier = 'bar';

        // no result set identifier and no prefix
        $this->assertEquals(
            '000105196',
            $record->getUniqueHtmlElementId()
        );

        // no result set identifier but with prefix
        $this->assertEquals(
            "{$contextPrefix}-000105196",
            $record->getUniqueHtmlElementId($contextPrefix)
        );

        // with result set identifier but no prefix
        $driver->setResultSetIdentifier($randomIdentifier);
        $this->assertEquals(
            "{$randomIdentifier}-000105196",
            $record->getUniqueHtmlElementId()
        );

        // with result set identifier and with prefix
        $this->assertEquals(
            "{$contextPrefix}-{$randomIdentifier}-000105196",
            $record->getUniqueHtmlElementId($contextPrefix)
        );
    }

    /**
     * Test getTab.
     *
     * @return void
     */
    public function testGetTab(): void
    {
        $tab = new \VuFind\RecordTab\Description();
        $driver = $this->loadRecordFixture('testbug1.json');
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo(compact('driver', 'tab')))
            ->willReturn(['bar' => 'baz']);
        $context->expects($this->once())->method('restore')
            ->with($this->equalTo(['bar' => 'baz']));
        $record = $this->getRecord($driver, [], $context);
        $record->getView()->expects($this->once())->method('render')
            ->with($this->equalTo('RecordTab/description.phtml'))
            ->willReturn('success');
        $this->assertEquals('success', $record->getTab($tab));
    }

    /**
     * Test various ways of making getQrCode() fail.
     *
     * @return void
     */
    public function testGetQrCodeFailures(): void
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
    public function testGetQrCodeSuccess(): void
    {
        $driver = $this->loadRecordFixture('testbug1.json');
        $context = $this->getMockContext();
        $context->expects($this->once())->method('apply')
            ->with($this->equalTo(['driver' => $driver, 'extra' => 'xyzzy']))
            ->willReturn(['bar' => 'baz']);
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
    public function testGetThumbnailNone(): void
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
    public function testGetThumbnailHardCoded(): void
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
    public function testGetThumbnailDynamic(): void
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
    public function testGetLinkDetailsEmpty(): void
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
    public function testGetLinkDetailsSuccess(): void
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'URLs' => [
                    ['route' => 'fake-route', 'prefix' => 'http://proxy?_=', 'desc' => 'a link'],
                ],
            ]
        );
        $record = $this->getRecord($driver, [], null, 'fake-route', true);
        $this->assertEquals(
            [
                [
                    'route' => 'fake-route',
                    'prefix' => 'http://proxy?_=',
                    'desc' => 'a link',
                    'url' => 'http://proxy?_=http://server-foo/baz',
                ],
            ],
            $record->getLinkDetails()
        );
    }

    /**
     * Test getLinkDetails with invalid details
     *
     * @return void
     */
    public function testGetLinkDetailsFailure(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid URL array.');

        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'URLs' => [
                    ['bad' => 'junk'],
                ],
            ]
        );
        $record = $this->getRecord($driver);
        $this->assertEquals(
            [
                [
                    'route' => 'fake-route',
                    'prefix' => 'http://proxy?_=',
                    'desc' => 'a link',
                    'url' => 'http://proxy?_=http://server-foo/baz',
                ],
            ],
            $record->getLinkDetails()
        );
    }

    /**
     * Test getLinkDetails with duplicate URLs
     *
     * @return void
     */
    public function testGetLinkDetailsWithDuplicateURLs(): void
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
                    ['url' => 'http://foo/baz3'],
                ],
            ]
        );
        $record = $this->getRecord($driver);
        $this->assertEquals(
            [
                ['desc' => 'link 1', 'url' => 'http://foo/baz1'],
                ['desc' => 'link 2', 'url' => 'http://foo/baz2'],
                ['desc' => 'link 1 (alternate description)',
                    'url' => 'http://foo/baz1'],
                ['desc' => 'http://foo/baz3', 'url' => 'http://foo/baz3'],
            ],
            $record->getLinkDetails()
        );
    }

    /**
     * Test getUrlList
     *
     * @return void
     */
    public function testGetUrlList(): void
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'URLs' => [
                    ['route' => 'fake-route', 'prefix' => 'http://proxy?_=', 'desc' => 'a link'],
                ],
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
     * @param RecordDriver $driver                   Record driver
     * @param array|Config $config                   Configuration
     * @param Context      $context                  Context helper
     * @param bool|string  $url                      Should we add a URL helper? False if no, expected route if yes.
     * @param bool         $serverurl                Should we add a ServerURL helper?
     * @param bool         $setSearchTabExpectations Should we set default search tab expectations?
     *
     * @return Record
     */
    protected function getRecord(
        RecordDriver $driver,
        array|Config $config = [],
        Context $context = null,
        bool|string $url = false,
        bool $serverurl = false,
        bool $setSearchTabExpectations = true
    ): Record {
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
        $container->set('searchTabs', $this->getMockSearchTabs($setSearchTabExpectations));
        $view->setHelperPluginManager($container);
        $view->expects($this->any())->method('resolver')
            ->willReturn($this->getMockResolver());
        $config = is_array($config) ? new \Laminas\Config\Config($config) : $config;
        $record = new Record($this->createMock(TagsService::class), $config);
        $record->setCoverRouter(new \VuFind\Cover\Router('http://foo/bar', $this->getCoverLoader()));
        $record->setView($view);
        return $record($driver);
    }

    /**
     * Get a mock resolver object
     *
     * @return MockObject&ResolverInterface
     */
    protected function getMockResolver(): MockObject&ResolverInterface
    {
        return $this->createMock(ResolverInterface::class);
    }

    /**
     * Get a mock context object
     *
     * @return MockObject&Context
     */
    protected function getMockContext(): MockObject&Context
    {
        $context = $this->createMock(\VuFind\View\Helper\Root\Context::class);
        $context->expects($this->any())->method('__invoke')->willReturn($context);
        return $context;
    }

    /**
     * Get a mock URL helper
     *
     * @param string $expectedRoute Route expected by mock helper
     *
     * @return MockObject&Url
     */
    protected function getMockUrl($expectedRoute): MockObject&Url
    {
        $url = $this->createMock(Url::class);
        $url->expects($this->once())->method('__invoke')
            ->with($this->equalTo($expectedRoute))
            ->willReturn('http://foo/bar');
        return $url;
    }

    /**
     * Get a mock server URL helper
     *
     * @return MockObject&ServerUrl
     */
    protected function getMockServerUrl(): MockObject&ServerUrl
    {
        $url = $this->createMock(ServerUrl::class);
        $url->expects($this->once())->method('__invoke')->willReturn('http://server-foo/baz');
        return $url;
    }

    /**
     * Get a mock search tabs view helper
     *
     * @param bool $setDefaultExpectations Should we set up default expectations?
     *
     * @return MockObject&SearchTabs
     */
    protected function getMockSearchTabs(bool $setDefaultExpectations = true): MockObject&SearchTabs
    {
        $searchTabs = $this->getMockBuilder(SearchTabs::class)
            ->disableOriginalConstructor()->getMock();
        if ($setDefaultExpectations) {
            $searchTabs->expects($this->any())->method('getCurrentHiddenFilterParams')->willReturn('');
        }
        return $searchTabs;
    }

    /**
     * Load a fixture file.
     *
     * @param string $file File to load from fixture directory.
     *
     * @return object
     */
    protected function loadRecordFixture(string $file): object
    {
        $json = $this->getJsonFixture('misc/' . $file);
        $record = new \VuFind\RecordDriver\SolrMarc();
        $record->setRawData($json['response']['docs'][0]);
        return $record;
    }

    /**
     * Set up expectations for a template
     *
     * @param Record  $record   Record helper
     * @param string  $tpl      Template to expect
     * @param string  $response Response to send
     * @param ?object $matcher  Matcher for frequency of calls (default = once)
     *
     * @return void
     */
    protected function setSuccessTemplate(
        Record $record,
        string $tpl,
        string $response = 'success',
        ?object $matcher = null
    ) {
        $record->getView()->resolver()->expects($matcher ?? $this->once())->method('resolve')
            ->with($this->equalTo($tpl))
            ->willReturn(true);
        $record->getView()->expects($matcher ?? $this->once())->method('render')
            ->with($this->equalTo($tpl))
            ->willReturn($response);
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
    protected function getCoverLoader(
        array $config = [],
        \VuFind\Content\Covers\PluginManager $manager = null,
        ThemeInfo $theme = null,
        \VuFindHttp\HttpService $httpService = null,
        array|bool $mock = false
    ): Loader {
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
    protected function getThemeDir(): string
    {
        return realpath(__DIR__ . '/../../../../../../../themes');
    }
}
