<?php
/**
 * VuDL to Fedora connection class (defines some methods to talk to Fedora)
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
namespace VuDL;
use VuFindHttp\HttpServiceInterface;

/**
 * VuDL-Fedora connection class
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class Fedora implements \VuFindHttp\HttpServiceAwareInterface {
    /**
     * VuDL config
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

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
    public function __construct($config) {
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
     * Get Fedora Base URL.
     *
     * @return string
     */
    public function getBase()
    {
        return isset($this->config->Fedora->url_base)
            ? $this->config->Fedora->url_base
            : null;
    }

    /**
     * Get Fedora Page Length.
     *
     * @return string
     */
    public function getPageLength()
    {
        return isset($this->config->Fedora->page_length)
            ? $this->config->Fedora->page_length
            : 16;
    }

    /**
     * Get Fedora Query URL.
     *
     * @return string
     */
    public function getQueryURL()
    {
        return isset($this->config->Fedora->query_url)
            ? $this->config->Fedora->query_url
            : null;
    }

    /**
     * Get Fedora Root ID.
     *
     * @return string
     */
    public function getRootId()
    {
        return isset($this->config->Fedora->root_id)
            ? $this->config->Fedora->root_id
            : null;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to access
     *
     * @return \Zend\Http\Client
     */
    public function getHttpClient($url)
    {
        if ($this->httpService) {
            return $this->httpService->createClient($url);
        }
        return new \Zend\Http\Client($url);
    }

    /**
     * Consolidation of Zend Client calls
     *
     * @param string $query   Query for call
     * @param array  $options Additional options
     *
     * @return Response
     */
    public function query($query, $options = array())
    {
        $data = array(
            'type'  => 'tuples',
            'flush' => false,
            'lang'  => 'itql',
            'format'=> 'Simple',
            'query' => $query
        );
        foreach ($options as $key=>$value) {
            $data[$key] = $value;
        }
        $client = $this->getHttpClient($this->getQueryURL());
        $client->setMethod('POST');
        $client->setAuth($this->config->Fedora->adminUser, $this->config->Fedora->adminPass);
        $client->setParameterPost($data);
        return $client->send();
    }
}
