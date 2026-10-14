<?php

/**
 * Console command: expire sessions.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Service\Feature\DeleteExpiredInterface;
use VuFind\Db\Service\PluginManager as ServiceManager;
use VuFind\Session\SessionDatabaseTrait;

/**
 * Console command: expire sessions.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/expire_sessions'
)]
class ExpireSessionsCommand extends AbstractExpireCommand
{
    use SessionDatabaseTrait;

    /**
     * Minimum legal age (in days) of rows to delete or null if age isn't applicable.
     *
     * @var int|float|null
     */
    protected $minAge = 0.1;

    /**
     * Default age of rows (in days) to delete. $minAge is used if $defaultAge is
     * null.
     *
     * @var int|float|null
     */
    protected $defaultAge = 2;

    /**
     * Help description for the command.
     *
     * @var string
     */
    protected $commandDescription = 'Database session table cleanup';

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'sessions';

    /**
     * Constructor.
     *
     * @param DeleteExpiredInterface $service        Service on which to expire rows
     * @param ServiceManager         $serviceManager Service manager
     * @param ?string                $name           The name of the command; passing null means it
     *                                               must be set in configure()
     */
    public function __construct(
        DeleteExpiredInterface $service,
        protected ServiceManager $serviceManager,
        ?string $name = null
    ) {
        parent::__construct($service, $name);
    }

    /**
     * Run some processes after expiring.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return void
     */
    protected function postProcess(InputInterface $input, OutputInterface $output): void
    {
        $batchSize = $input->getOption('batch');
        $sleepTime = $input->getOption('sleep');
        foreach ($this->getSessionDatabaseServices() as $serviceName => $serviceClass) {
            $service = $this->serviceManager->get($serviceClass);

            // Delete the rows without related session.
            $total = 0;
            do {
                $count = $service->deleteExpired($batchSize);
                if ($count > 0) {
                    $output->writeln(
                        $this->getTimestampedMessage("$count {$serviceName} deleted.")
                    );
                    $total += $count;
                    // Be nice to others and wait between batches
                    usleep($sleepTime * 1000);
                }
            } while ($count > 0);

            $output->writeln(
                $this->getTimestampedMessage("Total $total {$serviceName} deleted.")
            );
        }
    }
}
