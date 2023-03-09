<?php
/**
 * AbstractExpireCommand test.
 *
 * PHP version 7
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

namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Db\Table\Gateway;
use VuFindConsole\Command\Util\AbstractExpireCommand;

/**
 * AbstractExpireCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AbstractExpireCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Name of class being tested
     *
     * @var string
     */
    protected $targetClass = AbstractExpireCommand::class;

    /**
     * Name of a valid table class to test with
     *
     * @var string
     */
    protected $validTableClass = \VuFind\Db\Table\AuthHash::class;

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'rows';

    /**
     * Age parameter to use when testing illegal age input.
     *
     * @var int
     */
    protected $illegalAge = 1;

    /**
     * Expected minimum age in error message.
     *
     * @var int
     */
    protected $expectedMinAge = 2;

    /**
     * Test an unsupported table class.
     *
     * @return void
     */
    public function testUnsupportedTableClass()
    {
        $table = $this->getMockBuilder(\VuFind\Db\Table\User::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            get_class($table) . ' does not support deleteExpired()'
        );
        new $this->targetClass($table, 'foo');
    }

    /**
     * Test an illegal age parameter.
     *
     * @return void
     */
    public function testIllegalAgeInput()
    {
        $table = $this->getMockBuilder($this->validTableClass)
            ->disableOriginalConstructor()
            ->getMock();
        $command = new $this->targetClass($table, 'foo');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['age' => $this->illegalAge]);
        $expectedMinAge = number_format($this->expectedMinAge, 1, '.', '');
        $this->assertEquals(
            "Expiration age must be at least $expectedMinAge days.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test that the command expires rows correctly.
     *
     * @return void
     */
    public function testSuccessfulExpiration()
    {
        $date = date('Y-m-d H:i:s');
        $table = $this->getMockBuilder($this->validTableClass)
            ->disableOriginalConstructor()
            ->getMock();
        $table->expects($this->exactly(3))->method('deleteExpired')
            ->withConsecutive([$date, 1000], [$date, 1000], [$date, 1000])
            ->willReturnOnConsecutiveCalls(1000, 7, false);
        $command = $this->getCommand($table, $date);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--sleep' => 1]);
        $response = $commandTester->getDisplay();
        // The response contains date stamps that will vary every time the test
        // runs, so let's split things apart to work around that...
        $parts = explode("\n", trim($response));
        $this->assertEquals(3, count($parts));
        $this->assertEquals(
            "1000 {$this->rowLabel} deleted.",
            explode('] ', $parts[0])[1]
        );
        $this->assertEquals(
            "7 {$this->rowLabel} deleted.",
            explode('] ', $parts[1])[1]
        );
        $this->assertEquals(
            "Total 1007 {$this->rowLabel} deleted.",
            explode('] ', $parts[2])[1]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test correct behavior when no rows need to be expired.
     *
     * @return void
     */
    public function testSuccessfulNonExpiration()
    {
        $date = date('Y-m-d H:i:s');
        $table = $this->getMockBuilder($this->validTableClass)
            ->disableOriginalConstructor()
            ->getMock();
        $table->expects($this->once())->method('deleteExpired')
            ->with($this->equalTo($date))
            ->will($this->returnValue(0));
        $command = $this->getCommand($table, $date);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $response = $commandTester->getDisplay();
        // The response contains date stamps that will vary every time the test
        // runs, so let's split things apart to work around that...
        $parts = explode("\n", trim($response));
        $this->assertEquals(1, count($parts));
        $this->assertEquals(
            "Total 0 {$this->rowLabel} deleted.",
            explode('] ', $parts[0])[1]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get the command class
     *
     * @param Gateway $table Table to process
     * @param string  $date  Expiration date threshold
     *
     * @return MockObject&AbstractExpireCommand
     */
    protected function getCommand(
        Gateway $table,
        string $date
    ): AbstractExpireCommand {
        $command = $this->getMockBuilder($this->targetClass)
            ->setConstructorArgs([$table, 'foo'])
            ->onlyMethods(['getDateThreshold'])
            ->getMock();
        $command->expects($this->once())
            ->method('getDateThreshold')
            ->with(2)
            ->willReturn($date);
        return $command;
    }
}
