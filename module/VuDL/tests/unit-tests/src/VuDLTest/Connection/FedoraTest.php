<?php
/**
 * VuDL Fedora Test Class
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
namespace VuDLTest;

/**
 * VuDL Fedora Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class FedoraTest extends \VuFindTest\Unit\TestCase
{
    public function testAllWithMock()
    {
        $subject = $this->getMock(
            '\VuDL\Connection\Fedora',
            ['getDatastreamContent', 'getDatastreamHeaders'],
            [(object) [
                'Fedora' => (object) [
                    'url_base' => 'http://jsontest.com/',
                    'query_url' => 'QUERY',
                    'adminUser' => 'ADMIN',
                    'adminPass' => 'ADMINPASS'
                ]
            ]]
        );
        $subject->method('getDatastreamContent')->will(
            $this->onConsecutiveCalls(
                '<hasModel xmlns="info:fedora/fedora-system:def/model#" rdf:resource="info:fedora/vudl-system:CLASS1"/><hasModel xmlns="info:fedora/fedora-system:def/model#" rdf:resource="info:fedora/vudl-system:CLASS2"/>',
                '<xml><a><b id="c"></b></a></xml>',
                'xlink:href="test_passed"',
                '<dc:title>T</dc:title><dc:id>ID</dc:id>'
            )
        );
        $subject->method('getDatastreamHeaders')->will(
            $this->onConsecutiveCalls(
                ['HTTP/1.1 200 OK'],
                ['HTTP/1.1 404 EVERYTHING IS WRONG']
            )
        );

        $this->assertEquals('http://jsontest.com/', $subject->getBase());

        $this->assertEquals(['CLASS1','CLASS2'], $subject->getClasses('id'));

        $this->assertEquals('<xml><a><b id="c"></b></a></xml>', $subject->getDatastreams('id'));
        $this->assertEquals('SimpleXMLElement', get_class($subject->getDatastreams('id', true)));

        $this->assertEquals(['test_passed', 'fake'], $subject->getCopyright('id', ['passed' => 'fake']));

        $this->assertEquals(['title' => 'T','id' => 'ID'], $subject->getDetails('id'));
        // Detail formatting tested in Solr

        $this->assertEquals('Zend\Http\Client', get_class($subject->getHttpClient('url')));
    }
}
