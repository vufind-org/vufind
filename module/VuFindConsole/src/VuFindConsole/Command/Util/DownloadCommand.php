<?php

/**
 * Console command: download
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Console
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

use GuzzleHttp\RequestOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Http\GuzzleService;

/**
 * Console command: download
 *
 * @category VuFind
 * @package  Console
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/download',
    description: 'Download a file'
)]
class DownloadCommand extends Command
{
    /**
     * Constructor
     *
     * @param GuzzleService $guzzleService Guzzle service
     * @param ?string       $name          The name of the command; passing null means it must be set in configure()
     */
    public function __construct(protected GuzzleService $guzzleService, ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Downloads a file using the HTTP GET method')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'URL to get the file from.'
            )
            ->addArgument(
                'target-file',
                InputArgument::REQUIRED,
                "Target file. Note that a temporary file with the target name plus suffix '.partial' will be used"
                . ' during download',
            )
            ->addOption(
                'username',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Username for authentication'
            )
            ->addOption(
                'overwrite',
                mode: InputOption::VALUE_NONE,
                description: 'Overwrite any existing destination file'
            )
            ->addOption(
                'password',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Password for authentication'
            )
            ->addOption(
                'auth-type',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Authentication type (basic, digest, ntlm)',
                default: 'basic'
            )
            ->addOption(
                'read-timeout',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Read timeout in seconds',
                default: 30
            )
            ->addOption(
                'timeout',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Total request timeout in seconds (0 = no timeout)',
                default: 0
            )
            ->addOption(
                'status-interval',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Status update interval in seconds',
                default: 1
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $targetFile = $input->getArgument('target-file');
        $username = $input->getOption('username');
        $password = $input->getOption('password');
        $authType = $input->getOption('auth-type');
        $statusInterval = $input->getOption('status-interval');
        $overwrite = $input->getOption('overwrite');

        if (file_exists($targetFile)) {
            if (!$overwrite) {
                $output->writeln("<error>Target file $targetFile already exists</error>");
                return self::FAILURE;
            }
            if (!unlink($targetFile)) {
                $output->writeln("<error>Could not remove existing target file $targetFile</error>");
                return self::FAILURE;
            }
        }

        $partialFile = $targetFile . '.partial';
        if (file_exists($partialFile)) {
            if (!unlink($partialFile)) {
                $output->writeln("<error>Could not remove existing partial file $partialFile</error>");
                return self::FAILURE;
            }
        }

        $client = $this->guzzleService->createClient($url);

        $progressBar = null;
        if (!$output->isQuiet()) {
            $output->writeln("Downloading $url to $targetFile");
        }
        $options = [
            RequestOptions::READ_TIMEOUT => $input->getOption('read-timeout'),
            RequestOptions::TIMEOUT => $input->getOption('timeout'),
            RequestOptions::SINK => $partialFile,
        ];
        if (!$output->isQuiet()) {
            $options[RequestOptions::PROGRESS] = function (
                $downloadTotal,
                $downloadedBytes,
                /*$uploadTotal,*/
                /*$uploadedBytes*/
            ) use (
                $output,
                &$progressBar,
                $statusInterval
            ): void {
                $maxSteps = (int)round($downloadTotal / 1024 / 1024);
                if (null === $progressBar) {
                    $progressBar = new ProgressBar($output, $maxSteps, $statusInterval);
                    $this->configureProgressBarFormats($progressBar);
                    $progressBar->start();
                }
                $progressBar->setMaxSteps($maxSteps);
                $progressBar->setProgress((int)round($downloadedBytes / 1024 / 1024));
            };
        }

        if ($username && $password) {
            $auth = [$username, $password];
            if ('basic' !== $authType) {
                $auth[] = $authType;
            }
            $options[RequestOptions::AUTH] = $auth;
        }

        $response = $client->get($url, $options);
        if (!$output->isQuiet()) {
            $output->writeLn('');
        }
        if ($progressBar) {
            $progressBar->finish();
        }
        if ($response->getStatusCode() !== 200) {
            $output->writeLn("<error>Download failed: '$partialFile' to '$targetFile'</error>");
            return self::FAILURE;
        }
        if (!rename($partialFile, $targetFile)) {
            $output->writeLn("<error>Could not rename '$partialFile' to '$targetFile'</error>");
            return self::FAILURE;
        }
        if (!$output->isQuiet()) {
            $output->writeLn('Download complete');
        }
        return self::SUCCESS;
    }

    /**
     * Configure the output formats for different verbosity levels.
     *
     * @param ProgressBar $progressBar Progress bar
     *
     * @return void
     */
    protected function configureProgressBarFormats(ProgressBar $progressBar): void
    {
        $progressBar->setFormatDefinition(
            'normal',
            ' %current% MB / %max% MB [%bar%] %percent:3s%%'
        );
        $progressBar->setFormatDefinition(
            'verbose',
            ' %current% MB / %max% MB [%bar%] %percent:3s%% %elapsed:16s%'
        );
        $progressBar->setFormatDefinition(
            'very_verbose',
            ' %current% MB / %max% MB [%bar%] %percent:3s%% %elapsed:16s% / %remaining% remaining'
        );
        $progressBar->setFormatDefinition(
            'debug',
            ' %current% MB / %max% MB [%bar%] %percent:3s%% %elapsed:16s% / %remaining% remaining [%memory%]'
        );
        foreach (['normal', 'verbose', 'very_verbose'] as $verbosity) {
            $progressBar->setFormatDefinition(
                $verbosity . '_nomax',
                ' %current% MB [%bar%]'
            );
        }
        $progressBar->setFormatDefinition(
            'debug_nomax',
            ' %current% MB [%bar%] [%memory%]'
        );
    }
}
