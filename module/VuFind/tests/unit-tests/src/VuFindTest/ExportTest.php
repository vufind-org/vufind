<?php

/**
 * Export Support Test Class
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

namespace VuFindTest;

use Laminas\Config\Config;
use VuFind\Export;

/**
 * Export Support Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExportTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test options using legacy (deprecated) configuration.
     *
     * @return void
     */
    public function testGetBulkOptionsLegacy()
    {
        $config = [
            'BulkExport' => [
                'enabled' => 1,
                'options' => 'foo:bar:baz',
            ],
            'Export' => [
                'foo' => 1,
                'bar' => 1,
                'baz' => 0,
                'xyzzy' => 1,
            ],
        ];
        $export = $this->getExport($config);
        $this->assertEquals(['foo', 'bar'], $export->getActiveFormats('bulk'));
    }

    /**
     * Test active formats.
     *
     * @return void
     */
    public function testGetActiveFormats()
    {
        $config = [
            'Export' => [
                'foo' => 'record,bulk',
                'bar' => 'record,bulk',
                'baz' => 0,
                'xyzzy' => 1,
            ],
        ];
        $export = $this->getExport($config);
        $this->assertEquals(['foo', 'bar'], $export->getActiveFormats('bulk'));
        $this->assertEquals(
            ['foo', 'bar', 'xyzzy'],
            $export->getActiveFormats('record')
        );
    }

    /**
     * Test "needs redirect"
     *
     * @return void
     */
    public function testNeedsRedirect()
    {
        $config = [
            'foo' => ['redirectUrl' => 'http://foo'],
            'bar' => [],
        ];
        $export = $this->getExport([], $config);
        $this->assertTrue($export->needsRedirect('foo'));
        $this->assertFalse($export->needsRedirect('bar'));
    }

    /**
     * Test non-XML case of process group
     *
     * @return void
     */
    public function testProcessGroupNonXML()
    {
        $this->assertEquals(
            "a\nb\nc\n",
            $this->getExport()->processGroup('foo', ["a\n", "b\n", "c\n"])
        );
    }

    /**
     * Test XML case of process group
     *
     * @return void
     */
    public function testProcessGroupXML()
    {
        $config = [
            'foo' => [
                'combineNamespaces' => ['marc21|http://www.loc.gov/MARC21/slim'],
                'combineXpath' => '/marc21:collection/marc21:record',
            ],
        ];
        $this->assertEquals(
            "<?xml version=\"1.0\"?>\n"
            . '<collection xmlns="http://www.loc.gov/MARC21/slim">'
            . '<record><id>a</id></record><record><id>b</id></record></collection>',
            trim(
                $this->getExport([], $config)->processGroup(
                    'foo',
                    [$this->getFakeMARCXML('a'), $this->getFakeMARCXML('b')]
                )
            )
        );
    }

    /**
     * Test recordSupportsFormat
     *
     * @return void
     */
    public function testRecordSupportsFormat()
    {
        $config = [
            'foo' => ['requiredMethods' => ['getTitle']],
            'bar' => ['requiredMethods' => ['getThingThatDoesNotExist']],
        ];

        $export = $this->getExport([], $config);
        $primo = new \VuFind\RecordDriver\Primo();
        $solr = new \VuFind\RecordDriver\SolrDefault();

        // Case 1: Primo doesn't support any kind of export.
        $this->assertFalse($export->recordSupportsFormat($primo, 'foo'));

        // Case 2: Solr has a getTitle method.
        $this->assertTrue($export->recordSupportsFormat($solr, 'foo'));

        // Case 3: Solr lacks a getThingThatDoesNotExist method.
        $this->assertFalse($export->recordSupportsFormat($solr, 'bar'));

        // Case 4: Format 'baz' is undefined.
        $this->assertFalse($export->recordSupportsFormat($solr, 'baz'));
    }

    /**
     * Test getFormatsForRecord
     *
     * @return void
     */
    public function testGetFormatsForRecord()
    {
        // Use RefWorks and EndNote as our test data, since these are the items
        // turned on by default if no main config is passed in.
        $config = [
            'RefWorks' => ['requiredMethods' => ['getTitle']],
            'EndNote' => ['requiredMethods' => ['getThingThatDoesNotExist']],
        ];

        $export = $this->getExport([], $config);
        $solr = new \VuFind\RecordDriver\SolrDefault();
        $this->assertEquals(['RefWorks'], $export->getFormatsForRecord($solr));
    }

    /**
     * Test getFormatsForRecords
     *
     * @return void
     */
    public function testGetFormatsForRecords()
    {
        $mainConfig = [
            'Export' => [
                'anything' => 'record,bulk',
                'marc' => 'record,bulk',
            ],
        ];
        $exportConfig = [
            'anything' => ['requiredMethods' => ['getTitle']],
            'marc' => ['requiredMethods' => ['getMarcReader']],
        ];
        $export = $this->getExport($mainConfig, $exportConfig);
        $solrDefault = new \VuFind\RecordDriver\SolrDefault();
        $solrMarc = new \VuFind\RecordDriver\SolrMarc();

        // Only $solrMarc supports the 'marc' option, so we should lose the 'marc' option when we add
        // the non-supporting $solrDefault to the array:
        $this->assertEquals(['anything', 'marc'], $export->getFormatsForRecords([$solrMarc]));
        $this->assertEquals(['anything'], $export->getFormatsForRecords([$solrMarc, $solrDefault]));
    }

    /**
     * Test getHeaders
     *
     * @return void
     */
    public function testGetHeaders()
    {
        $config = ['foo' => ['headers' => ['bar']]];
        $export = $this->getExport([], $config);
        $this->assertEquals(['bar'], $export->getHeaders('foo')->toArray());
    }

    /**
     * Test getRedirectUrl
     *
     * @return void
     */
    public function testGetRedirectUrl()
    {
        $mainConfig = ['config' => ['this' => 'that=true']];
        $template = 'http://result?src={encodedCallback}&fallbacktest={config|config|unset|default}'
            . '&configtest={encodedConfig|config|this|default}';
        $exportConfig = ['foo' => ['redirectUrl' => $template]];
        $export = $this->getExport($mainConfig, $exportConfig);
        $this->assertEquals(
            'http://result?src=http%3A%2F%2Fcallback&fallbacktest=default&configtest=that%3Dtrue',
            $export->getRedirectUrl('foo', 'http://callback')
        );
    }

    /**
     * Test getLabelForFormat
     *
     * @return void
     */
    public function testGetLabel(): void
    {
        $config = [
            'foo' => [],
            'bar' => ['label' => 'baz'],
        ];
        $export = $this->getExport([], $config);
        // test "use section label as default"
        $this->assertEquals('foo', $export->getLabelForFormat('foo'));
        // test "override with label setting"
        $this->assertEquals('baz', $export->getLabelForFormat('bar'));
    }

    /**
     * Test getBulkExportType()
     *
     * @return void
     */
    public function testGetBulkExportType(): void
    {
        // We should get 'link' as the default if nothing is configured:
        $this->assertEquals(
            'link',
            $this->getExport()->getBulkExportType('foo')
        );

        // We should get export-specific values from export.ini if set;
        // otherwise, we should get the default setting from config.ini.
        $mainConfig = ['BulkExport' => ['defaultType' => 'download']];
        $exportConfig = ['foo' => ['bulkExportType' => 'post']];
        $export = $this->getExport($mainConfig, $exportConfig);
        $this->assertEquals('post', $export->getBulkExportType('foo'));
        $this->assertEquals('download', $export->getBulkExportType('bar'));
    }

    /**
     * Test getBulkUrl() method
     *
     * @return void
     */
    public function testGetBulkUrl(): void
    {
        $url = $this->getMockBuilder(\Laminas\View\Helper\Url::class)
            ->disableOriginalConstructor()
            ->getMock();
        $url->expects($this->once())->method('__invoke')
            ->with($this->equalTo('cart-doexport'))
            ->will($this->returnValue('/cart/doExport'));
        $serverUrl = $this->getMockBuilder(\Laminas\View\Helper\ServerUrl::class)
            ->disableOriginalConstructor()
            ->getMock();
        $serverUrl->expects($this->once())->method('__invoke')
            ->with($this->equalTo('/cart/doExport'))
            ->will($this->returnValue('http://localhost/cart/doExport'));
        $view = $this->getMockBuilder(\Laminas\View\Renderer\PhpRenderer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->expectConsecutiveCalls($view, 'plugin', [['serverurl'], ['url']], [$serverUrl, $url]);
        $this->assertEquals(
            'http://localhost/cart/doExport?f=foo&i%5B%5D=1&i%5B%5D=2&i%5B%5D=3',
            $this->getExport()->getBulkUrl($view, 'foo', [1, 2, 3])
        );
    }

    /**
     * Test getPostField()
     *
     * @return void
     */
    public function testGetPostField(): void
    {
        $exportConfig = ['foo' => ['postField' => 'postField']];
        $export = $this->getExport([], $exportConfig);
        // Test configured method:
        $this->assertEquals('postField', $export->getPostField('foo'));
        // Test default:
        $this->assertEquals('ImportData', $export->getPostField('bar'));
    }

    /**
     * Test getTargetWindow()
     *
     * @return void
     */
    public function testGetTargetWindow(): void
    {
        $exportConfig = ['foo' => ['targetWindow' => 'window']];
        $export = $this->getExport([], $exportConfig);
        // Test configured method:
        $this->assertEquals('window', $export->getTargetWindow('foo'));
        // Test default:
        $this->assertEquals('barMain', $export->getTargetWindow('bar'));
    }

    /**
     * Get a fake MARCXML record
     *
     * @param string $id ID to put in record.
     *
     * @return string
     */
    protected function getFakeMARCXML($id)
    {
        return '<collection xmlns="http://www.loc.gov/MARC21/slim"><record><id>'
            . $id . '</id></record></collection>';
    }

    /**
     * Get a configured Export object.
     *
     * @param array $main   Main config
     * @param array $export Export config
     *
     * @return Export
     */
    protected function getExport($main = [], $export = [])
    {
        return new Export(new Config($main), new Config($export));
    }
}
