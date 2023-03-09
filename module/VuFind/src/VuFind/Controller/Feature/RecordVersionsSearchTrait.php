<?php

/**
 * VuFind Action Feature Trait - Record Versions Search
 * Depends on method getSearchResultsView and record driver's method getWorkKeys.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

/**
 * VuFind Action Feature Trait - Record Versions Search
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait RecordVersionsSearchTrait
{
    /**
     * Show results of versions search.
     *
     * @return mixed
     */
    public function versionsAction()
    {
        $id = $this->params()->fromQuery('id');
        $keys = $this->params()->fromQuery('keys');
        $record = null;
        if ($id) {
            $loader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
            $record = $loader->load($id, $this->searchClassId, true);
            if ($record instanceof \VuFind\RecordDriver\Missing) {
                $record = null;
            } else {
                $keys = $record->tryMethod('getWorkKeys');
            }
        }

        if (empty($keys)) {
            return $this->forwardTo('Search', 'Home');
        }

        $mapFunc = function ($val) {
            return '"' . addcslashes($val, '"') . '"';
        };

        $query = $this->getRequest()->getQuery();
        $query->lookfor = implode(' OR ', array_map($mapFunc, (array)$keys));
        $query->type = 'WorkKeys';

        // Don't save to history -- history page doesn't handle correctly:
        $this->saveToHistory = false;

        $callback = function ($runner, $params, $searchId) {
            $defaultCallback = is_callable([$this, 'getSearchSetupCallback'])
                ? $this->getSearchSetupCallback() : null;
            if (is_callable($defaultCallback)) {
                $defaultCallback($runner, $params, $searchId);
            }
            $options = $params->getOptions();
            $options->disableHighlighting();
            $options->spellcheckEnabled(false);
        };

        $view = $this->getSearchResultsView($callback);

        // Customize the URL helper to make sure it builds proper versions URLs
        // (but only do this if we have access to a results object, which we
        // won't in RSS mode):
        if (isset($view->results)) {
            $view->results->getUrlQuery()
                ->setDefaultParameter('id', $id)
                ->setDefaultParameter('keys', $keys)
                ->setSuppressQuery(true);
            $view->driver = $record;
        }

        return $view;
    }
}
