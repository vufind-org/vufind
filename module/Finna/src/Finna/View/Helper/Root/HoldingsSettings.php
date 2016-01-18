<?php
/**
 * Holdings Settings Mode Helper
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
 * @category VuFind2
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Zend\View\Helper\AbstractHelper;

/**
 * Holdings Settings Helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class HoldingsSettings extends AbstractHelper
{
    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct($config = null)
    {
        $this->config = $config;
    }

    /**
     * Return the configured holding details mode
     *
     * @return string
     */
    public function getDetailsMode()
    {
        return empty($this->config->Record['holdings_details'])
            ? 'expand-first'
            : $this->config->Record['holdings_details'];
    }

    /**
     * Return configured thresholds for collapsing holdings.
     *
     * @return array
     */
    public function getCollapseThreshold()
    {
        return empty($this->config->Item_Status->collapse_threshold)
            ? null : $this->config->Item_Status->collapse_threshold->toArray();
    }

    /**
     * Return configured option for showing
     * link to record page in search results holdings.
     *
     * @return boolean
     */
    public function showLinkToRecordPage()
    {
        return empty($this->config->Item_Status->show_link_to_record_page)
            ? false
            : (boolean)$this->config->Item_Status->show_link_to_record_page;
    }

    /**
     * Return configured option for showing
     * place hold button link in search results holdings.
     *
     * @return boolean
     */
    public function showSearchResultsTitleHold()
    {
        return empty($this->config->Item_Status->show_title_hold)
            ? false
            : (boolean)$this->config->Item_Status->show_title_hold;
    }

    /**
     * Return configured setting for showing
     * holdings summariy on record page.
     *
     * @return boolean
     */
    public function showRecordPageSummary()
    {
        return empty($this->config->Item_Status->show_holdings_summary)
            ? false
            : (boolean)$this->config->Item_Status->show_holdings_summary;
    }
}
