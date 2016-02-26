<?php
/**
 * Solr Utility Functions
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Solr;

/**
 * Solr Utility Functions
 *
 * This class is designed to hold Solr-related support methods that may
 * be called statically.  This allows sharing of some Solr-related logic
 * between the Solr and Summon classes.
 *
 * @category VuFind
 * @package  Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Utils extends \VuFind\Solr\Utils
{
    /**
     * Build date range filter query.
     *
     * @param int    $from  Start year
     * @param int    $to    End year
     * @param string $type  Query type ('overlap' or 'within')
     * @param string $field Index field
     *
     * @return string Query
     */
    public static function buildSpatialDateRangeQuery($from, $to, $type, $field)
    {
        $filter = "[$from TO $to]";

        if ($type) {
            $map = ['within' => 'Within'];
            // overlap => Intersects is default
            $op = 'Intersects';
            if (isset($map[$type])) {
                $op = $map[$type];
            }
            $filter = "{!field f=$field op=$op}$filter";
        }
        return $filter;
    }

    /**
     * Parse "from" and "to" values out of a spatial date range
     * query (or return false if the query is not a range).
     *
     * @param string  $query         Solr query to parse.
     * @param string  $type          Query type ('overlap' or 'within')
     * @param boolean $vufind2Syntax Parse according to VuFind2 filter syntax?
     *
     * @return array|bool   Array with 'from' and 'to' values extracted from range
     * or false if the provided query is not a range.
     */
    public static function parseSpatialDateRange($query, $type, $vufind2Syntax)
    {
        $regex = false;
        if ($vufind2Syntax) {
            // VuFind2 daterange: search_datarange_mv: [1900 TO 2000]
            $regex = '/\[*([\d-]+|\*) TO *([\d-]+|\*)\]/';
        } else {
            // VuFind1 daterange: search_sdaterange_mv
            if ($type == 'overlap') {
                $regex
// @codingStandardsIgnoreStart - long regex
                    = '/[\(\[]\"*[\d-]+\s+([\d-]+)\"*[\s\w]+\"*([\d-]+)\s+[\d-]+\"*[\)\]]/';
// @codingStandardsIgnoreEnd
            } elseif ($type == 'within') {
                $regex
// @codingStandardsIgnoreStart - long regex
                    = '/[\(\[]\"*([\d-]+\.?\d*)\s+[\d-]+\"*[\s\w]+\"*[\d-]+\s+([\d-]+\.?\d*)\"*[\)\]]/';
// @codingStandardsIgnoreEnd
            }
        }

        if (!$regex || !preg_match($regex, $query, $matches)) {
            return false;
        }

        $from = $matches[1];
        $to = $matches[2];

        if (!$vufind2Syntax) {
            if ($type == 'within') {
                // Adjust date range end points to match original search query
                $from += 0.5;
                $to -= 0.5;
            }

            $from = $from * 86400;
            $from = new \DateTime("@{$from}");
            $from = $from->format('Y');

            $to = $to * 86400;
            $to = new \DateTime("@{$to}");
            $to = $to->format('Y');
        }

        return ['from' => $from, 'to' => $to];
    }
}
