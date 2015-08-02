<?php
// Copyright 2013 OCLC
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
// http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
namespace OCLC\Auth;

class AuthCode
{

    /**
     *
     * @var string $clientId;
     * @var integer $authenticatingInstitutionId;
     * @var integer $contextInstitutionId;
     * @var string $redirectUri;
     * @var array $scope
     */
    public static $authorizationServer = 'https://authn.sd00.worldcat.org/oauth2';

    private $clientId;

    private $authenticatingInstitutionId;

    private $contextInstitutionId;

    private $redirectUri;

    private $scope;

    /**
     * Construct an Authorization Code object using
     *
     * @param string $client_id            
     * @param integer $authenticatingInstitutionId            
     * @param integer $contextInstitutionId            
     * @param string $redirectUri            
     * @param string $scope            
     */
    public function __construct($client_id, $authenticatingInstitutionId, $contextInstitutionId, $redirectUri, $scope)
    {
        if (empty($client_id)) {
            Throw new \BadMethodCallException('You must pass a valid key to construct an AuthCode');
        } elseif (empty($authenticatingInstitutionId)) {
            Throw new \BadMethodCallException('You must pass an authenticatingInstitutionId');
        } elseif (! (is_int($authenticatingInstitutionId))) {
            Throw new \BadMethodCallException('You must pass a valid integer for the authenticatingInstitutionId');
        } elseif (empty($contextInstitutionId)) {
            Throw new \BadMethodCallException('You must pass a contextInstitutionId');
        } elseif (! (is_int($contextInstitutionId))) {
            Throw new \BadMethodCallException('You must pass a valid integer for the contextInstitutionId');
        } elseif (empty($redirectUri)) {
            Throw new \BadMethodCallException('You must pass a redirectUri');
        } elseif (filter_var($redirectUri, FILTER_VALIDATE_URL) === FALSE) {
            Throw new \BadMethodCallException('You must pass a valid redirectUri');
        } elseif (empty($scope) || ! (is_array($scope))) {
            Throw new \BadMethodCallException('You must pass an array of at least one scope');
        }
        
        $this->clientId = $client_id;
        $this->authenticatingInstitutionId = (int) $authenticatingInstitutionId;
        $this->contextInstitutionId = (int) $contextInstitutionId;
        $this->redirectUri = $redirectUri;
        $this->scope = $scope;
    }

    /**
     * Build the URL for logging user into Authorization Server
     *
     * @return string
     */
    public function getLoginUrl()
    {
        $loginURL = static::$authorizationServer . '/authorizeCode?client_id=' . $this->clientId;
        $loginURL .= '&authenticatingInstitutionId=' . $this->authenticatingInstitutionId . '&contextInstitutionId=' . $this->contextInstitutionId;
        $loginURL .= '&redirect_uri=' . urlencode($this->redirectUri) . '&response_type=code' . '&scope=' . implode($this->scope, ' ');
        return $loginURL;
    }
}
