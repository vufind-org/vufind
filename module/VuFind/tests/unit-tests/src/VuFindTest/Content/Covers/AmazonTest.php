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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Content\Covers;
use VuFindCode\ISBN;

/**
 * Unit tests for Amazon cover loader.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
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
        $amazon = new AmazonCoverMock();
        $params = [];
        if (!empty($isbn)) {
            $amazon->setAmazonService(new AmazonServiceMock($throw));
            $params['isbn'] = new ISBN($isbn);
        }
        return $amazon->getUrl('fakekey', $size, $params);
    }
}

class AmazonServiceMock extends \ZendService\Amazon\Amazon
{
    protected $throwException;

    public function __construct($throw)
    {
        parent::__construct('fakekey', 'US', 'fakesecret');
        $this->throwException = $throw;
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

    /**
     * Get an AmazonService object for the specified key.
     *
     * @param string $key API key
     *
     * @return AmazonService
     */
    public function itemLookup($asin, array $options = [])
    {
        if ($this->throwException) {
            throw new \Exception('kaboom');
        } else {
            return $this->loadFixture();
        }
    }
}

class AmazonCoverMock extends \VuFind\Content\Covers\Amazon
{
    protected $service;

    public function __construct()
    {
        parent::__construct('fake', 'fakekey');
    }

    public function setAmazonService($service)
    {
        $this->service = $service;
    }

    /**
     * Get an AmazonService object for the specified key.
     *
     * @param string $key API key
     *
     * @return AmazonService
     */
    protected function getAmazonService($key)
    {
        return $service;
    }
}