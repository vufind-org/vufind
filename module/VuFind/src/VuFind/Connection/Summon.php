<?php
/**
 * Summon Search API Interface (VuFind implementation)
 *
 * PHP version 5
 *
 * Copyright (C) Andrew Nagy 2009.
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
 * @package  Summon
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
namespace VuFind\Connection;
use SerialsSolutions\Summon\Zend2 as BaseSummon,
    VuFind\Solr\Utils as SolrUtils, Zend\Log\LoggerInterface;

/**
 * Summon Search API Interface (VuFind implementation)
 *
 * @category VuFind2
 * @package  Summon
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class Summon extends BaseSummon implements \Zend\Log\LoggerAwareInterface
{
    /**
     * Should boolean operators in the search string be treated as
     * case-insensitive (false), or must they be ALL UPPERCASE (true)?
     *
     * @var bool
     */
    protected $caseSensitiveBooleans = true;

    /**
     * Will we include snippets in responses?
     *
     * @var bool
     */
    protected $snippets = false;

    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Constructor
     *
     * Sets up the Summon API Client
     *
     * @param \Zend\Config\Config $config  Configuration representing Summon.ini
     * @param string              $apiId   Summon API ID
     * @param string              $apiKey  Summon API Key
     * @param array               $options Associative array of additional options;
     * legal keys:
     *    <ul>
     *      <li>authedUser - is the end-user authenticated?</li>
     *      <li>debug - boolean to control debug mode</li>
     *      <li>host - base URL of Summon API</li>
     *      <li>sessionId - Summon session ID to apply</li>
     *      <li>version - API version to use</li>
     *    </ul>
     * @param \Zend\Http\Client   $client  HTTP client
     */
    public function __construct(\Zend\Config\Config $config, $apiId, $apiKey,
        $options = array(), \Zend\Http\Client $client = null
    ) {
        // Store preferred boolean behavior:
        if (!isset($options['caseSensitiveBooleans'])
            && isset($config->General->case_sensitive_bools)
        ) {
            $this->caseSensitiveBooleans = $config->General->case_sensitive_bools;
        } else {
            $this->caseSensitiveBooleans = $options['caseSensitiveBooleans'];
        }

        // Set default snippet behavior if necessary:
        if (isset($config->General->snippets)) {
            $this->snippets = $config->General->snippets;
        }

        if (null !== $client) {
            $timeout = isset($config->General->timeout)
                ? $config->General->timeout : 30;
            $client->setOptions(array('timeout' => $timeout));
        }
        parent::__construct($apiId, $apiKey, $options, $client);
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        // Adjust debug property based on logger settings:
        $this->debug = method_exists($logger, 'debugNeeded')
            ? $logger->debugNeeded() : true;
        $this->logger = $logger;
    }

    /**
     * Print a message if debug is enabled.
     *
     * @param string $msg Message to print
     *
     * @return void
     */
    protected function debugPrint($msg)
    {
        if ($this->debug && $this->logger) {
            $this->logger->debug("$msg\n");
        }
    }

    /**
     * Perform normalization and analysis of Summon return value.
     *
     * @param array $input The raw response from Summon
     *
     * @throws \SerialsSolutions_Summon_Exception
     * @return array       The processed response from Summon
     */
    protected function process($input)
    {
        $result = parent::process($input);

        // Process highlighting/snippets:
        foreach ($result['documents'] as $i => $current) {
            // Remove snippets if not desired:
            if (!$this->snippets) {
                unset($result['documents'][$i]['Snippet']);
            }
        }

        return $result;
    }
}
