<?php

/**
 * Config CacheDecorator test class file.
 *
 * PHP version 8
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
 * @package  Tests
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config\Reader;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Config\Reader\ReaderInterface;
use VuFind\Config\Reader\CacheDecorator;

/**
 * Config CacheDecorator test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CacheDecoratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Read config from while, new file.
     *
     * @return void
     */
    public function testFromFileAndString()
    {
        $cache = $this->getMockForAbstractClass(StorageInterface::class);
        $cache->expects($this->exactly(2))
            ->method('setItem');
        $cache->expects($this->exactly(2))
            ->method('hasItem')
            ->will($this->returnValue(false));
        $reader = $this->getMockForAbstractClass(ReaderInterface::class);
        $reader->expects($this->once())
            ->method('fromFile')
            ->will($this->returnValue([]));
        $reader->expects($this->once())
            ->method('fromString')
            ->will($this->returnValue([]));
        $deco = new CacheDecorator($reader, $cache);
        $deco->fromFile('ignore');
        $deco->fromString('ignore');
    }

    /**
     * Read config from while, cached file.
     *
     * @return void
     */
    public function testFromFileAndStringCached()
    {
        $cache = $this->getMockForAbstractClass(StorageInterface::class);
        $cache->expects($this->never())
            ->method('setItem');
        $cache->expects($this->exactly(2))
            ->method('hasItem')
            ->will($this->returnValue(true));
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->will($this->returnValue([]));
        $reader = $this->getMockForAbstractClass(ReaderInterface::class);
        $deco = new CacheDecorator($reader, $cache);
        $deco->fromFile('ignore');
        $deco->fromString('ignore');
    }
}
