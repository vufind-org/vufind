<?php
/**
 * Facebook authentication module.
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
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException;

/**
 * WorldShare authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class WorldShare extends AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface
{
    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * Session container
     *
     * @var \Zend\Session\Container
     */
    protected $session;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->session = new \Zend\Session\Container('WorldCatDiscovery');
        
        if ($config->get('config')->Catalog->driver == 'WMS'){
        	$this->wmsEnabled = true;
        } else {
        	$this->wmsEnabled = false;
        }
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
     * Validate configuration parameters.  This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
		if ($this->config->get('WorldCatDiscovery')){
			$this->wskey = $this->config->get('WorldCatDiscovery')->General->wskey;
			$this->secret = $this->config->get('WorldCatDiscovery')->General->secret;
			$this->institution = $this->config->get('WorldCatDiscovery')->General->institution;
		} elseif ($this->wmsDriverConfig) {
			$this->wskey = $this->WMS['Catalog']['wskey'];
			$this->secret = $this->WMS['Catalog']['secret'];
			$this->institution = $this->WMS['Catalog']['institution'];
		} elseif ($this->config) {
			$this->wskey = $this->config->WorldShare->wskey;
			$this->secret = $this->config->WorldShare->secret;
			$this->institution = $this->config->WorldShare->institution;
		} else {
			throw new Exception('You do not have the proper properties setup in either the WorldCatDiscovery, WMS ini file or WorldShare section in the main config ini file');
		}
    }

    /**
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $code = $request->getQuery()->get('code');
        if (empty($code)) {
            throw new AuthException('authentication_error_admin');
        }
        $accessToken = $this->getAccessTokenFromCode($code);
        if (empty($accessToken)) {
            throw new AuthException('authentication_error_admin');
        }
        
        if ($accessToken->getErrorCode()) {
        	throw new AuthException($accessToken->getErrorCode() . ' ' . $accessToken->getErrorMessage());
        }
        
        if (empty($accessToken->getUser())) {
            throw new AuthException('Access Token does is not associated with a user');
        }

        // If we made it this far, we should log in the user!
        $user = $this->getUserTable()->getByUsername($accessToken->getUser()->getPrincipalID());

        // Save and return the user object:
        $user->save();
        return $user;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
    	$target .= ((strpos($target, '?') !== false) ? '&' : '?')
    	. 'auth_method=WorldShare';
    	$this->session->lastUri = $target;
    	
    	$options = array(
    			'services' => array('WorldCatDiscoveryAPI', 'refresh_token'),
    			'redirect_uri' => urlencode($target)
    	);
    	if ($this->wmsEnabled){
    		$options['services'][] = 'WMS_Availability';
    		$options['services'][] = 'WMS_NCIP';
    	}
    	$this->wskey = new WSKey($this->key, $this->secret, $options);
    	return $wskey->getLoginURL($this->institution, $this->institution);
    }

    /**
     * Obtain an access token from a code.
     *
     * @param string $code Code to look up.
     *
     * @return AccessToken
     */
    protected function getAccessTokenFromCode($code)
    {
        $accessToken = $this->wskey->$wskey->getAccessTokenWithAuthCode($code, $this->institution, $this->institution);
        if ($accessToken->getValue()){
        	$this->session->accessToken = $accessToken;
        }
		return $accessToken;
    }

}
