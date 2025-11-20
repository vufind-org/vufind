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
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\Cache;
use VuFind\Record\Loader;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\Missing as MissingRecord;

use function assert;

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
     * @param ResourceServiceInterface $resourceService    Resource service
     * @param Loader                   $recordLoader       Record loader
     * @param ResourcePopulator        $resourcePopulator  Resource pop
     * @param PersistenceManager       $persistenceManager Persistence manager
     * @param ?string                  $name               The name of the command; passing null means
     * it must be set in configure()
     */
    public function __construct(
        protected ResourceServiceInterface $resourceService,
        protected Loader $recordLoader,
        protected ResourcePopulator $resourcePopulator,
        protected PersistenceManager $persistenceManager,
        ?string $name = null,
    ) {
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
            ->setHelp('Updates the metadata fields of the resource table.')
            ->addOption(
                'batch',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of records to process in a single batch',
                100
            )
            ->addOption(
                'min-age',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum age of a record (in days) before it is refreshed even if metadata is not missing.'
                . ' By default records with missing metadata are updated, but using this option allows updates to all'
                . ' records periodically.'
            )->addOption(
                'backend',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batch = $input->getOption('batch');
        $minAge = $input->getOption('min-age');
        $backend = $input->getOption('backend');

        $this->recordLoader->setCacheContext(Cache::CONTEXT_FAVORITE);

        $updated = 0;
        $redirected = 0;
        $missing = 0;
        $lastId = null;
        $output->writeln('<info>Updating resource metadata</info>');
        while ($resources = $this->resourceService->findMetadataToUpdate($lastId, $batch, $minAge, $backend)) {
            $recordIds = array_map(
                fn ($resource) => ['id' => $resource->getRecordId(), 'source' => $resource->getSource()],
                $resources
            );
            $records = $this->recordLoader->loadBatch($recordIds, true);
            foreach ($resources as $i => $resource) {
                $driver = $records[$i];
                $lastId = $resource->getId();
                $recordId = $resource->getRecordId();
                $source = $resource->getSource();
                assert($recordId == $driver->getUniqueID());
                if ($output->isVerbose()) {
                    $output->writeln("Checking record {$source}:{$recordId}");
                }
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
                    $driverRecordId = $driver->getUniqueId();
                    if ($recordId != $driverRecordId) {
                        $resource->setRecordId($driverRecordId);
                        ++$redirected;
                    }
                    ++$updated;
                }
            }
            $this->persistenceManager->flushEntities();
            array_walk($resources, [$this->persistenceManager, 'detachEntity']);
            $output->writeln(
                "<info>$updated records updated ($redirected redirects), $missing records missing</info>"
            );
        }

        $output->writeln('<info>Resource metadata update completed</info>');
        return self::SUCCESS;
    }
}
