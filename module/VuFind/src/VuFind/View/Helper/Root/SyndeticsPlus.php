<?php
/**
 * SyndeticsPlus view helper
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
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\View\Helper\Root;

/**
 * SyndeticsPlus view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SyndeticsPlus extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Syndetics configuration
     *
     * \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config Syndetics configuration (should contain
     * 'plus' boolean value (true if Syndetics Plus is enabled) and 'plus_id' string
     * value (Syndetics Plus user ID).  If these values are absent, SyndeticsPlus
     * will be disabled.
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Is SyndeticsPlus active?
     *
     * @return bool
     */
    public function isActive()
    {
        return isset($this->config->plus)
            ? $this->config->plus : false;
    }

    /**
     * Get the SyndeticsPlus Javascript loader.
     *
     * @return string
     */
    public function getScript()
    {
        // Determine whether to include script tag for SyndeticsPlus
        if (isset($this->config->plus_id)) {
            $baseUrl = (isset($this->config->use_ssl) && $this->config->use_ssl)
                ? 'https://secure.syndetics.com' : 'http://plus.syndetics.com';
            return $baseUrl . "/widget.php?id="
                . urlencode($this->config->plus_id);
        }

        return null;
    }
}
