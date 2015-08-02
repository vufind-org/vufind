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
 * A class that represents a Book in WorldCat
 *
 */
class Book extends CreativeWork
{
    /**
     * Get the Copyright Year
     * @return EasyRDF_Literal
     */
    function getCopyrightYear()
    {
        $copyrightYear = $this->get('schema:copyrightYear');
        return $copyrightYear;
    }
    
    /**
     * Get the Book edition
     * @return EasyRDF_Literal
     */
    function getBookEdition(){
        $bookEdition = $this->get('schema:bookEdition');
        return $bookEdition;
    }
    
    /**
     * Get the Book format
     * @return EasyRDF_Resource
     */
    function getBookFormat(){
        $bookFormat = $this->get('schema:bookFormat');
        return $bookFormat;
    }
    
    /**
     * Get an array of the Manifestations
     * @return array
     */
    function getManifestations(){
        return $this->allResources('schema:workExample');
    }
    
    /**
     * Get an array of the Editors
     */
    function getEditors()
    {
        return $this->allResources('schema:editor');
    }
}