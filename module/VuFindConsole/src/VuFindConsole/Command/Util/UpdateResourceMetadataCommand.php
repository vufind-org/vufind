<?php

/**
 * Command for updating metadata in the resource table.
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

use DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\Cache;
use VuFind\Record\Loader;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\Missing as MissingRecord;

use function sprintf;

/**
 * Command for updating metadata in the resource table.
 *
 * @category VuFind
 * @package  Console
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/update_resource_metadata',
    description: 'Resource metadata updater'
)]
class UpdateResourceMetadataCommand extends Command
{
    /**
     * Constructor
     *
     * @param ResourceServiceInterface $resourceService   Resource service
     * @param Loader                   $recordLoader      Record loader
     * @param ResourcePopulator        $resourcePopulator Resource pop
     */
    public function __construct(
        protected ResourceServiceInterface $resourceService,
        protected Loader $recordLoader,
        protected ResourcePopulator $resourcePopulator
    ) {
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Updates the metadata fields of the resource table.')
            ->addOption(
                'batch',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of records to process in a single batch',
                1000
            )
            ->addOption(
                'min-age',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum age of a record (in days) before it is refreshed even if metadata is not missing.'
                . ' By default records with missing metadata are updated, but using this option allows to update all'
                . ' records periodically.'
            )->addOption(
                'backend',
                null,
                InputOption::VALUE_REQUIRED,
                'Record backend (source) to check. By default resources for all backends are checked.'
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $batch = $input->getOption('batch');
        $minAge = $input->getOption('min-age');
        $backend = $input->getOption('backend');

        $this->recordLoader->setCacheContext(Cache::CONTEXT_FAVORITE);

        $updated = 0;
        $missing = 0;
        $errors = 0;
        $lastId = null;
        $output->writeln('<info>Updating resource metadata</info>');
        $starttime = microtime(true);
        while ($resources = $this->resourceService->findMetadataToUpdate($lastId, $batch, $minAge, $backend)) {
            $output->writeln(sprintf('%0.4f', microtime(true) - $starttime));
            foreach ($resources as $resource) {
                $lastId = $resource->getId();
                $recordId = $resource->getRecordId();
                $source = $resource->getSource();
                if ($output->isVerbose()) {
                    $output->writeln("Checking record {$source}:{$recordId}");
                }
                try {
                    $driver = $this->recordLoader->load($recordId, $source, true);
                    if ($driver instanceof MissingRecord) {
                        $output->writeln(
                            '<comment>'
                            . OutputFormatter::escape("Unable to load metadata for record {$source}:{$recordId}")
                            . '</comment>'
                        );
                        ++$missing;
                        // Always update the timestamp when running a periodical refresh:
                        if (null !== $minAge) {
                            $resource->setUpdated(new DateTime());
                        }
                    } else {
                        $this->resourcePopulator->assignMetadata($resource, $driver);
                        $resource->setUpdated(new DateTime());
                        ++$updated;
                    }
                    $this->resourceService->persistEntity($resource);
                } catch (\Exception $e) {
                    $output->writeln(
                        '<error>'
                        . OutputFormatter::escape(
                            "Problem saving metadata updates for record {$source}:{$recordId}: "
                            . (string)$e
                        )
                        . '</error>'
                    );
                    ++$errors;
                }
            }
            $output->writeln("<info>$updated records updated, $missing missing, $errors errors</info>");
            $starttime = microtime(true);
        }

        $output->writeln('<info>Resource metadata update completed</info>');
        return Command::SUCCESS;
    }
}
