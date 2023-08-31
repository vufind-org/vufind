<?php

/**
 * AlphaBrowse Module Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */

namespace VuFind\Controller;

use Laminas\Config\Config;
use Laminas\View\Model\ViewModel;
use VuFind\Exception\BadRequest;
use VuFindSearch\ParamBag;

use function in_array;
use function intval;

/**
 * AlphabrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
class AlphabrowseController extends AbstractBase
{
    use Feature\AlphaBrowseTrait;

    /**
     * Default browse types
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
     * Default extras
     *
     * @var array
     */
    protected $defaultExtras = [
        'title' => 'author:format:publishDate',
        'lcc' => 'title',
        'dewey' => 'title',
    ];

    /**
     * Get browse types from config file, or use defaults if unavailable.
     *
     * @param Config $config Configuration
     *
     * @return array
     */
    protected function getTypes(Config $config): array
    {
        return empty($config->AlphaBrowse_Types)
            ? $this->defaultTypes
            : $config->AlphaBrowse_Types->toArray();
    }

    /**
     * Load any extras from config file, or use defaults if unavailable.
     *
     * @param Config $config Configuration
     *
     * @return array
     */
    protected function getExtras(Config $config): array
    {
        return isset($config->AlphaBrowse_Extras)
            ? $config->AlphaBrowse_Extras->toArray()
            : $this->defaultExtras;
    }

    /**
     * Add alphabrowse results to the view model.
     *
     * @param ViewModel $view         View model (must already contain source and
     * from values)
     * @param int       $page         Results page to load
     * @param int       $limit        Page size
     * @param int       $rowsBefore   Number of rows to display before highlighted
     * row
     * @param bool      $highlighting Is row highlighting enabled?
     * @param array     $extras       Extra fields to load in results
     *
     * @return void
     */
    protected function addResultsToView(
        ViewModel $view,
        int $page,
        int $limit,
        int $rowsBefore,
        bool $highlighting,
        array $extras
    ): void {
        $result = [];
        if ($view->source && $view->from !== false) {
            // Validate source parameter:
            if (!in_array($view->source, array_keys($view->alphaBrowseTypes))) {
                throw new BadRequest(
                    "Unsupported alphabrowse type: {$view->source}"
                );
            }

            // Set up extra params:
            $extraParams = new ParamBag();
            if (isset($extras[$view->source])) {
                $extraParams->add('extras', $extras[$view->source]);
            }

            // Load Solr data or die trying:
            $result = $this->alphabeticBrowse(
                $view->source,
                $view->from,
                $page,
                $limit,
                $extraParams,
                0 - $rowsBefore
            );

            // No results?    Try the previous page just in case we've gone past
            // the end of the list....
            if ($result['Browse']['totalCount'] == 0) {
                $page--;
                $result = $this->alphabeticBrowse(
                    $view->source,
                    $view->from,
                    $page,
                    $limit,
                    $extraParams,
                    0
                );
                if ($highlighting) {
                    $view->highlight_end = true;
                }
            }

            // Only display next/previous page links when applicable:
            if ($result['Browse']['totalCount'] > $limit) {
                $view->nextpage = $page + 1;
            }
            if ($result['Browse']['offset'] + $result['Browse']['startRow'] > 1) {
                $view->prevpage = $page - 1;
            }
        }
        $view->result = $result;

        // set up highlighting: page 0 contains match location
        if ($highlighting && $page == 0 && isset($view->result['Browse'])) {
            $this->applyHighlighting($view, $rowsBefore);
        }
    }

    /**
     * Apply highlighting settings to the view based on the result set.
     *
     * @param ViewModel $view       View model to be updated (must already contain
     * results)
     * @param int       $rowsBefore Number of rows to display before highlighted row
     *
     * @return void
     */
    protected function applyHighlighting(ViewModel $view, int $rowsBefore): void
    {
        $startRow = $view->result['Browse']['startRow'];
        // solr counts rows from 1; adjust to array position style
        $startRow_adj = $startRow - 1;
        $offset = $view->result['Browse']['offset'];
        $totalRows = $view->result['Browse']['totalCount'];
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
            $view->highlight_end = true;
        }
        $view->highlight_row = $highlight_row;
        $view->match_type = $view->result['Browse']['matchType'];
    }

    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return ViewModel
     */
    public function homeAction(): ViewModel
    {
        // Load config parameters
        $config = $this->getConfig();
        $rowsBefore = ctype_digit((string)($config->AlphaBrowse->rows_before ?? '-'))
            ? (int)$config->AlphaBrowse->rows_before : 0;
        $limit  = ctype_digit((string)($config->AlphaBrowse->page_size ?? '-'))
            ? (int)$config->AlphaBrowse->page_size : 20;

        // Process incoming parameters:
        $source = $this->params()->fromQuery('source', false);
        $from   = $this->params()->fromQuery('from', false);
        $page   = intval($this->params()->fromQuery('page', 0));

        // Load highlighting configuration while accounting for special case:
        // highlighting is pointless if there's no user input:
        $highlighting = empty($from)
            ? false : $config->AlphaBrowse->highlighting ?? false;

        // Set up any extra parameters to pass
        $extras = $this->getExtras($config);

        // Create view model:
        $view = $this->createViewModel(
            [
                'alphaBrowseTypes' => $this->getTypes($config),
                'from' => $from,
                'source' => $source,
                'extras' => array_filter(explode(':', $extras[$source] ?? '')),
            ]
        );

        // If required parameters are present, load results:
        $this->addResultsToView(
            $view,
            $page,
            $limit,
            $rowsBefore,
            $highlighting,
            $extras
        );

        return $view;
    }
}
