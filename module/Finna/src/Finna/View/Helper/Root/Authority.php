<?php
/**
 * Authority link view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017-2019.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Authority link view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Authority extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Main configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Datasource configuration
     *
     * @var \Zend\Config\Config
     */
    protected $datasourceConfig;

    /**
     * Constructor
     *
     * @param Zend\Config\Config $config           Main configuration
     * @param Zend\Config\Config $datasourceConfig Datasource configuration
     */
    public function __construct(\Zend\Config\Config $config,
        \Zend\Config\Config $datasourceConfig
    ) {
        $this->config = $config;
        $this->datasourceConfig = $datasourceConfig;
    }

    /**
     * Returns HTML for an authority link.
     *
     * @param string                            $url    Regular link URL
     * @param string                            $label  Link label
     * @param string                            $id     Authority id
     * @param string                            $type   Authority type
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return string|null
     */
    public function link($url, $label, $id, $type,
        \VuFind\RecordDriver\AbstractBase $driver
    ) {
        if (empty($this->config->Authority->enabled)) {
            return null;
        }
        $recordSource = $driver->getDatasource();
        if (empty($this->datasourceConfig[$recordSource]['authority'][$type])
            && empty($this->datasourceConfig[$recordSource]['authority']['*'])
        ) {
            return null;
        }
        $authSrc = isset($this->datasourceConfig[$recordSource]['authority'][$type])
            ? $this->datasourceConfig[$recordSource]['authority'][$type]
            : $this->datasourceConfig[$recordSource]['authority']['*'];
        $authorityId = "$authSrc.$id";

        $record = $this->getView()->plugin('record');
        $record = $record($driver);
        $url = $record->getLink($type, $id);
        return $record->renderTemplate(
            'authority-link.phtml',
            [
               'url' => $url, 'label' => $label,
               'id' => $authorityId, 'type' => $type,
               'recordSource' => $recordSource
            ]
        );
    }
}
