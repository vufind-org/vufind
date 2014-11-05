<?php
// Copyright 2014 OCLC
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

namespace WorldCat\Discovery;

use \EasyRdf_Graph;
use \EasyRdf_Resource;
use \EasyRdf_Format;
use \EasyRdf_Namespace;
use \EasyRdf_TypeMapper;

/**
 * A class that represents an issue of a Publication in WorldCat
 *
 */
class PublicationVolume extends EasyRdf_Resource
{
    /**
     * Get the Periodical the Volume is part of 
     * @return EasyRDF_Resource
     */
    function getPeriodical()
    {
        $periodical = $this->getResource('schema:isPartOf');
        return $periodical;
    }
    
    /**
     * Get the Volume Number
     * @return EasyRDF_Literal
     */
    function getVolumeNumber()
    {
        $volumeNumber = $this->get('schema:volumeNumber');
        return $volumeNumber;
    }
}