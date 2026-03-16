<?php

/**
 * Filetype handler test
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

use VuFind\Condition\Handler\Filetype;
use VuFindTest\Feature\FixtureTrait;

/**
 * Filetype handler test
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FiletypeTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Test true condition for a missing file.
     *
     * @return void
     */
    public function testTrueMatchingForMissingFile(): void
    {
        $filetypeHandler = new Filetype();
        $this->assertTrue($filetypeHandler->checkCondition([
            'type' => 'filetype',
            'comparator' => '=',
            'checkedValues' => '',
            'file' => '/non/existing/file',
        ]));
    }

    /**
     * Test true condition for directory.
     *
     * @return void
     */
    public function testTrueMatchingForDir(): void
    {
        $filetypeHandler = new Filetype();
        $this->assertTrue($filetypeHandler->checkCondition([
            'type' => 'filetype',
            'comparator' => '=',
            'checkedValues' => 'dir',
            'file' => $this->getFixtureDir(),
        ]));
    }

    /**
     * Test true condition for file.
     *
     * @return void
     */
    public function testTrueMatchingForFile(): void
    {
        $filetypeHandler = new Filetype();
        $this->assertTrue($filetypeHandler->checkCondition([
            'type' => 'filetype',
            'comparator' => '=',
            'checkedValues' => 'file',
            'file' => $this->getFixtureDir() . '/misc/testFile.txt',
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
            'Filetype condition handler requires key "file" of type string specifying the path to the file to check.'
        );

        $filetypeHandler = new Filetype();
        $filetypeHandler->checkCondition([
            'type' => 'filetype',
            'comparator' => '=',
            'checkedValues' => 'testValue',
        ]);
    }
}
