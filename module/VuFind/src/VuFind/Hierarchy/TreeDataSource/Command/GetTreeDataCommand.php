<?php

/**
 * Command to fetch tree data from Solr.
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
 * @package  HierarchyTree_DataSource
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFind\Hierarchy\TreeDataSource\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\ParamBag;

/**
 * Command to fetch tree data from Solr.
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetTreeDataCommand extends \VuFindSearch\Command\AbstractBase
{
    /**
     * Get default search parameters shared by cursorMark and legacy methods.
     *
     * @param string $q       Search query
     * @param array  $filters Filter queries
     *
     * @return array
     */
    protected function getDefaultSearchParams(string $q, array $filters): array
    {
        return [
            'q'  => [$q],
            'fq' => $filters,
            'hl' => ['false'],
            'fl' => ['title,id,hierarchy_parent_id,hierarchy_top_id,'
                . 'is_hierarchy_id,hierarchy_sequence,title_in_hierarchy'],
            'wt' => ['json'],
            'json.nl' => ['arrarr'],
        ];
    }

    /**
     * Search Solr using legacy, non-cursorMark method (sometimes needed for
     * backward compatibility, but usually disabled).
     *
     * @param Connector $connector Solr connector
     * @param array     $context   Search context
     *
     * @return array
     */
    protected function searchSolrLegacy(Connector $connector, array $context): array
    {
        $params = new ParamBag(
            $this->getDefaultSearchParams($context['q'], $context['filters']) +
            [
                'rows' => [$context['rows']],
                'start' => [0],
            ]
        );
        $response = $connector->search($params);
        $json = json_decode($response);
        return $json->response->docs ?? [];
    }

    /**
     * Search Solr.
     *
     * @param Connector $connector Solr connector
     * @param array     $context   Search context
     *
     * @return array
     */
    protected function searchSolr(Connector $connector, array $context)
    {
        // Use legacy method if configured to do so:
        if ($context['batchSize'] <= 0) {
            return $this->searchSolrLegacy($connector, $context);
        }

        // By default, use cursorMark method:
        $prevCursorMark = '';
        $cursorMark = '*';
        $records = [];
        while ($cursorMark !== $prevCursorMark) {
            $params = new ParamBag(
                $this->getDefaultSearchParams($context['q'], $context['filters']) + [
                    'rows' => [min([$context['batchSize'], $context['rows']])],
                    // Start is always 0 when using cursorMark
                    'start' => [0],
                    // Sort is required
                    'sort' => ['id asc'],
                    // Override any default timeAllowed since it cannot be used with
                    // cursorMark
                    'timeAllowed' => -1,
                    'cursorMark' => $cursorMark
                ]
            );
            $results = json_decode($connector->search($params));
            if (empty($results->response->docs)) {
                break;
            }
            $records = array_merge($records, $results->response->docs);
            if (count($records) >= $context['rows']) {
                break;
            }
            $prevCursorMark = $cursorMark;
            $cursorMark = $results->nextCursorMark;
        }
        return $records;
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backend Backend
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backend): CommandInterface
    {
        if (!($backend instanceof \VuFindSearch\Backend\Solr\Backend)) {
            throw new \Exception('Unexpected backend: ' . get_class($backend));
        }
        $this->result = $this
            ->searchSolr($backend->getConnector(), $this->getContext());
        return parent::execute($backend);
    }
}
