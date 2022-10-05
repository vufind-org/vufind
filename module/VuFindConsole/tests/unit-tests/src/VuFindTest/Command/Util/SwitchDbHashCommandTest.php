<?php
/**
 * SwitchDbHashCommand test.
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

use Laminas\Config\Config;
use Laminas\Crypt\BlockCipher;
use Laminas\Crypt\Symmetric\Openssl;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Config\Writer;
use VuFind\Db\Table\User;
use VuFindConsole\Command\Util\SwitchDbHashCommand;

/**
 * SwitchDbHashCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SwitchDbHashCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\PathResolverTrait;

    /**
     * Expected path to config.ini
     *
     * @var string
     */
    protected $expectedConfigIniPath;

    /**
     * Prepare a mock object
     *
     * @param string $class Class to mock
     *
     * @return mixed
     */
    protected function prepareMock($class)
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock table object
     *
     * @return User
     */
    protected function getMockTable()
    {
        return $this->prepareMock(User::class);
    }

    /**
     * Get mock command object
     *
     * @param array $config Config settings
     * @param User  $table  User table gateway
     */
    protected function getMockCommand(array $config = [], $table = null)
    {
        return $this->getMockBuilder(SwitchDbHashCommand::class)
            ->setConstructorArgs(
                [
                    new Config($config),
                    $table ?? $this->getMockTable(),
                ]
            )->onlyMethods(['getConfigWriter'])
            ->getMock();
    }

    /**
     * Get a mock config writer
     *
     * @return Writer
     */
    protected function getMockConfigWriter()
    {
        return $this->prepareMock(Writer::class);
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->expectedConfigIniPath = $this->getPathResolver()
            ->getLocalConfigPath('config.ini', null, true);
    }

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
            'Not enough arguments (missing: "newmethod").'
        );
        $command = $this->getMockCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test missing key parameter (not in config or on command line).
     *
     * @return void
     */
    public function testWithoutKeyParameter()
    {
        $command = $this->getMockCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['newmethod' => 'blowfish']);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            "Please specify a key as the second parameter.\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test no action needed because no changes requested.
     *
     * @return void
     */
    public function testNoActionNeeded()
    {
        $command = $this->getMockCommand(
            [
                'Authentication' => [
                    'encrypt_ils_password' => true,
                    'ils_encryption_algo' => 'blowfish',
                    'ils_encryption_key' => 'bar',
                ]
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute(['newmethod' => 'blowfish', 'newkey' => 'bar']);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "No changes requested -- no action needed.\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test failed configurate write.
     *
     * @return void
     */
    public function testFailedConfigWrite()
    {
        $writer = $this->getMockConfigWriter();
        $writer->expects($this->once())->method('save')
            ->will($this->returnValue(false));
        $command = $this->getMockCommand();
        $command->expects($this->once())->method('getConfigWriter')
            ->will($this->returnValue($writer));
        $commandTester = new CommandTester($command);
        $commandTester->execute(['newmethod' => 'blowfish', 'newkey' => 'foo']);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            "\tUpdating {$this->expectedConfigIniPath}...\n\tWrite failed!\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test success with no users to update.
     *
     * @return void
     */
    public function testSuccessNoUsers()
    {
        $writer = $this->getMockConfigWriter();
        $writer->expects($this->exactly(3))->method('set')
            ->withConsecutive(
                ['Authentication', 'encrypt_ils_password', true],
                ['Authentication', 'ils_encryption_algo', 'blowfish'],
                ['Authentication', 'ils_encryption_key', 'foo']
            );
        $writer->expects($this->once())->method('save')
            ->will($this->returnValue(true));
        $table = $this->getMockTable();
        $table->expects($this->once())->method('select')
            ->will($this->returnValue([]));
        $command = $this->getMockCommand([], $table);
        $command->expects($this->once())->method('getConfigWriter')
            ->will($this->returnValue($writer));
        $commandTester = new CommandTester($command);
        $commandTester->execute(['newmethod' => 'blowfish', 'newkey' => 'foo']);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "\tUpdating {$this->expectedConfigIniPath}...\n\tConverting hashes for"
            . " 0 user(s).\n\tFinished.\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Get a mock row representing a user.
     *
     * @return \VuFind\Db\Row\Search
     */
    protected function getMockUserObject()
    {
        $data = [
            'id' => 2,
            'username' => 'foo',
            'email' => 'fake@myuniversity.edu',
            'created' => '2000-01-01 00:00:00',
            'cat_password' => 'mypassword',
            'last_language' => 'en',
        ];
        $adapter = $this->prepareMock(\Laminas\Db\Adapter\Adapter::class);
        $user = $this->getMockBuilder(\VuFind\Db\Row\User::class)
            ->setConstructorArgs([$adapter])
            ->onlyMethods(['save'])
            ->getMock();
        $user->populate($data, true);
        return $user;
    }

    /**
     * Decode a hash to confirm that it was encoded correctly.
     */
    protected function decode($hash)
    {
        $cipher = new BlockCipher(new Openssl(['algorithm' => 'blowfish']));
        $cipher->setKey('foo');
        return $cipher->decrypt($hash);
    }

    /**
     * Test success with a user to update.
     *
     * @return void
     */
    public function testSuccessWithUser()
    {
        $writer = $this->getMockConfigWriter();
        $writer->expects($this->exactly(3))->method('set')
            ->withConsecutive(
                ['Authentication', 'encrypt_ils_password', true],
                ['Authentication', 'ils_encryption_algo', 'blowfish'],
                ['Authentication', 'ils_encryption_key', 'foo']
            );
        $writer->expects($this->once())->method('save')
            ->will($this->returnValue(true));
        $user = $this->getMockUserObject();
        $user->expects($this->once())->method('save');
        $table = $this->getMockTable();
        $table->expects($this->once())->method('select')
            ->will($this->returnValue([$user]));
        $command = $this->getMockCommand([], $table);
        $command->expects($this->once())->method('getConfigWriter')
            ->will($this->returnValue($writer));
        $commandTester = new CommandTester($command);
        $commandTester->execute(['newmethod' => 'blowfish', 'newkey' => 'foo']);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "\tUpdating {$this->expectedConfigIniPath}...\n\tConverting hashes for"
            . " 1 user(s).\n\tFinished.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(null, $user['cat_password']);
        $this->assertEquals('mypassword', $this->decode($user['cat_pass_enc']));
    }
}
