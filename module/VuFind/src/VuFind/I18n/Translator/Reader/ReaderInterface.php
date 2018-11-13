<?php

namespace VuFind\I18n\Translator\Reader;

use Zend\I18n\Translator\TextDomain;

interface ReaderInterface
{
    public function read(string $source): TextDomain;
}