<?php
/**
 * VuDL Abstract Base Test Class
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
 * VuDL Solr Manager Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class AbstractBaseTest extends \VuFindTest\Unit\TestCase
{
    public function testConstructorAndSet()
    {
        $subject = new \VuDL\Connection\AbstractBase([]);
        $subject->setHttpService(new FakeInterface());
    }
}

class FakeInterface implements \VuFindHttp\HttpServiceInterface
{
    public function proxify(\Zend\Http\Client $client, array $options = [])
    {

    }
    public function get($url, array $params = [], $timeout = null)
    {

    }
    public function post($url, $body = null, $type = 'application/octet-stream', $timeout = null)
    {

    }
    public function postForm($url, array $params = [], $timeout = null)
    {

    }
    public function createClient($url, $method = \Zend\Http\Request::METHOD_GET, $timeout = null)
    {

    }
}