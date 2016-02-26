<?php
/**
 * Favorites aspect of the Search Multi-class (Options)
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Search_Favorites
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Favorites;
use Finna\Controller\MyResearchController;

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
    /**
     * Constructor
     * Add the limit and views options to Favorites.
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $searchSettings = $configLoader->get($this->searchIni);
        if (isset($searchSettings->General->default_limit)) {
            $this->defaultLimit = $searchSettings->General->default_limit;
        }
        if (isset($searchSettings->General->limit_options)) {
            $this->limitOptions
                = explode(",", $searchSettings->General->limit_options);
        }
        // Load view preferences (or defaults if none in .ini file):
        if (isset($searchSettings->Views)) {
            foreach ($searchSettings->Views as $key => $value) {
                $this->viewOptions[$key] = $value;
            }
        } elseif (isset($searchSettings->General->default_view)) {
            $this->viewOptions = [$this->defaultView => $this->defaultView];
        } else {
            $this->viewOptions = ['list' => 'List'];
        }

        $this->sortOptions = [];
        $this->defaultSort = '';
        foreach (MyResearchController::getFavoritesSortList() as $key => $value) {
            if (empty($this->defaultSort)) {
                $this->defaultSort = $key;
            }
            $this->sortOptions[$key] = $value;
        }
    }

}
