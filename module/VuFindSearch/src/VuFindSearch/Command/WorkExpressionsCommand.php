<?php

/**
 * Return work expressions command.
 *
 * PHP version 8
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

use VuFindSearch\Command\Feature\RecordIdentifierTrait;
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
    use RecordIdentifierTrait;

    /**
     * Whether to include the record to compare with in the results
     *
     * @var bool
     */
    protected $includeSelf;

    /**
     * WorkExpressionsCommand constructor.
     *
     * @param string    $backendId   Search backend identifier
     * @param string    $id          Identifier of record to compare with
     * @param bool      $includeSelf Whether to include the record to compare with in the results
     * @param ?ParamBag $params      Search backend parameters
     */
    public function __construct(
        string $backendId,
        string $id,
        bool $includeSelf,
        ?ParamBag $params = null
    ) {
        $this->id = $id;
        $this->includeSelf = $includeSelf;
        parent::__construct(
            $backendId,
            WorkExpressionsInterface::class,
            'workExpressions',
            $params
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getRecordIdentifier(),
            $this->getIncludeSelf(),
            $this->getSearchParameters(),
        ];
    }

    /**
     * Return "include self" setting.
     *
     * @return bool
     */
    public function getIncludeSelf(): bool
    {
        return $this->includeSelf;
    }
}
