<?php

/**
 * Extension of Laminas\Validator\Csrf with token counting/clearing functions added.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Validator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Validator;

use function array_slice;
use function count;

/**
 * Extension of Laminas\Validator\Csrf with token counting/clearing functions added.
 *
 * @category VuFind
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SessionCsrf extends \Laminas\Validator\Csrf implements CsrfInterface
{
    /**
     * Keep only the most recent N tokens.
     *
     * @param int $limit Number of tokens to keep.
     *
     * @return void
     */
    public function trimTokenList($limit)
    {
        $session = $this->getSession();
        if ($limit < 1) {
            // Reset the array if necessary:
            $session->tokenList = [];
        } elseif ($limit < $this->getTokenCount()) {
            // Trim the array if necessary:
            $session->tokenList
                = array_slice($session->tokenList, -1 * $limit, null, true);
        }
    }

    /**
     * How many tokens are currently stored in the session?
     *
     * @return int
     */
    public function getTokenCount()
    {
        return count($this->getSession()->tokenList ?? []);
    }
}
