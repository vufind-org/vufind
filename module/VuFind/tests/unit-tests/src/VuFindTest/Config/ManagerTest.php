<?php
/**
 * VuFind Configuration Manager Test Class
 *
 * PHP version 7
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
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
namespace VuFindTest\Config;

use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use VuFind\Config\Manager;
use VuFind\Config\ManagerFactory;
use Zend\Config\Config;

/**
 * VuFind Configuration Manager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ManagerTest extends TestCase
{
    const BASE_PATH = __DIR__ . '/../../../../fixtures/configs/example';

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function setUp()
    {
        /** @var ContainerInterface|MockObject $container */
        $container = $this->createMock(ContainerInterface::class);
        $this->manager = (new ManagerFactory)($container, Manager::class, [
            'aggregatorPath' => static::BASE_PATH . '/config.php',
            'useCache' => false
        ]);
    }

    public function testBasic()
    {
        $this->assertArraySubset([
            'u' => 'w',
            'y' => 'z'
        ], $this->getValue('basic/nested/yaml')->toArray());

        $this->assertArraySubset([
            'a' => 2,
            'b' => [
                'c' => 1,
                'd' => 0
            ]
        ], $this->getValue('basic/ini/S')->toArray());

        $this->assertArraySubset([
            'key'            => 43,
            '%weired;Key$\\' => true
        ], $this->getValue('basic/yaml')->toArray());

        $this->assertArrayHasKey('@parent_yaml',
            $this->getValue('basic/yaml')->toArray());

        $this->assertArraySubset([
            'ini'  => [
                'T' => [
                    'x' => 'z',
                    'a' => 'b',
                    'c' => 'd'
                ],
            ],
            'json' => [
                'u' => 'v',
                'x' => 'y'
            ],
            'yaml' => [
                'u' => 'w',
                'y' => 'z'
            ]
        ], $this->getConfig('basic/nested')->toArray());
    }

    public function testClassic()
    {
        $this->assertEquals('w', $this->getValue('classic/nested/yaml/u'));

        $this->assertArrayNotHasKey('y',
            $this->getValue('classic/nested/yaml')->toArray());

        $this->assertArraySubset([
            'key'            => 43,
            '%weired;Key$\\' => true
        ], $this->getValue('classic/yaml')->toArray());

        $this->assertArrayNotHasKey('@parent_yaml',
            $this->getValue('classic/yaml')->toArray());

        $this->assertArraySubset([
            'a'   => 2,
            'b.c' => 1,
            'b.d' => 0
        ], $this->getValue('classic/ini/S')->toArray());

        $this->assertArraySubset([
            'ini'  => [
                'T' => [
                    'x' => 'z',
                    'a' => 'b'
                ],
            ],
            'json' => [
                'x' => 'y'
            ],
            'yaml' => [
                'u' => 'w'
            ]
        ], $this->getConfig('classic/nested')->toArray());

        $this->assertArrayNotHasKey('c',
            $this->getConfig('classic/nested/ini/T')->toArray());
    }

    protected function getConfig($path = null): Config
    {
        return $this->manager->getConfig($path);
    }

    protected function getValue($path = null)
    {
        return $this->manager->getValue($path);
    }
}
