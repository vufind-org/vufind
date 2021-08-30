<?php

/**
 * Set the backend's record collection factory.
 *
 * PHP version 7
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
 * @author   David Maus <maus@hab.de>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SetRecordCollectionFactoryCommand extends CallMethodCommand
{
    /**
     * Constructor.
     *
     * @param string                           $backend Search backend identifier
     * @param RecordCollectionFactoryInterface $factory Factory to set
     */
    public function __construct(
        string $backend,
        RecordCollectionFactoryInterface $factory
    ) {
        parent::__construct(
            $backend,
            AbstractBackend::class,
            'setRecordCollectionFactory',
            [$factory],
            null,
            false
        );
    }
}
