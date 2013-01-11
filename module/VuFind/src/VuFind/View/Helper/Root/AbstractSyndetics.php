<?php
/**
 * Abstract Syndetics-based view helper.
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
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper;

/**
 * Author Notes view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
abstract class AbstractSyndetics extends AbstractHelper
    implements \VuFindHttp\HttpServiceAwareInterface
{
    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Object representing ISBN
     *
     * @var \VuFind\Code\ISBN
     */
    protected $isbn;

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = \VuFind\Config\Reader::getConfig();
    }

    /**
     * Store an ISBN; return false if it is invalid.
     *
     * @param string $isbn ISBN
     *
     * @return bool
     */
    protected function setIsbn($isbn)
    {
        // We can't proceed without an ISBN:
        if (empty($isbn)) {
            return false;
        }

        $this->isbn = new \VuFind\Code\ISBN($isbn);
        return true;
    }

    /**
     * Attempt to get an ISBN-10; revert to ISBN-13 only when ISBN-10 representation
     * is impossible.
     *
     * @return string
     */
    protected function getIsbn10()
    {
        $isbn = is_object($this->isbn) ? $this->isbn->get10() : false;
        if (!$isbn) {
            $isbn = $this->isbn->get13();
        }
        return $isbn;
    }

    /**
     * Retrieve results for the providers specified.
     *
     * @param string $isbn      ISBN to use for lookup
     * @param string $providers Provider configuration
     *
     * @return array
     */
    protected function getResults($isbn, $providers)
    {
        $results = array();
        if (!$this->setIsbn($isbn)) {
            return $results;
        }

        // Fetch from provider
        $providers = explode(',', $providers);
        foreach ($providers as $provider) {
            $parts = explode(':', trim($provider));
            $provider = strtolower($parts[0]);
            $func = 'load' . ucwords($provider);
            $key = $parts[1];
            try {
                $results[$provider] = method_exists($this, $func)
                    ? $this->$func($key) : false;
                // If the current provider had no valid data, store nothing:
                if (empty($results[$provider])) {
                    unset($results[$provider]);
                }
            } catch (\Exception $e) {
                // Ignore exceptions:
                unset($results[$provider]);
            }
        }

        return $results;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to use
     *
     * @return \Zend\Http\Client
     */
    protected function getHttpClient($url)
    {
        if (null === $this->httpService) {
            throw new \Exception('HTTP service missing.');
        }
        return $this->httpService->createClient($url);
    }

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service HTTP service
     *
     * @return void
     */
    public function setHttpService(\VuFindHttp\HttpServiceInterface $service)
    {
        $this->httpService = $service;
    }

    /**
     * This method is responsible for retrieving data from Syndetics.
     *
     * @param string $id     Client access key
     * @param bool   $s_plus Are we operating in Syndetics Plus mode?
     *
     * @throws \Exception
     * @return array
     */
    abstract protected function loadSyndetics($id, $s_plus = false);

    /**
     * Wrapper around syndetics to provide Syndetics Plus functionality.
     *
     * @param string $id Client access key
     *
     * @throws \Exception
     * @return array
     */
    protected function loadSyndeticsplus($id) 
    {
        return $this->loadSyndetics($id, true);
    }
}