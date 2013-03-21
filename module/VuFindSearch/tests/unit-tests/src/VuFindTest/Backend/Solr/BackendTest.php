<?php

/**
 * Unit tests for SOLR backend.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindTest\Backend\Solr;

use VuFindSearch\Backend\Solr\Backend;
use Zend\Http\Response;
use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

/**
 * Unit tests for SOLR backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test retrieving a record.
     *
     * @return void
     */
    public function testRetrieve ()
    {
        $resp = $this->loadResponse('single-record');
        $conn = $this->getMock('VuFindSearch\Backend\Solr\Connector', array('retrieve'), array('http://example.tld/'));
        $conn->expects($this->once())
            ->method('retrieve')
            ->will($this->returnValue($resp->getBody()));

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->retrieve('foobar');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('690250223', $rec->id);
    }

    /**
     * Test terms component.
     *
     * @return void
     */
    public function testTerms ()
    {
        $resp = $this->loadResponse('terms');
        $conn = $this->getMock('VuFindSearch\Backend\Solr\Connector', array('query'), array('http://example.tld/'));
        $conn->expects($this->once())
            ->method('query')
            ->will($this->returnValue($resp->getBody()));
        $back = new Backend($conn);
        $back->setIdentifier('test');
        $terms = $back->terms('author', '', -1);
        $this->assertTrue($terms->hasFieldTerms('author'));
        $this->assertCount(10, $terms->getFieldTerms('author'));
    }

    /**
     * Load a SOLR response as fixture.
     *
     * @param string $fixture Fixture file
     *
     * @return Zend\Http\Response
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse ($fixture)
    {
        $file = realpath(sprintf('%s/solr/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s', $file));
        }
        return Response::fromString(file_get_contents($file));
    }

}
