<?php

/**
 * Favorites aspect of the Search Multi-class (Options)
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Search_Favorites
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Search\Favorites;

use Finna\Controller\MyResearchController;
use VuFind\Config\ConfigManager;

/**
 * Search Favorites Options
 *
 * @category VuFind
 * @package  Search_Favorites
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Options extends \VuFind\Search\Favorites\Options
{
    use \Finna\Search\FinnaOptions;

    /**
     * Constructor
     * Add the limit and views options to Favorites.
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManager $configManager)
    {
        parent::__construct($configManager);

        if ($limit = $this->searchSettings['General']['default_limit'] ?? null) {
            $this->defaultLimit = $limit;
        }
        if ($options = $this->searchSettings['General']['limit_options'] ?? null) {
            $this->limitOptions = $this->explodeListSetting($options);
        }
        // Load view preferences (or defaults if none in .ini file):
        if ($viewOptions = $this->searchSettings['Views'] ?? []) {
            $this->viewOptions = $viewOptions;
        } elseif ($defaultView = $this->getConfiguredDefaultView()) {
            $this->viewOptions = [$defaultView => $defaultView];
        } else {
            $this->viewOptions = ['list' => 'List'];
        }

        $this->sortOptions = [];
        $this->defaultSort = '';
        $this->rssSort = '';
        foreach (MyResearchController::getFavoritesSortList() as $key => $value) {
            if (empty($this->defaultSort)) {
                $this->defaultSort = $key;
            }
            $this->sortOptions[$key] = $value;
        }
    }
}
