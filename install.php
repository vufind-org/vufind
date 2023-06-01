<?php
/**
 * Command-line tool to begin VuFind installation process
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2012.
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
 * @package  Installer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/installation Wiki
 */

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("Please run 'composer install' to load dependencies.\n");
}
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__
    . '/module/VuFindConsole/src/VuFindConsole/Command/Install/InstallCommand.php';

$command = new \VuFindConsole\Command\Install\InstallCommand($argv[0]);
$application = new \Symfony\Component\Console\Application();
$application->add($command);
$application->setDefaultCommand($command->getName(), true);
return $application->run();
