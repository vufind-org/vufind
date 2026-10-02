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
     * Get a redirection response to a single record (or null if a redirect is impossible/inappropriate).
     *
     * @param \VuFind\RecordDriver\AbstractBase $record      Record driver
     * @param array                             $queryParams Any query parameters
     *
     * @return ?ResponseInterface
     */
    protected function getRedirectForRecord(
        \VuFind\RecordDriver\AbstractBase $record,
        array $queryParams = []
    ): ?ResponseInterface {
        $url = $record->tryMethod('getUrl');
        return $url ? $this->getHelper(RedirectHelper::class)->redirectToUrl($this->response, $url) : null;
    }
}
