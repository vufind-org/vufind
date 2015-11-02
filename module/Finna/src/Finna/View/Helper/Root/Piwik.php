<?php
/**
 * Piwik view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @category VuFind2
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Piwik Web Analytics view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Piwik extends \VuFind\View\Helper\Root\Piwik
{
    /**
     * MetaLib search results
     *
     * @var \Finna\Search\MetaLib\Results
     */
    protected $results = null;

    /**
     * Returns Piwik code (if active) or empty string if not.
     *
     * @param \Finna\Search\MetaLib\Results $results MetaLib search results
     *
     * @return string
     */
    public function __invoke($results = null)
    {
        $this->results = $results;

        $viewModel = $this->getView()->plugin('view_model');
        if ($current = $viewModel->getCurrent()) {
            $children = $current->getChildren();
            if (isset($children[0])
                && isset($children[0]->disablePiwik) && $children[0]->disablePiwik
            ) {
                return '';
            }
        }

        return parent::__invoke();
    }

    /**
     * Get Search Results if on a Results Page
     *
     * @return VuFind\Search\Base\Results|null Search results or null if not
     * on a search page
     */
    protected function getSearchResults()
    {
        return $this->results ?: parent::getSearchResults();
    }
}
