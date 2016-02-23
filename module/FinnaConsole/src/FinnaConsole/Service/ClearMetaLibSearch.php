<?php
/**
 * Console service for clearing expired MetaLib searches.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @category VuFind2
 * @package  Service
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use Zend\Db\Sql\Select;

/**
 * Console service for clearing expired MetaLib searches.
 *
 * @category VuFind2
 * @package  Service
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ClearMetaLibSearch extends AbstractService implements ConsoleServiceInterface
{
    protected $table = null;

    /**
     * Constructor
     *
     * @param VuFind\Db\Table $table MetaLibSearch table.
     */
    public function __construct($table)
    {
        $this->table = $table;
    }

    /**
     * Run service.
     *
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        if (!isset($arguments[0]) || (int) $arguments[0] < 1) {
            echo "Usage:\n  php index.php util clear_metalib_search <m>\n\n"
                . "  Removes all metalib searches from the database that are older\n"
                . "  than <m> minutes.\n";
            return false;
        }

        $count = 0;

        foreach ($this->getExpiredMetalibSearches($arguments[0]) as $row) {
            $row->delete();
            $count++;
        }

        if ($count === 0) {
            $this->msg("There were no expired MetaLib searches to remove");
        } else {
            $this->msg("$count expired MetaLib searches were removed");
        }

        return true;
    }

    /**
     * Returns expired MetaLib searches
     *
     * @param int $minutes Number of minutes the searches are considered live
     *
     * @return MetaLibSearch[] Traversable list of expired MetaLib searches
     */
    protected function getExpiredMetalibSearches($minutes)
    {
        $expires = date(
            'Y-m-d H:i:s',
            strtotime(sprintf('-%d minutes', (int) $minutes))
        );

        return $this->table->select(
            function (Select $select) use ($expires) {
                $select->where->lessThan('created', $expires);
            }
        );
    }
}
