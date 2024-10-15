<?php

/**
 * File Session Handler Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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

namespace VuFindTest\Session;

use VuFind\Session\File;

use function function_exists;

/**
 * File Session Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FileTest extends \VuFindTest\Unit\SessionHandlerTestCase
{
    /**
     * Path to session files
     *
     * @var string
     */
    protected $path;

    /**
     * Generic setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        $tempdir = function_exists('sys_get_temp_dir')
            ? sys_get_temp_dir() : DIRECTORY_SEPARATOR . 'tmp';
        $this->path = $tempdir . DIRECTORY_SEPARATOR . 'vufindtest_sessions';
    }

    /**
     * Generic teardown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        rmdir($this->path);
    }

    /**
     * Test the standard default session life cycle.
     *
     * @return void
     */
    public function testWriteReadAndDestroy()
    {
        $handler = $this->getHandler();
        $this->assertTrue($handler->write('foo', 'bar'));
        $this->assertEquals('bar', $handler->read('foo'));
        $this->setUpDestroyExpectations('foo');
        $this->assertTrue($handler->destroy('foo'));
        $this->assertEquals('', $handler->read('foo'));
    }

    /**
     * Test disabling writes.
     *
     * @return void
     */
    public function testDisabledWrites()
    {
        $handler = $this->getHandler();
        $handler->disableWrites();
        $this->assertTrue($handler->write('foo', 'bar'));
        $this->assertEquals('', $handler->read('foo'));

        // Now test re-enabling writes:
        $handler->enableWrites();
        $this->assertTrue($handler->write('foo', 'bar'));
        $this->assertEquals('bar', $handler->read('foo'));

        // Now clean up after ourselves:
        $this->setUpDestroyExpectations('foo');
        $this->assertTrue($handler->destroy('foo'));
        $this->assertEquals('', $handler->read('foo'));
    }

    /**
     * Test the session garbage collector.
     *
     * @return void
     */
    public function testGarbageCollector()
    {
        $handler = $this->getHandler();
        $this->assertTrue($handler->write('foo', 'bar'));
        $this->assertEquals('bar', $handler->read('foo'));
        // Use a negative garbage collection age so we can purge everything
        // without having to wait for time to pass in the test!
        $this->assertEquals(1, $handler->gc(-1));
        $this->assertEquals('', $handler->read('foo'));
    }

    /**
     * Get the session handler to test.
     *
     * @param \Laminas\Config\Config $config Optional configuration
     *
     * @return Database
     */
    protected function getHandler($config = null)
    {
        if (null === $config) {
            $config = new \Laminas\Config\Config(
                ['file_save_path' => $this->path]
            );
        }
        $handler = new File($config);
        $this->injectMockDatabaseDependencies($handler);
        return $handler;
    }
}
