<?php
/**
 * Export Support Test Class
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
namespace VuFindTest;
use VuFind\Export, Zend\Config\Config;

/**
 * Export Support Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ExportTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test bulk options.
     *
     * @return void
     */
    public function testGetBulkOptions()
    {
        $config = array(
            'BulkExport' => array(
                'enabled' => 1,
                'options' => 'foo:bar:baz',
            ),
            'Export' => array(
                'foo' => 1,
                'bar' => 1,
                'baz' => 0,
                'xyzzy' => 1,
            ),
        );
        $export = $this->getExport($config);
        $this->assertEquals(array('foo', 'bar'), $export->getBulkOptions());
    }

    /**
     * Test "needs redirect"
     *
     * @return void
     */
    public function testNeedsRedirect()
    {
        $config = array(
            'foo' => array('redirectUrl' => 'http://foo'),
            'bar' => array(),
        );
        $export = $this->getExport(array(), $config);
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
            $this->getExport()->processGroup('foo', array("a\n", "b\n", "c\n"))
        );
    }

    /**
     * Test XML case of process group
     *
     * @return void
     */
    public function testProcessGroupXML()
    {
        $config = array(
            'foo' => array(
                'combineNamespaces' => array('marc21|http://www.loc.gov/MARC21/slim'),
                'combineXpath' => '/marc21:collection/marc21:record',
            ),
        );
        $this->assertEquals(
            "<?xml version=\"1.0\"?>\n"
            . '<collection xmlns="http://www.loc.gov/MARC21/slim">'
            . '<record><id>a</id></record><record><id>b</id></record></collection>',
            trim (
                $this->getExport(array(), $config)->processGroup(
                    'foo',
                    array($this->getFakeMARCXML('a'), $this->getFakeMARCXML('b'))
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
        $config = array(
            'foo' => array('requiredMethods' => array('getTitle')),
            'bar' => array('requiredMethods' => array('getThingThatDoesNotExist'))
        );

        $export = $this->getExport(array(), $config);
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
        $config = array(
            'RefWorks' => array('requiredMethods' => array('getTitle')),
            'EndNote' => array('requiredMethods' => array('getThingThatDoesNotExist'))
        );

        $export = $this->getExport(array(), $config);
        $solr = new \VuFind\RecordDriver\SolrDefault();
        $this->assertEquals(array('RefWorks'), $export->getFormatsForRecord($solr));
    }

    /**
     * Test getFormatsForRecords
     *
     * @return void
     */
    public function testGetFormatsForRecords()
    {
        $mainConfig = array(
            'BulkExport' => array(
                'enabled' => 1,
                'options' => 'anything:marc',
            ),
            'Export' => array(
                'anything' => 1,
                'marc' => 1,
            ),
        );
        $exportConfig = array(
            'anything' => array('requiredMethods' => array('getTitle')),
            'marc' => array('requiredMethods' => array('getMarcRecord'))
        );
        $export = $this->getExport($mainConfig, $exportConfig);
        $solrDefault = new \VuFind\RecordDriver\SolrDefault();
        $solrMarc = new \VuFind\RecordDriver\SolrMarc();

        // Only $solrMarc supports the 'marc' option, so we should lose the 'marc' option when we add
        // the non-supporting $solrDefault to the array:
        $this->assertEquals(array('anything', 'marc'), $export->getFormatsForRecords(array($solrMarc)));
        $this->assertEquals(array('anything'), $export->getFormatsForRecords(array($solrMarc, $solrDefault)));
    }

    /**
     * Test getHeaders
     *
     * @return void
     */
    public function testGetHeaders()
    {
        $config = array('foo' => array('headers' => array('bar')));
        $export = $this->getExport(array(), $config);
        $this->assertEquals(array('bar'), $export->getHeaders('foo')->toArray());
    }

    /**
     * Test getRedirectUrl
     *
     * @return void
     */
    public function testGetRedirectUrl()
    {
        $mainConfig = array('config' => array('this' => 'that=true'));
        $template = 'http://result?src={encodedCallback}&fallbacktest={config|config|unset|default}&configtest={encodedConfig|config|this|default}';
        $exportConfig = array('foo' => array('redirectUrl' => $template));
        $export = $this->getExport($mainConfig, $exportConfig);
        $this->assertEquals(
            'http://result?src=http%3A%2F%2Fcallback&fallbacktest=default&configtest=that%3Dtrue',
            $export->getRedirectUrl('foo', 'http://callback')
        );
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
    protected function getExport($main = array(), $export = array())
    {
        return new Export(new Config($main), new Config($export));
    }
}