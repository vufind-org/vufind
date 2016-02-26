<?php
/**
 * Finna record controller trait.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;
use Zend\Escaper\Escaper;
/**
 * Finna record controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait RecordControllerTrait
{
    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return \Zend\View\Model\ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);
        $this->modifyLastSearchURL();
        return $view;
    }

    /**
     * Append record id as a hash to the last search URL.
     * This way the previus window scroll position gets restored
     * when the user returns to search results from a record page.
     *
     * @return void
     */
    protected function modifyLastSearchURL()
    {
        $memory  = $this->getServiceLocator()->get('VuFind\Search\Memory');

        if ($last = $memory->retrieve()) {
            $parts = parse_url($last);
            // Do not overwrite existing hash
            if (!isset($parts['fragment'])) {
                $escaper = new Escaper('utf-8');
                $id = $this->driver->getUniqueId();
                $id = $escaper->escapeUrl($id);
                $last .= "#$id";
                $memory->rememberSearch($last);
            }
        }
    }
}
