<?php
/**
 * Base Provider Test Class
 *
 * PHP version 7
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
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
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Config\Provider;

use VuFind\Config\Manager;
use VuFind\Config\Provider\Base;


/**
 * Base Provider Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BaseTest extends \VuFindTest\Unit\TestCase
{
    const BASE = __DIR__ . '/../../../../../fixtures/configs/example/local';

    public function setUp()
    {
        Manager::getInstance();
    }

    public function testLocalIni()
    {
        $glob = "**/*.ini";
        $flags = Base::FLAG_FLAT_INI | Base::FLAG_PARENT_CONFIG;
        $data = (new Base(self::BASE, $glob, $flags))();
        $this->assertEquals([
            'sub.localKey' => 'localValue',
            'sub.coreKey' => 'coreValue',
            'key' => 'changedValue'
        ], $data['ini']['first']);
    }
}