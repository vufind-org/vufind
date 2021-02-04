<?php
namespace VuFind\I18n\Translator\Loader;

use Laminas\I18n\Translator\TextDomain;

interface LoaderInterface
{
    /**
     * @param string $locale
     * @param string $textDomain
     * @return TextDomain
     */
    public function load(string $locale, string $textDomain): TextDomain;
}
