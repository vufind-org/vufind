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
namespace OCLC;

class User
{

    private $principalID = null;

    private $principalIDNS = null;

    private $authenticatingInstitutionID = null;

    function __construct($authenticatingInstitutionID, $principalID = null, $principalIDNS = null)
    {
        if (empty($authenticatingInstitutionID)) {
            Throw new \BadMethodCallException('You must set a valid authenticating institution ID');
        } elseif (empty($principalID) || empty($principalIDNS)) {
            throw new \BadMethodCallException('You must set a principalID and principalIDNS');
        }
        
        $this->authenticatingInstitutionID = $authenticatingInstitutionID;
        if (! empty($principalID)) {
            $this->principalID = $principalID;
        }
        if (! empty($principalIDNS)) {
            $this->principalIDNS = $principalIDNS;
        }
    }

    function getPrincipalID()
    {
        return $this->principalID;
    }

    function getPrincipalIDNS()
    {
        return $this->principalIDNS;
    }

    function getAuthenticatingInstitutionID()
    {
        return $this->authenticatingInstitutionID;
    }
}
