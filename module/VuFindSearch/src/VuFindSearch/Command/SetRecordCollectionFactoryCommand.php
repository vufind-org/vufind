<?php

/**
 * Set the backend's record collection factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Command;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

/**
 * Perform a search and return record collection command.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SetRecordCollectionFactoryCommand extends CallMethodCommand
{
    /**
     * Factory to set.
     *
     * @var RecordCollectionFactoryInterface
     */
    protected $factory;

    /**
     * Constructor.
     *
     * @param string                           $backendId Search backend identifier
     * @param RecordCollectionFactoryInterface $factory   Factory to set
     */
    public function __construct(
        string $backendId,
        RecordCollectionFactoryInterface $factory
    ) {
        $this->factory = $factory;
        parent::__construct(
            $backendId,
            AbstractBackend::class,
            'setRecordCollectionFactory'
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [$this->getFactory()];
    }

    /**
     * Return factory to set.
     *
     * @return RecordCollectionFactoryInterface
     */
    public function getFactory(): RecordCollectionFactoryInterface
    {
        return $this->factory;
    }
}
