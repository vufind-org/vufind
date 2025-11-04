<?php

/**
 * Abstract base class for a command that updates records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserServiceInterface;

/**
 * Abstract base class for a command that updates records.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractRecordUpdateCommand extends Command
{
    /**
     * Table display name
     *
     * @var string
     */
    protected $tableName = null;

    /**
     * Command description
     *
     * @var string
     */
    protected $description = null;

    /**
     * Constructor
     *
     * @param UserServiceInterface|UserListServiceInterface $dbService Database service
     */
    public function __construct(protected UserServiceInterface|UserListServiceInterface $dbService)
    {
        if (null === $this->tableName) {
            throw new \Exception('tableName empty');
        }
        if (null === $this->description) {
            throw new \Exception('description empty');
        }
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
            ->setDescription($this->description)
            ->addArgument(
                'ids',
                InputArgument::REQUIRED,
                "A comma-separated list of {$this->tableName} id's to process"
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
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $count = 0;
        foreach (explode(',', $input->getArgument('ids')) as $id) {
            if ($this->dbService instanceof UserListServiceInterface) {
                $record = $this->dbService->getUserListById($id);
            } elseif ($this->dbService instanceof UserServiceInterface) {
                $record = $this->dbService->getUserById($id);
            } else {
                throw new \Exception('Unexpected database service class');
            }
            if ($record) {
                if ($this->changeRecord($record)) {
                    $this->dbService->persistEntity($record);
                    ++$count;
                    $output->writeln("Record $id updated");
                } else {
                    $output->writeln("Record $id already up to date");
                }
            } else {
                $output->writeln("Record $id not found");
            }
        }
        $output->writeln("Total $count {$this->tableName}(s) updated");
        return 0;
    }

    /**
     * Update a record
     *
     * @param EntityInterface $record Record
     *
     * @return bool Whether changes were made
     */
    abstract protected function changeRecord(EntityInterface $record): bool;
}
