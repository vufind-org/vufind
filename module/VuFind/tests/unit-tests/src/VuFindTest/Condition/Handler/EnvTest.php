<?php

/**
 * Environment variable handler test
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

use VuFind\Condition\Handler\Env;

/**
 * Environment variable handler test
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class EnvTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test environment variable name.
     *
     * @var string
     */
    protected string $envVar = 'VUFIND_ENV_TEST_VAR';

    /**
     * Clean up test environment
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        putenv($this->envVar);
    }

    /**
     * Test true condition for an unset environment variable.
     *
     * @return void
     */
    public function testTrueMatchingForUnsetEnv(): void
    {
        putenv($this->envVar);
        $envHandler = new Env();
        $this->assertTrue($envHandler->checkCondition([
            'type' => 'env',
            'comparator' => '=',
            'checkedValues' => '',
            'env' => $this->envVar,
        ]));
    }

    /**
     * Test true condition.
     *
     * @return void
     */
    public function testTrueMatching(): void
    {
        putenv($this->envVar . '=testValue');
        $envHandler = new Env();
        $this->assertTrue($envHandler->checkCondition([
            'type' => 'env',
            'comparator' => '=',
            'checkedValues' => 'testValue',
            'env' => $this->envVar,
        ]));
    }

    /**
     * Test false condition.
     *
     * @return void
     */
    public function testFalseMatching(): void
    {
        $envHandler = new Env();
        putenv($this->envVar . '=wrongValue');
        $this->assertFalse($envHandler->checkCondition([
            'type' => 'env',
            'comparator' => '=',
            'checkedValues' => 'testValue',
            'env' => $this->envVar,
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
            'Env condition handler requires key "env" of type string specifying the environment variable to check.'
        );

        $envHandler = new Env();
        $envHandler->checkCondition([
            'type' => 'env',
            'comparator' => '=',
            'checkedValues' => 'testValue',
        ]);
    }
}
