<?php

/**
 * Console command: browscap
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Console
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Http\GuzzleService;

/**
 * Console command: browscap
 *
 * @category VuFind
 * @package  Console
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BrowscapCommand extends Command
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/browscap';

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Guzzle service
     *
     * @var GuzzleService
     */
    protected $guzzleService;

    /**
     * Constructor
     *
     * @param CacheManager  $cacheManager  Cache manager
     * @param GuzzleService $guzzleService Guzzle service
     */
    public function __construct(
        CacheManager $cacheManager,
        GuzzleService $guzzleService
    ) {
        parent::__construct();
        $this->cacheManager = $cacheManager;
        $this->guzzleService = $guzzleService;
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Browscap Cache Manager')
            ->setHelp('Manages the browscap cache.')
            ->addArgument(
                'function',
                InputArgument::REQUIRED,
                'Function to execute. Currently the only supported function is: update'
            )
            ->addOption(
                'file-type',
                null,
                InputOption::VALUE_REQUIRED,
                'Browscap file type (standard, lite or full). See https://browscap.org/ for more information.',
                'standard'
            );
    }

    /**
     * Run the command.
     *
     * Note that there's also similar functionality in MaintenanceController.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '1024M');
        if ($input->getArgument('function') !== 'update') {
            $output->writeln('<error>Invalid function specified</error>');
            return Command::FAILURE;
        }
        switch ($input->getOption('file-type')) {
            case 'full':
                $type = \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI_FULL;
                break;
            case 'lite':
                $type = \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI_LITE;
                break;
            case 'standard':
                $type = \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI;
                break;
            default:
                $output->writeln('<error>Invalid file-type specified</error>');
                return Command::FAILURE;
        }

        $cache = new SimpleCacheDecorator($this->cacheManager->getCache('browscap'));
        $logger = new ConsoleLogger($output, [LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL]);
        $client = $this->guzzleService->createClient();

        $bc = new \BrowscapPHP\BrowscapUpdater($cache, $logger, $client);
        $logger->info('Checking for update...');
        try {
            $bc->checkUpdate();
        } catch (\BrowscapPHP\Exception\NoNewVersionException $e) {
            $logger->info('No newer version available.');
            return Command::SUCCESS;
        } catch (\BrowscapPHP\Exception\NoCachedVersionException $e) {
            $logger->info('No cached version available.');
        } catch (\Exception $e) {
            // Output the exception and continue (assume we don't have a current version):
            $logger->warning((string)$e);
        }
        $logger->info('Updating browscap cache...');
        $bc->update($type);
        $logger->info('Update complete.');

        return Command::SUCCESS;
    }
}
