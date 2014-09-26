<?php
/**
 * Generic access check assertion for VuFind.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Assertions
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Assertion;
use Zend\Http\PhpEnvironment\Request;
use ZfcRbac\Assertion\AssertionInterface;
use ZfcRbac\Service\AuthorizationService;

/**
 * Generic access check assertion for VuFind.
 *
 * @category VuFind2
 * @package  Assertions
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class HasAccessAssertion implements AssertionInterface
{
    /**
     * Configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param array   $config  Configuration array indicating which methods of access
     * should be permitted (legal keys = ipRegEx, a regular expression for verifying
     * IP addresses; userWhitelist, a list of legal users)
     * @param Request $request Request object
     */
    public function __construct(array $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Check if this assertion is true
     *
     * @param AuthorizationService $authorization Authorization service
     * @param mixed                $context       Context variable
     *
     * @return bool
     */
    public function assert(AuthorizationService $authorization, $context = null)
    {
        // If an IP regex is set, check if the current IP matches.
        if (isset($this->config['ipRegEx'])) {
            $ipMatch = preg_match(
                $this->config['ipRegEx'],
                $this->request->getServer()->get('REMOTE_ADDR')
            );
            if (!$ipMatch) {
                return false;
            }
        }

        // If a user whitelist is set, check if the user is on it.
        if (isset($this->config['userWhitelist'])) {
            $user = $authorization->getIdentity();
            if (!$user
                || !in_array($user->username, $this->config['userWhitelist'])
            ) {
                return false;
            }
        }

        // If we got this far, there were no failed checks.
        return true;
    }
}
