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

Phar::mapPhar('oclc-auth.phar');

require_once 'phar://oclc-auth.phar/vendor/symfony/class-loader/Symfony/Component/ClassLoader/UniversalClassLoader.php';

$classLoader = new Symfony\Component\ClassLoader\UniversalClassLoader();
$classLoader->registerNamespaces(array(
    'OCLC' => 'phar://oclc-auth.phar/src',
    'Guzzle' => 'phar://oclc-auth.phar/vendor/guzzle/src',
    'Symfony\\Component\\EventDispatcher' => 'phar://oclc-auth.phar/vendor/symfony/event-dispatcher'
));
$classLoader->register();

__HALT_COMPILER();