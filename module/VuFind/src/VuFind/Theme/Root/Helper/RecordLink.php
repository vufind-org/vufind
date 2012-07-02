<?php
/**
 * Record link view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * Record link view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_RecordLink extends Zend_View_Helper_Abstract
{
    /**
     * Get the current object so individual methods can be called.
     *
     * @return VuFind_Theme_Root_Helper_RecordLink
     */
    public function recordLink()
    {
        return $this;
    }

    /**
     * Given an array representing a related record (which may be a bib ID or OCLC
     * number), this helper renders a URL linking to that record.
     *
     * @param array $link   Link information from record model
     * @param bool  $escape Should we escape the rendered URL?
     *
     * @return string       URL derived from link information
     */
    public function related($link, $escape = true)
    {
        switch ($link['type']) {
        case 'bib':
            $url = $this->view->url('record', array('id' => $link['value']));
            break;
        case 'oclc':
            $url = $this->view->url('search-results');
                . '?lookfor=' . urlencode($link['value'])
                . '&type=oclc_num&jumpto=1';
            break;
        default:
            throw new \Exception('Unexpected link type: ' . $link['type']);
        }

        return $escape ? $this->view->escape($url) : $url;
    }

    /**
     * Given a record driver, get a URL for that record.
     *
     * @param VF_RecordDriver_Base $driver Record to link to.
     * @param string               $action Optional record action/tab to access
     *
     * @return string
     */
    public function getUrl($driver, $action = null)
    {
        $params = array('id' => $driver->getUniqueId());
        if (!empty($action)) {
            $params['action'] = $action;
        }
        return $this->view->url($driver->getRecordRoute(), $params);
    }

    /**
     * Given a record driver, generate HTML to link to the record from breadcrumbs.
     *
     * @param VF_RecordDriver_Base $driver Record to link to.
     *
     * @return string
     */
    public function getBreadcrumb($driver)
    {
        return '<a href="' . $this->getUrl($driver) . '">' .
            $this->view->escape($this->view->truncate($driver->getBreadcrumb(), 30))
            . '</a>';
    }
}