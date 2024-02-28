<?php

/**
 * Language/Normalize command test.
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

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\I18n\ExtendedIniNormalizer;
use VuFind\I18n\Translator\Loader\ExtendedIniReader;
use VuFindConsole\Command\Language\NormalizeCommand;

/**
 * Language/Normalize command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NormalizeCommandTest extends \PHPUnit\Framework\TestCase
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
    public function testWithoutParameters()
    {
        $this->expectException(
            \Symfony\Component\Console\Exception\RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Not enough arguments (missing: "target").'
        );
        $command = new NormalizeCommand($this->getMockNormalizer());
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test normalizing a directory.
     *
     * @return void
     */
    public function testNormalizingDirectory()
    {
        $target = realpath($this->languageFixtureDir);
        $normalizer = $this->getMockNormalizer();
        $normalizer->expects($this->once())->method('normalizeDirectory')
            ->with($this->equalTo($target));
        $command = new NormalizeCommand($normalizer);
        $commandTester = new CommandTester($command);
        $commandTester->execute(compact('target'));
        $this->assertEquals('', $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test normalizing a directory.
     *
     * @return void
     */
    public function testNormalizingDirectoryWithBadFilter()
    {
        $target = realpath($this->languageFixtureDir);
        $filter = '*.ini';
        $command = new NormalizeCommand(new ExtendedIniNormalizer());
        $commandTester = new CommandTester($command);

        $this->expectExceptionMessage(
            "Cannot normalize a file with sections; $target/non-language-file.ini"
            . ' line 1 contains: [Main]'
        );

        $commandTester->execute(['target' => $target, '--filter' => $filter]);
    }

    /**
     * Test normalizing a file.
     *
     * @return void
     */
    public function testNormalizingFile()
    {
        $target = realpath($this->languageFixtureDir) . '/foo/en.ini';
        $normalizer = $this->getMockNormalizer();
        $normalizer->expects($this->once())->method('normalizeFile')
            ->with($this->equalTo($target));
        $command = new NormalizeCommand($normalizer);
        $commandTester = new CommandTester($command);
        $commandTester->execute(compact('target'));
        $this->assertEquals('', $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test an attempt to normalize a file that does not exist.
     *
     * @return void
     */
    public function testNormalizingNonExistentFile()
    {
        $target = realpath($this->languageFixtureDir) . '/foo/noexist.ini';
        $command = new NormalizeCommand($this->getMockNormalizer());
        $commandTester = new CommandTester($command);
        $commandTester->execute(compact('target'));
        $this->assertEquals(
            "{$target} does not exist.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test an attempt to normalize a file that contains bad content.
     *
     * @return void
     */
    public function testNormalizingNonLanguageFile()
    {
        $target = realpath($this->languageFixtureDir) . '/non-language-file.ini';
        $command = new NormalizeCommand(new ExtendedIniNormalizer());
        $commandTester = new CommandTester($command);

        $this->expectExceptionMessage(
            "Cannot normalize a file with sections; $target line 1 contains: [Main]"
        );

        $commandTester->execute(compact('target'));
    }

    /**
     * Get a mock normalizer object
     *
     * @param array $methods Methods to mock
     *
     * @return ExtendedIniNormalizer
     */
    protected function getMockNormalizer($methods = [])
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
     * @return ExtendedIniReader
     */
    protected function getMockReader($methods = [])
    {
        $builder = $this->getMockBuilder(ExtendedIniReader::class)
            ->disableOriginalConstructor();
        if (!empty($methods)) {
            $builder->onlyMethods($methods);
        }
        return $builder->getMock();
    }
}
