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

use WorldCat\Discovery\Offer;

class OfferConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     *Test configuring static variable in Offer class
     */
    function testConfigs(){
        Offer::$serviceUrl = 'discovery';
        Offer::$testServer = TRUE;
        Offer::$userAgent = 'Testing Client';
    
        $this->assertEquals('discovery', Offer::$serviceUrl);
        $this->assertTrue(Offer::$testServer);
        $this->assertEquals('Testing Client', Offer::$userAgent);
    
    }
}
