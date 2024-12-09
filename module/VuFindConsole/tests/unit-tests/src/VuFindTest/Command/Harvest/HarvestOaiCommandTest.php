<?php

/**
 * HarvestOai command test.
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

namespace VuFindTest\Command\Harvest;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Harvest\HarvestOaiCommand;
use VuFindTest\Feature\PathResolverTrait;

/**
 * HarvestOai command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HarvestOaiCommandTest extends \PHPUnit\Framework\TestCase
{
    use PathResolverTrait;

    /**
     * Test that the --ini setting is overridden automatically.
     *
     * @return void
     */
    public function testIniOverride()
    {
        $command = new HarvestOaiCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expectedIni = $this->getPathResolver()->getConfigPath('oai.ini', 'harvest');
        $this->assertEquals(
            "Please add OAI-PMH settings to $expectedIni.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }
}
