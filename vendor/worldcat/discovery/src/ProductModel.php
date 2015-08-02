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
 * A class that represents a Product Model in WorldCat
 *
 */
class ProductModel extends EasyRdf_Resource
{
    
    /**
     * Get ISBNs
     *
     * @return EasyRDF_Literal
     */
    function getISBN()
    {
        return $this->get('schema:isbn');
    }
    
    /**
     * Get ISBNs
     *
     * @return array
     */
    function getISBNs()
    {
        return $this->all('schema:isbn');
    }
    
    /**
     * Get Book Format
     * @return EasyRDF_Literal
     */
    function getBookFormat(){
        return $this->get('schema:bookFormat');
    }
    
    /**
     * Get Description
     * @return EasyRDF_Literal
     */
    
    function getDescription(){
        return $this->get('schema:description');
    }
    
}
