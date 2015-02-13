<?php

/**
 * Unit tests for VuFind NoCacheAdapter.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Cache
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Cache\Storage\Adapter;

use VuFind\Cache\Storage\Adapter\NoCacheAdapter;

use PHPUnit_Framework_TestCase;

/**
 * Unit tests for VuFind NoCacheAdapter.
 *
 * @category VuFind2
 * @package  Cache
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class NoCacheAdapterTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test that an item is not stored.
     *
     * @return void
     */
    public function testHasItemIsFalseAfterSetItem()
    {
        $cache = new NoCacheAdapter();
        $cache->setItem('key', 'value');
        $this->assertFalse((boolean)$cache->hasItem('key'));
    }
}