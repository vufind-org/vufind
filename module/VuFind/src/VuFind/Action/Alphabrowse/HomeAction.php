<?php

/**
 * AlphaBrowse home action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */

namespace VuFind\Action\Alphabrowse;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Exception\BadRequest;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindSearch\Command\AlphabeticBrowseCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Service as SearchService;

use function in_array;
use function intval;

/**
 * AlphaBrowse home action.
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
class HomeAction extends AbstractTemplateRenderingAction
{
    /**
     * The name of the backend providing alphabrowse services.
     *
     * @var string
     */
    protected $alphabrowseBackend = 'Solr';

    /**
     * Default browse types.
     *
     * @var array
     */
    protected $defaultTypes = [
        'topic'  => 'By Topic',
        'author' => 'By Author',
        'title'  => 'By Title',
        'lcc'    => 'By Call Number',
    ];

    /**
     * Default extras.
     *
     * @var array
     */
    protected $defaultExtras = [
        'title' => 'author:format:publishDate',
        'lcc' => 'title',
        'dewey' => 'title',
    ];

    /**
     * Constructor.
     *
     * @param SearchService $searchService Search service
     * @param array         $config        VuFind configuration
     */
    public function __construct(
        protected SearchService $searchService,
        #[Autowire(config: 'config')] protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Display a particular tab.
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
        // Load config parameters:
        $config = $this->config;
        $rowsBefore = ctype_digit((string)($config['AlphaBrowse']['rows_before'] ?? '-'))
            ? (int)$config['AlphaBrowse']['rows_before'] : 0;
        $limit  = ctype_digit((string)($config['AlphaBrowse']['page_size'] ?? '-'))
            ? (int)$config['AlphaBrowse']['page_size'] : 20;

        // Process incoming parameters:
        $source = $this->getQueryParam('source', false);
        $from   = $this->getQueryParam('from', false);
        $page   = intval($this->getQueryParam('page', 0));

        // Load highlighting configuration while accounting for special case:
        // highlighting is pointless if there's no user input:
        $highlighting = empty($from) ? false : $config['AlphaBrowse']['highlighting'] ?? false;

        // Set up any extra parameters to pass
        $extras = $this->getExtras($config);

        // Create template params:
        $templateParams = [
            'alphaBrowseTypes' => $this->getTypes($config),
            'from' => $from,
            'source' => $source,
            'extras' => array_filter(explode(':', $extras[$source] ?? '')),
        ];

        // If required parameters are present, load results:
        $templateParams = $this->addResultsToTemplateParams(
            $templateParams,
            $page,
            $limit,
            $rowsBefore,
            $highlighting,
            $extras
        );

        // Render results:
        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Get browse types from config file, or use defaults if unavailable.
     *
     * @param array $config Configuration
     *
     * @return array
     */
    protected function getTypes(array $config): array
    {
        return empty($config['AlphaBrowse_Types']) ? $this->defaultTypes : $config['AlphaBrowse_Types'];
    }

    /**
     * Load any extras from config file, or use defaults if unavailable.
     *
     * @param array $config Configuration
     *
     * @return array
     */
    protected function getExtras(array $config): array
    {
        return $config['AlphaBrowse_Extras'] ?? $this->defaultExtras;
    }

    /**
     * Add alphabrowse results to the template params.
     *
     * @param array $templateParams Template params (must already contain source and from values)
     * @param int   $page           Results page to load
     * @param int   $limit          Page size
     * @param int   $rowsBefore     Number of rows to display before highlighted row
     * @param bool  $highlighting   Is row highlighting enabled?
     * @param array $extras         Extra fields to load in results
     *
     * @return array
     */
    protected function addResultsToTemplateParams(
        array $templateParams,
        int $page,
        int $limit,
        int $rowsBefore,
        bool $highlighting,
        array $extras
    ): array {
        $result = [];
        $source = $templateParams['source'] ?? null;
        $from = $templateParams['from'] ?? null;
        $alphaBrowseTypes = $templateParams['alphaBrowseTypes'] ?? [];
        if ($source && $from !== false) {
            // Validate source parameter:
            if (!in_array($source, array_keys($alphaBrowseTypes))) {
                throw new BadRequest(
                    "Unsupported alphabrowse type: $source"
                );
            }

            // Set up extra params:
            $extraParams = new ParamBag();
            if (isset($extras[$source])) {
                $extraParams->add('extras', $extras[$source]);
            }

            // Load Solr data or die trying:
            $command = new AlphabeticBrowseCommand(
                $this->alphabrowseBackend,
                $source,
                $from,
                $page,
                $limit,
                $extraParams,
                0 - $rowsBefore
            );
            $result = $this->searchService->invoke($command)->getResult();

            // No results? Try the previous page just in case we've gone past the end of the list....
            if ($result['Browse']['totalCount'] == 0) {
                $page--;
                $command->setPage($page)
                    ->setOffsetDelta(0);
                $result = $this->searchService->invoke($command)->getResult();
                if ($highlighting) {
                    $templateParams['highlight_end'] = true;
                }
            }

            // Only display next/previous page links when applicable:
            if ($result['Browse']['totalCount'] > $limit) {
                $templateParams['nextpage'] = $page + 1;
            }
            if ($result['Browse']['offset'] + $result['Browse']['startRow'] > 1) {
                $templateParams['prevpage'] = $page - 1;
            }
        }

        if ($source === 'topic') {
            $result = $this->applyTopicDelimiters($result);
        }

        $templateParams['result'] = $result;

        // set up highlighting: page 0 contains match location
        if ($highlighting && $page == 0 && isset($result['Browse'])) {
            $templateParams = $this->applyHighlighting($templateParams, $rowsBefore);
        }

        return $templateParams;
    }

    /**
     * Applies topic delimiters to the 'heading' field of each item in the browse results.
     *
     * @param array $result The result array containing 'Browse' items to be modified.
     *
     * @return array
     */
    protected function applyTopicDelimiters(array $result): array
    {
        foreach ($result['Browse']['items'] as &$item) {
            $item['heading'] = str_replace(
                "\u{2002}",
                ($this->config['AlphaBrowse']['topic_browse_separator'] ?? ' > '),
                $item['heading']
            );
        }
        unset($item);

        return $result;
    }

    /**
     * Apply highlighting settings to the template params based on the result set.
     *
     * @param array $templateParams Template params to be updated (must already contain results)
     * @param int   $rowsBefore     Number of rows to display before highlighted row
     *
     * @return array
     */
    protected function applyHighlighting(array $templateParams, int $rowsBefore): array
    {
        $browseResult = $templateParams['result']['Browse'];
        $startRow = $browseResult['startRow'];
        // solr counts rows from 1; adjust to array position style
        $startRow_adj = $startRow - 1;
        $offset = $browseResult['offset'];
        $totalRows = $browseResult['totalCount'];
        $totalRows += $startRow + $offset > 0 ? $startRow_adj + $offset : 0;

        // normal case: somewhere in the middle of the browse list
        $highlight_row = $rowsBefore;
        // special case: match row is < rowsBefore (i.e. at beginning of list)
        if ($startRow_adj < $rowsBefore) {
            $highlight_row = $startRow_adj;
        }
        // special case: we've gone past the end
        // only the rowsBefore records will have been returned
        if ($startRow > $totalRows) {
            $templateParams['highlight_end'] = true;
        }
        $templateParams['highlight_row'] = $highlight_row;
        $templateParams['match_type'] = $browseResult['matchType'];

        return $templateParams;
    }
}
