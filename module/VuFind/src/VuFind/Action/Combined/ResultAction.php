<?php

/**
 * Combined search result action.
 *
 * PHP version 8
 *
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Combined;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Log\LoggerAwareTrait;

/**
 * Combined search result action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResultAction extends AbstractCombinedSearchAndResultsAction implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Get a single result list (used for AJAX-loaded results).
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->disableSessionWrites();  // avoid session write timing bug

        // Turn off search memory -- not relevant in this context:
        $this->searchMemory->disable();

        // Validate configuration:
        $sectionId = $this->getQueryParam('id');
        $combinedOptions = $this->searchOptionsPluginManager->get('combined');
        $tabConfig = $combinedOptions->getTabConfig();
        if (!isset($tabConfig[$sectionId])) {
            throw new \Exception('Illegal ID');
        }
        [$searchClassId] = explode(':', $sectionId);

        // Retrieve results:
        $currentOptions = $this->searchOptionsPluginManager->get($searchClassId);
        $currentSearch = $tabConfig[$sectionId];

        $adjustedRequest = $this->adjustQueryForSettings(
            $request,
            $currentSearch,
            $currentOptions->getHandlerForLabel($this->getQueryParam('type'))
        );
        // Perform search and get template params:
        $currentSearch['templateParams'] = $this->getSearchResultsTemplateParams($adjustedRequest, $searchClassId);
        // Save to history for hidden filters etc:
        $this->saveSearchToHistory($currentSearch['templateParams']['results']);

        // Should we suppress content due to emptiness?
        if (
            ($currentSearch['hide_if_empty'] ?? false)
            && $currentSearch['templateParams']['results']->getResultTotal() <= 0
        ) {
            $html = '';
        } else {
            $templateParams = [
                'ajax' => true,
                'searchClassId' => $searchClassId,
                'currentSearch' => $currentSearch,
                'domId' => 'combined_' . str_replace(':', '____', $sectionId),
            ];
            if ($extraErrors = $currentSearch['templateParams']['extraErrors'] ?? null) {
                $templateParams['extraErrors'] = $extraErrors;
            }
            // Render content:
            $html = $this->getTemplateRenderer()->renderTemplateAsString(
                $adjustedRequest,
                'combined/results-list.phtml',
                $templateParams
            );
        }
        return $this->getHelper(ResponseHelper::class)->getAjaxResponse($response, 'text/html', $html);
    }
}
