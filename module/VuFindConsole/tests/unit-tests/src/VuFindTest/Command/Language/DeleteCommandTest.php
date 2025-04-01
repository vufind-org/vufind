<?php

/**
 * Language/Delete command test.
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

namespace VuFindTest\Command\Language;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\I18n\ExtendedIniNormalizer;
use VuFind\I18n\Translator\Loader\ExtendedIniReader;
use VuFindConsole\Command\Language\DeleteCommand;

/**
 * Language/Delete command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DeleteCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Language fixture directory
     *
     * @var string
     */
    protected $languageFixtureDir = null;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->languageFixtureDir = $this->getFixtureDir('VuFindConsole') . 'language';
    }

    /**
     * Test that missing parameters yield an error message.
     *
     * @return void
     */
    public function testWithoutParameters(): void
    {
        $this->expectException(
            \Symfony\Component\Console\Exception\RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Not enough arguments (missing: "target").'
        );
        $command = new DeleteCommand(
            $this->getMockNormalizer(),
            $this->getMockReader()
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Data provider for testSuccessWithMinimalParameters()
     *
     * @return array[]
     */
    public static function successWithMinimalParametersProvider(): array
    {
        return ['double-quoted string' => ['foo'], 'single-quoted string' => ['foo-quoted']];
    }

    /**
     * Test the simplest possible success case.
     *
     * @param string $domain Text domain to test with.
     *
     * @return void
     *
     * @dataProvider successWithMinimalParametersProvider
     */
    public function testSuccessWithMinimalParameters(string $domain): void
    {
        $expectedPath = realpath($this->languageFixtureDir) . '/' . $domain . '/en.ini';
        $normalizer = $this->getMockNormalizer();
        $normalizer->expects($this->once())->method('normalizeFile')
            ->with($this->equalTo($expectedPath));
        $command = $this->getMockCommand($normalizer);
        $command->expects($this->once())->method('writeFileToDisk')
            ->with(
                $this->equalTo($expectedPath),
                $this->equalTo('')
            );
        $commandTester = new CommandTester($command);
        $commandTester->execute(['target' => $domain . '::bar']);
        $this->assertEquals("Processing en.ini...\n", $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test an attempt to delete a string that does not exist.
     *
     * @return void
     */
    public function testDeletingNonExistentString(): void
    {
        $command = $this->getMockCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['target' => 'foo::barzap']);
        $this->assertEquals(
            "Processing en.ini...\nSource key not found.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get a mock command object
     *
     * @param ?ExtendedIniNormalizer $normalizer  Normalizer for .ini files
     * @param ?ExtendedIniReader     $reader      Reader for .ini files
     * @param ?string                $languageDir Base language file directory
     * @param array                  $methods     Methods to mock
     *
     * @return DeleteCommand&MockObject
     */
    protected function getMockCommand(
        ?ExtendedIniNormalizer $normalizer = null,
        ?ExtendedIniReader $reader = null,
        ?string $languageDir = null,
        array $methods = ['writeFileToDisk']
    ): DeleteCommand&MockObject {
        return $this->getMockBuilder(DeleteCommand::class)
            ->setConstructorArgs(
                [
                    $normalizer ?? $this->getMockNormalizer(),
                    $reader ?? $this->getMockReader(),
                    $languageDir ?? $this->languageFixtureDir,
                ]
            )->onlyMethods($methods)
            ->getMock();
    }

    /**
     * Get a mock normalizer object
     *
     * @param array $methods Methods to mock
     *
     * @return ExtendedIniNormalizer&MockObject
     */
    protected function getMockNormalizer(array $methods = []): ExtendedIniNormalizer&MockObject
    {
        $builder = $this->getMockBuilder(ExtendedIniNormalizer::class)
            ->disableOriginalConstructor();
        if (!empty($methods)) {
            $builder->onlyMethods($methods);
        }
        return $builder->getMock();
    }

    /**
     * Get a mock reader object
     *
     * @param array $methods Methods to mock
     *
     * @return ExtendedIniReader&MockObject
     */
    protected function getMockReader(array $methods = []): ExtendedIniReader&MockObject
    {
        return $this->getMockBuilder(ExtendedIniReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }
}
