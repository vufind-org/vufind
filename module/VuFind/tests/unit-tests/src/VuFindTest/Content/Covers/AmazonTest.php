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
use VuFindCode\ISBN;

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
    protected $params = ['ResponseGroup' => 'Images', 'AssociateTag' => 'fake'];

    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading()
    {
        $expected = [
            'small' =>
                'http://ecx.images-amazon.com/images/I/518597FY50L._SL75_.jpg',
            'medium' =>
                'http://ecx.images-amazon.com/images/I/518597FY50L._SL160_.jpg',
            'large' => 'http://ecx.images-amazon.com/images/I/518597FY50L.jpg',
        ];
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
     * Test missing ISBN
     *
     * @return void
     */
    public function testMissingIsbn()
    {
        $this->assertEquals(false, $this->getUrl('small', ''));
    }

    /**
     * Test broken Amazon service (we should just swallow exceptions)
     *
     * @return void
     */
    public function testServiceException()
    {
        $this->assertEquals(false, $this->getUrl('small', '0739313126', true));
    }

    /**
     * Simulate retrieval of a cover URL for a particular size.
     *
     * @param string $size  Size to retrieve
     * @param string $isbn  ISBN to retrieve (empty for none)
     * @param bool   $throw Should the fake service throw an exception?
     *
     * @return string
     */
    protected function getUrl($size, $isbn = '0739313126', $throw = false)
    {
        $amazon = $this->getMock(
            'VuFind\Content\Covers\Amazon', ['getAmazonService'],
            ['fake', 'fakesecret']
        );
        $params = [];
        if (!empty($isbn)) {
            $behavior = $throw
                ? $this->throwException(new \Exception('kaboom'))
                : $this->returnValue($this->loadFixture());
            $amazon->expects($this->once())
                ->method('getAmazonService')->with($this->equalTo('fakekey'))
                ->will($this->returnValue($this->getFakeService($isbn, $behavior)));
            $params['isbn'] = new ISBN($isbn);
        }
        return $amazon->getUrl('fakekey', $size, $params);
    }

    /**
     * Create fake Amazon service
     *
     * @param string $isbn             ISBN to retrieve (empty for none)
     * @param mixed  $expectedBehavior Behavior of the itemLookup method
     *
     * @return \ZendService\Amazon\Amazon
     */
    protected function getFakeService($isbn, $expectedBehavior)
    {
        $service = $this->getMock(
            'ZendService\Amazon\Amazon', ['itemLookup'],
            ['fakekey', 'US', 'fakesecret']
        );
        if (!empty($isbn)) {
            $service->expects($this->once())
                ->method('itemLookup')
                ->with($this->equalTo($isbn), $this->equalTo($this->params))
                ->will($expectedBehavior);
        }
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