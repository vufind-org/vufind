<?php
namespace IxTheo\Controller;

use Laminas\View\Model\ViewModel;
use VuFindSearch\ParamBag;

class AlphabrowseController extends \VuFind\Controller\AlphabrowseController
{
    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction(): ViewModel
    {
        $config = $this->getConfig();

        // Load browse types from config file, or use defaults if unavailable:
        if (isset($config->AlphaBrowse_Types)
            && !empty($config->AlphaBrowse_Types)
        ) {
            $types = [];
            foreach ($config->AlphaBrowse_Types as $key => $value) {
                $types[$key] = $value;
            }
        } else {
            $types = [
                'topic'  => 'By Topic',
                'author' => 'By Author',
                'title'  => 'By Title',
                'lcc'    => 'By LCC Call Number'
            ];
        }

        // Load any extras from config file
        $extras = [];
        if (isset($config->AlphaBrowse_Extras)) {
            foreach ($config->AlphaBrowse_Extras as $key => $value) {
                $extras[$key] = $value;
            }
        } else {
            $extras = [
                'title' => 'author:format:publishDate',
                'lcc' => 'title',
                'dewey' => 'title'
            ];
        }

        // Load remaining config parameters
        $rows_before = isset($config->AlphaBrowse->rows_before)
            && is_numeric($config->AlphaBrowse->rows_before)
            ? (int) $config->AlphaBrowse->rows_before : 0;
        $highlighting = isset($config->AlphaBrowse->highlighting)
            ? $config->AlphaBrowse->highlighting : false;
        $limit  = isset($config->AlphaBrowse->page_size)
            && is_numeric($config->AlphaBrowse->page_size)
            ? (int) $config->AlphaBrowse->page_size : 20;

        // Connect to Solr:
        $db = $this->serviceLocator->get('VuFind\Search\BackendManager')
            ->get('Solr');

        // Process incoming parameters:
        $source = $this->params()->fromQuery('source', false);
        $from   = $this->params()->fromQuery('from', false);
        $page   = intval($this->params()->fromQuery('page', 0));

        // Special case: highlighting is pointless if there's no user input:
        if (empty($from)) {
            $highlighting = false;
        }

        // Set up any extra parameters to pass
        $extraParams = new ParamBag();
        if (isset($extras[$source])) {
            $extraParams->add('extras', $extras[$source]);
        }

        // Set up potential filter from Config
        $resultFilter = isset($config->AlphaBrowse_Filter->filter) ? $config->AlphaBrowse_Filter->filter : null;

        // Create view model:
        $view = $this->createViewModel();

        // If required parameters are present, load results:
        if ($source && $from !== false) {
            // Load Solr data or die trying:
            $result = $db->alphabeticBrowse(
                $source, $from, $page, $limit, $extraParams, 0 - $rows_before, $resultFilter
            );

            // No results?    Try the previous page just in case we've gone past
            // the end of the list....
            if ($result['Browse']['totalCount'] == 0) {
                $page--;
                $result = $db->alphabeticBrowse(
                    $source, $from, $page, $limit, $extraParams, 0, $resultFilter
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
            $view->result = $result;
        }

        // set up highlighting: page 0 contains match location
        if ($highlighting && $page == 0) {
            $startRow = $result['Browse']['startRow'];
            // solr counts rows from 1; adjust to array position style
            $startRow_adj = $startRow - 1;
            $offset = $result['Browse']['offset'];
            $totalRows = $result['Browse']['totalCount'];
            $totalRows += $startRow + $offset > 0
                        ? $startRow_adj + $offset : 0;

            // normal case: somewhere in the middle of the browse list
            $highlight_row = $rows_before;
            // special case: match row is < rows_before (i.e. at beginning of list)
            if ($startRow_adj < $rows_before) {
                $highlight_row =  $startRow_adj;
            }
            // special case: we've gone past the end
            // only the rows_before records will have been returned
            if ($startRow > $totalRows) {
                $view->highlight_end = true;
            }
            $view->highlight_row = $highlight_row;
            $view->match_type = $result['Browse']['matchType'];
        }

        $view->alphaBrowseTypes = $types;
        $view->from = $from;
        $view->source = $source;

        // Pass information about extra columns on to theme
        $view->extras = isset($extras[$source])
            ? explode(':', $extras[$source]) : [];

        return $view;
    }
}
