<?php

/**
 * String handler test.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Condition\Handler;

use VuFind\Condition\Handler\StringHandler;

/**
 * String handler test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class StringHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test true condition.
     *
     * @return void
     */
    public function testTrueMatching(): void
    {
        $stringHandler = new StringHandler();
        $this->assertTrue($stringHandler->checkCondition([
            'type' => 'string',
            'comparator' => '=',
            'checkedValues' => 'test',
            'string' => 'test',
        ]));
    }

    /**
     * Test false condition.
     *
     * @return void
     */
    public function testFalseMatching(): void
    {
        $stringHandler = new StringHandler();
        $this->assertFalse($stringHandler->checkCondition([
            'type' => 'string',
            'comparator' => '=',
            'checkedValues' => 'test',
            'string' => 'other',
        ]));
    }

    /**
     * Test invalid condition.
     *
     * @return void
     */
    public function testInvalidCondition(): void
    {
        $this->expectException(\VuFind\Exception\ConditionException::class);
        $this->expectExceptionMessage(
            'String condition handler requires key "string" of type string specifying the value to check.'
        );

        $stringHandler = new StringHandler();
        $stringHandler->checkCondition([
            'type' => 'string',
            'comparator' => '=',
            'checkedValues' => 'test',
        ]);
    }
}
