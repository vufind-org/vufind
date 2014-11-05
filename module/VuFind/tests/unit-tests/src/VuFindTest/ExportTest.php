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
     * Get a fake MARCXML record
     *
     * @param string $id ID to put in record.
     *
     * @return string
     */
    public function getFakeMARCXML($id)
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