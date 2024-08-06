<?php

/**
 * WebResults Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\Recommend\WebResults;

/**
 * WebResults Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class WebResultsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting search class id.
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $class = new \ReflectionClass(WebResults::class);
        $method = $class->getMethod('getSearchClassId');
        $method->setAccessible(true);
        $runner = $this->getMockBuilder(\VuFind\Search\SearchRunner::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configManager = $this->getMockBuilder(\VuFind\Config\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $obj = new WebResults($runner, $configManager);

        $this->assertSame('SolrWeb', $method->invoke($obj));
    }
}
