<?php

/**
 * Return work expressions command.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019, 2021.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Feature\WorkExpressionsInterface;
use VuFindSearch\ParamBag;

/**
 * Return work expressions command.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WorkExpressionsCommand extends CallMethodCommand
{
    /**
     * WorkExpressionsCommand constructor.
     *
     * @param string    $backend  Search backend identifier
     * @param string    $id       Id of record to compare with
     * @param ?array    $workKeys Work identification keys (optional; retrieved from
     *                            the record to compare with if not specified)
     * @param ?ParamBag $params   Search backend parameters
     */
    public function __construct(string $backend, string $id, ?array $workKeys,
        ?ParamBag $params = null
    ) {
        parent::__construct(
            $backend, WorkExpressionsInterface::class, 'workExpressions',
            [$id, $workKeys], $params
        );
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backendInstance Backend instance
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backendInstance): CommandInterface
    {
        $id = $this->args[0];
        $workKeys = $this->args[1];

        if (empty($workKeys)) {
            $records = $backendInstance->retrieve($id)->getRecords();
            if (!empty($records[0])) {
                $fields = $records[0]->getRawData();
                $this->args[1] = $fields['work_keys_str_mv'] ?? [];
            }
        }

        return parent::execute($backendInstance);
    }
}
