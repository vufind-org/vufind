<?php
/**
 * VuDL connection base class (defines some methods to talk to VuDL sources)
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
namespace VuDL\Connection;
use VuFindHttp\HttpServiceInterface,
    VuFindSearch\ParamBag;

/**
 * VuDL connection base class
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    /**
     * VuDL config
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Parent List data cache
     *
     * @var array
     */
    protected $parentLists = array();
    
    /**
     * HTTP service
     *
     * @var HttpServiceInterface
     */
    protected $httpService = false;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }
    
    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service HTTP service
     *
     * @return void
     */
    public function setHttpService(HttpServiceInterface $service)
    {
        $this->httpService = $service;
    }

    /**
     * Get root id from config
     *
     * @return string
     */
    protected function getRootId()
    {
        return isset($this->config->General->root_id)
            ? $this->config->General->root_id
            : null;
    }
    
    /**
     * Get VuDL detail fields.
     *
     * @return array
     */
    protected function getDetailsList()
    {
        return isset($this->config->Details)
            ? $this->config->Details->toArray()
            : array();
    }
    
    /**
     * Get Fedora Page Length.
     *
     * @return string
     */
    public function getPageLength()
    {
        return isset($this->config->General->page_length)
            ? $this->config->General->page_length
            : 16;
    }

    /**
     * Format details properly into the correct keys
     *
     * @param array $record
     *
     * @return string
     */
    protected function formatDetails($record)
    {
        // Format details
        // Get config for which details we want
        $fields = $combinedFields = array(); // Save to combine later
        $detailsList = $this->getDetailsList();
        if (empty($detailsList)) {
            throw new \Exception('Missing [Details] in VuDL.ini');
        }
        foreach ($detailsList as $key=>$title) {
            $keys = explode(',', $key);
            foreach ($keys as $k) {
                $fields[$k] = $title;
            }
            // Link up to top combined field
            if (count($keys) > 1) {
                $combinedFields[] = $keys;
            }
        }
        // Pool details
        $details = array();
        foreach ($fields as $key=>$title) {
            if (isset($record[$key])) {
                $details[$key] = array('title' => $title, 'value' => $record[$key]);
            }
        }
        // Rearrange combined fields
        foreach ($combinedFields as $fields) {
            $main = $fields[0];
            if (!isset($details[$main]['value'])
            || !is_array($details[$main]['value'])
            ) {
                if (isset($details[$main]['value'])) {
                    $details[$main]['value'] = array($details[$main]['value']);
                } else {
                    $details[$main]['value'] = array();
                }
            }
            for ($i=1;$i<count($fields);$i++) {
                if (isset($details[$fields[$i]])) {
                    if (!isset($details[$main]['title'])) {
                        $details[$main]['title'] = $details[$fields[$i]]['title'];
                    }
                    if (is_array($details[$main]['value'])) {
                        foreach ($details[$fields[$i]]['value'] as $value) {
                            $details[$main]['value'][] = $value;
                        }
                    } else {
                        $details[$main]['value'][] = $details[$fields[$i]]['value'];
                    }
                    unset($details[$fields[$i]]);
                }
            }
            if (empty($details[$main]['value'])) {
                unset($details[$main]);
            }
        }
        return $details;
    }
}