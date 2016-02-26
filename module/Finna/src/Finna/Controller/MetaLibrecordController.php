<?php
/**
 * MetaLib Record Controller
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
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * MetaLib Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MetaLibrecordController extends \VuFind\Controller\AbstractRecord
{
    use RecordControllerTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Override some defaults:
        $this->searchClassId = 'MetaLib';

        // Call standard record controller initialization:
        parent::__construct();
    }
    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        $view = parent::showTab($tab, $ajax);

        $memory = $this->getServiceLocator()->get('VuFind\Search\Memory');
        if ($last = $memory->retrieve()) {
            $parts = parse_url($last);
            if (isset($parts['query'])) {
                parse_str($parts['query'], $params);
                if (isset($params['set'])) {
                    $view->metalibSet = $params['set'];
                }
            }
        }
        return $view;
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('Primo');
        return (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);
    }
}
