<?php

/**
 * Random Recommend tests.
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\Recommend\RandomRecommend as Random;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Random Recommend tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class RandomRecommendTest extends TestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setup()
    {
        $this->recommend = new Random(
            $this->getMock('VuFindSearch\Service'),
            $this->getMock('VuFind\Search\Params\PluginManager')
        );
    }

    /**
     * Test load
     *
     * @return void
     */
    public function testCanSetDisplayMode()
    {
        $this->recommend->setConfig("Solr:10:disregard");
        $this->assertEquals("disregard", $this->recommend->getDisplayMode());
    }
}
