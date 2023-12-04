<?php

/**
 * Generate/NonTabRecordAction command test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Command\Generate;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Generate\NonTabRecordActionCommand;
use VuFindConsole\Generator\GeneratorTools;

/**
 * Generate/NonTabRecordAction command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NonTabRecordActionCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test that missing parameters yield an error message.
     *
     * @return void
     */
    public function testWithoutParameters()
    {
        $this->expectException(
            \Symfony\Component\Console\Exception\RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Not enough arguments '
            . '(missing: "action, target_module").'
        );
        $command = new NonTabRecordActionCommand(
            $this->getMockGeneratorTools(),
            []
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test the simplest possible success case.
     *
     * @return void
     */
    public function testSuccessWithMinimalParameters()
    {
        $configFixturePath = $this->getFixtureDir('VuFindConsole') . 'empty.config.php';
        $expectedConfig = [
            'router' => [
                'routes' => [
                    'example-foo' => [
                        'type'    => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route'    => '/Example/[:id]/Foo',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                'controller' => 'Example',
                                'action'     => 'Foo',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $tools = $this->getMockGeneratorTools(
            ['getModuleConfigPath', 'backUpFile', 'writeModuleConfig']
        );
        $tools->expects($this->once())->method('getModuleConfigPath')
            ->with($this->equalTo('xyzzy'))
            ->will($this->returnValue($configFixturePath));
        $tools->expects($this->once())->method('backUpFile')
            ->with($this->equalTo($configFixturePath));
        $tools->expects($this->once())->method('writeModuleConfig')
            ->with(
                $this->equalTo($configFixturePath),
                $this->equalTo($expectedConfig)
            );
        $config = [
            'router' => [
                'routes' => [
                    'example' => [
                        'type'    => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route'    => '/Example/[:id[/[:tab]]]',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                'controller' => 'Example',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $command = new NonTabRecordActionCommand($tools, $config);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'action' => 'Foo',
                'target_module' => 'xyzzy',
            ]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get a mock generator tools object
     *
     * @param array $methods Methods to mock
     *
     * @return GeneratorTools
     */
    protected function getMockGeneratorTools($methods = [])
    {
        return $this->getMockBuilder(GeneratorTools::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }
}
