<?php

/**
 * Unit tests for Amazon cover loader.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Content\Covers;
use VuFind\Code\ISBN;

/**
 * Unit tests for Amazon cover loader.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class AmazonTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Amazon parameters
     *
     * @var array
     */
    protected $params = array('ResponseGroup' => 'Images', 'AssociateTag' => 'fake');

    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading()
    {
        $expected = array(
            'small' =>
                'http://ecx.images-amazon.com/images/I/518597FY50L._SL75_.jpg',
            'medium' =>
                'http://ecx.images-amazon.com/images/I/518597FY50L._SL160_.jpg',
            'large' => 'http://ecx.images-amazon.com/images/I/518597FY50L.jpg',
        );
        foreach ($expected as $size => $expectedUrl) {
            $this->assertEquals($expectedUrl, $this->getUrl($size));
        }
    }

    /**
     * Test illegal size
     *
     * @return void
     */
    public function testIllegalCoverLoading()
    {
        $this->assertEquals(false, $this->getUrl('illegal'));
    }

    /**
     * Simulate retrieval of a cover URL for a particular size.
     *
     * @param string $size Size to retrieve
     *
     * @return string
     */
    protected function getUrl($size)
    {
        $amazon = $this->getMock(
            'VuFind\Content\Covers\Amazon', array('getAmazonService'),
            array('fake', 'fakesecret')
        );
        $amazon->expects($this->once())
            ->method('getAmazonService')->with($this->equalTo('fakekey'))
            ->will($this->returnValue($this->getFakeService()));
        return $amazon->getUrl(
            'fakekey', $size, array('isbn' => new ISBN('0739313126'))
        );
    }

    /**
     * Create fake Amazon service
     *
     * @return \ZendService\Amazon\Amazon
     */
    protected function getFakeService()
    {
        $service = $this->getMock(
            'ZendService\Amazon\Amazon', array('itemLookup'),
            array('fakekey', 'US', 'fakesecret')
        );
        $service->expects($this->once())
            ->method('itemLookup')
            ->with($this->equalTo('0739313126'), $this->equalTo($this->params))
            ->will($this->returnValue($this->loadFixture()));
        return $service;
    }

    /**
     * Load fixture
     *
     * @return \ZendService\Amazon\Item
     */
    protected function loadFixture()
    {
        $file = realpath(__DIR__ . '/../../../../../fixtures/content/amazon-cover');
        return unserialize(file_get_contents($file));
    }
}