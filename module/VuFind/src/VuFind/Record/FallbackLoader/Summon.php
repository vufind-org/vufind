<?php

/**
 * Summon record fallback loader
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018, 2022.
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record\FallbackLoader;

use SerialsSolutions\Summon\Laminas as Connector;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\ParamBag;

use function strlen;

/**
 * Summon record fallback loader
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Summon extends AbstractFallbackLoader
{
    /**
     * Record source
     *
     * @var string
     */
    protected $source = 'Summon';

    /**
     * Fetch a single record (null if not found).
     *
     * @param string $id ID to load
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    protected function fetchSingleRecord($id)
    {
        $resource = $this->resourceService->getResourceByRecordId($id, 'Summon');
        if ($resource && ($extra = json_decode($resource->getExtraMetadata(), true))) {
            $bookmark = $extra['bookmark'] ?? '';
            if (strlen($bookmark) > 0) {
                $params = new ParamBag(
                    ['summonIdType' => Connector::IDENTIFIER_BOOKMARK]
                );
                $command = new RetrieveCommand(
                    'Summon',
                    $bookmark,
                    $params
                );
                return $this->searchService->invoke($command)->getResult();
            }
        }
        return new \VuFindSearch\Backend\Summon\Response\RecordCollection([]);
    }
}
