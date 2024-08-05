<?php

/**
 * SummonBestBets Test Class
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

use VuFind\Recommend\SummonBestBets;

/**
 * SummonBestBets Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SummonBestBetsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting best bets results.
     *
     * @return void
     */
    public function testGetResults(): void
    {
        $pm = $this->getMockBuilder(\VuFind\Search\Results\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $obj = new SummonBestBets($pm);
        $results = $this->getMockBuilder(\VuFind\Search\Summon\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parms = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results->expects($this->once())->method('getParams')
            ->will($this->returnValue($parms));
        $parms->expects($this->once())->method('getSearchClassId')
            ->will($this->returnValue('Summon'));
        $obj->process($results);
        $results->expects($this->once())->method('getBestBets')
            ->will($this->returnValue(false));
        $this->assertFalse($obj->getResults());
    }
}
