<?php
/**
 * Solr Exception
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Exceptions
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Exception;

/**
 * Solr Exception
 *
 * @category VuFind2
 * @package  Exceptions
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 *
 * @todo     Remove, superseded by VuFindSearch model exception
 */
class Solr extends \Exception
{
    /**
     * Is this exception caused by a Solr parse error?
     *
     * @return bool
     */
    public function isParseError()
    {
        $error = $this->getMessage();
        if (stristr($error, 'org.apache.lucene.queryParser.ParseException')
            || stristr($error, 'undefined field')
        ) {
            return true;
        }
        return false;
    }

    /**
     * Is this exception caused by a missing browse index?
     *
     * @return bool
     */
    public function isMissingBrowseIndex()
    {
        $error = $this->getMessage();
        if (strstr($error, 'does not exist') || strstr($error, 'no such table')
            || strstr($error, 'couldn\'t find a browse index')
        ) {
            return true;
        }
        return false;
    }
}