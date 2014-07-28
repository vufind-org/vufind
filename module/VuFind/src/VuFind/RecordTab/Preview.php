<?php
/**
 * Embedded Preview tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Embedded Preview tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class Preview extends AbstractBase
{
    /**
     * Configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Is this tab active?
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Initial visibility
     *
     * @var bool
     */
    protected $visible = true;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config Configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->config = $config;
        // currently only active if config [content] [previews] contains google and googleoptins[tab] is not empty.
        $content_previews = explode(',', strtolower(str_replace(' ', '', $this->config->Content->previews)));
        if (in_array('google', $content_previews)
              && isset($this->config->Content->GoogleOptions)) {
            $g_options = $this->config->Content->GoogleOptions;
            if (isset($g_options->tab)) {
                $tabs = explode(',', $g_options->tab);
                if (count($tabs) > 0) {
                    $this->active = true;
                }
            }
        }
        // initially invisible if listed in config [hide_if_empty] contains embedded_previews
        if ($this->active) {
            $hide_if_empty = explode(',', strtolower($this->config->Content->hide_if_empty));
            if (in_array('embedded_previews', $hide_if_empty)) {
                $this->visible = false;
            }
        }
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Preview';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Is this tab initially visible?
     *
     * @return bool
     */
    public function isVisible()
    {
        return $this->visible;
    }
}
