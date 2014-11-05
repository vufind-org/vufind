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

use \EasyRdf_Resource;
use \EasyRdf_Format;

/**
 * A class that represents a Facet Item in WorldCat
 *
 */
class FacetItem extends EasyRdf_Resource
{
    /**
     * Get name of facet value
     * return EasyRDF_Literal
     */

    function getName(){
        return $this->get('schema:name');
    }

    /**
     * Get count of facet value
     *
     * @return EasyRDF_Literal
     */
    function getCount(){
        return $this->get('discovery:count');
    }
}