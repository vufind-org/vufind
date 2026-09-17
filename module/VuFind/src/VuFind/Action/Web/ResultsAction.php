<?php

/**
 * SolrWeb results action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Web;

use Psr\Http\Message\ResponseInterface;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Search\Base\Results;

/**
 * SolrWeb results action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResultsAction extends \VuFind\Action\Search\ResultsAction
{
    /**
     * Process the jumpto parameter -- either redirect to a specific record, or ignore the parameter and return null.
     *
     * @param Results $results Search results object.
     *
     * @return ?ResponseInterface
     */
    protected function processJumpTo(Results $results): ?ResponseInterface
    {
        // Missing/invalid parameter?  Ignore it:
        $jumpto = $this->getQueryParam('jumpto');
        if (empty($jumpto) || !is_numeric($jumpto)) {
            return null;
        }

        // Parameter out of range?  Ignore it:
        $recordList = $results->getResults();
        if (!isset($recordList[$jumpto - 1])) {
            return null;
        }

        // If we got this far, we have a valid parameter so we should redirect
        // and report success:
        $url = $recordList[$jumpto - 1]->getUrl();
        return $url ? $this->getHelper(RedirectHelper::class)->redirectToUrl($this->response, $url) : null;
    }
}
