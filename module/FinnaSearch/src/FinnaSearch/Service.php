<?php

/**
 * Search service.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace FinnaSearch;

use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\ParamBag;
use VuFindSearch\Response\RecordCollectionInterface;

/**
 * Search service.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Service extends \VuFindSearch\Service
{
    /**
     * Return records for work expressions.
     *
     * @param string   $backend  Search backend identifier
     * @param string   $id       Id of record to compare with
     * @param array    $workKeys Work identification keys (optional; retrieved from
     * the record to compare with if not specified)
     * @param ParamBag $params   Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function workExpressions($backend, $id, $workKeys = null,
        ParamBag $params = null
    ) {
        $params  = $params ?: new \VufindSearch\ParamBag();
        $context = __FUNCTION__;
        $args = compact('backend', 'id', 'params', 'context', 'workKeys');
        $backendInstance = $this->resolve($backend, $args);
        $args['backend_instance'] = $backendInstance;

        $this->triggerPre($backendInstance, $args);
        try {
            if (!($backendInstance instanceof Feature\WorkExpressionsInterface)) {
                throw new BackendException(
                    "$backend does not support workExpressions()"
                );
            }
            if (empty($args['workKeys'])) {
                $records = $backendInstance->retrieve($id)->getRecords();
                if (!empty($records[0])) {
                    $fields = $records[0]->getRawData();
                    $workKeys = $fields['work_keys_str_mv'] ?? [];
                }
            }
            $response = $backendInstance->workExpressions($id, $workKeys, $params);
        } catch (BackendException $e) {
            $this->triggerError($e, $args);
            throw $e;
        }
        $this->triggerPost($response, $args);
        return $response;
    }
}
