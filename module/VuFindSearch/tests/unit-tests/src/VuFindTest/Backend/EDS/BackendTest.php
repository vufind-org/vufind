<?php

/**
 * Unit tests for EDS backend.
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

namespace VuFindTest\Backend\EDS;

use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\Backend\EDS\Response\RecordCollectionFactory;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

/**
 * Unit tests for EDS backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test setting a query builder.
     *
     * @return void
     */
    public function testSetQueryBuilder()
    {
        $qb = new \VuFindSearch\Backend\EDS\QueryBuilder();
        $back = $this->getBackend($this->getConnectorMock());
        $back->setQueryBuilder($qb);
        $this->assertEquals($qb, $back->getQueryBuilder());
    }

    /**
     * Test setting a custom record collection factory.
     *
     * @return void
     */
    public function testConstructorSetters()
    {
        $fact = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        $conn = $this->getConnectorMock();
        $back = $this->getBackend($conn, $fact);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $this->getProperty($back, 'client'));
    }

    /// Internal API

    /**
     * Load a WorldCat response as fixture.
     *
     * @param string $fixture Fixture file
     *
     * @return mixed
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse($fixture)
    {
        $file = realpath(sprintf('%s/eds/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s', $fixture));
        }
        return unserialize(file_get_contents($file));
    }

    /**
     * Return connector mock.
     *
     * @param array $mock Functions to mock
     *
     * @return array
     */
    protected function getConnectorMock(array $mock = array())
    {
        $client = $this->getMock('Zend\Http\Client');
        return $this->getMock(
            'VuFindSearch\Backend\EDS\Zend2', $mock, array(array(), $client)
        );
    }

    /**
     * Return backend
     *
     * @param \VuFindSearch\Backend\EDS\Zend2                         $connector Connector
     * @param \VuFindSearch\Response\RecordCollectionFactoryInterface $factory   Record collection factory
     * @param array                                                   $settings  Additional settings
     */
    protected function getBackend($connector, $factory = null, $settings = array())
    {
        if (null === $factory) {
            $factory = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        }
        return new Backend($connector, $factory, $settings);
    }
}
