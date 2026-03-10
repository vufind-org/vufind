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
    /**
     * Test file path
     *
     * @var string
     */
    protected string $testFile;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->testFile = tempnam(sys_get_temp_dir(), 'vufind_filetype_test');
        parent::setUp();
    }

    /**
     * Clean up test environment
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    /**
     * Test true condition for a missing file.
     *
     * @return void
     */
    public function testTrueMatchingForMissingFile(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        $filetypeHandler = new Filetype();
        $this->assertTrue($filetypeHandler->checkCondition([
            'type' => 'filetype',
            'comparator' => '=',
            'checkedValues' => '',
            'file' => $this->testFile,
        ]));
    }

    /**
     * Test true condition.
     *
     * @return void
     */
    public function testTrueMatching(): void
    {
        file_put_contents($this->testFile, 'test content');
        $filetypeHandler = new Filetype();
        $this->assertTrue($filetypeHandler->checkCondition([
            'type' => 'filetype',
            'comparator' => '=',
            'checkedValues' => 'file',
            'file' => $this->testFile,
        ]));
    }

    /**
     * Test false condition.
     *
     * @return void
     */
    public function testFalseMatching(): void
    {
        file_put_contents($this->testFile, 'test content');
        $filetypeHandler = new Filetype();
        $this->assertFalse($filetypeHandler->checkCondition([
            'type' => 'filetype',
            'comparator' => '=',
            'checkedValues' => 'dir',
            'file' => $this->testFile,
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
