<?php

/**
 * Recommend ContentBlock Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\ContentBlock;

/**
 * Recommend ContentBlock Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecommendTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the normal workflow of the block.
     *
     * @return void
     */
    public function testNormalBehavior(): void
    {
        $params = $this->createMock(\VuFind\Search\Base\Params::class);
        $request = new \Laminas\Stdlib\Parameters();
        $recommend = $this->createMock(\VuFind\Recommend\RecommendInterface::class);
        $recommend->expects($this->once())->method('setConfig')->with('baz:xyzzy');
        $recommend->expects($this->once())->method('init')->with($params, $request);
        $paramsManager = $this->createMock(\VuFind\Search\Params\PluginManager::class);
        $paramsManager->expects($this->once())->method('get')->with('foo')->willReturn($params);
        $recommendManager = $this->createMock(\VuFind\Recommend\PluginManager::class);
        $recommendManager->expects($this->once())->method('get')->with('bar')->willReturn($recommend);
        $config = 'foo:bar:baz:xyzzy';
        $block = new \VuFind\ContentBlock\Recommend($paramsManager, $recommendManager, $request);
        $block->setConfig($config);
        $this->assertEquals(
            ['recommend' => $recommend],
            $block->getContext()
        );
    }
}
