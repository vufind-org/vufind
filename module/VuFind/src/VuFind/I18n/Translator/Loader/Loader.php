<?php

namespace VuFind\I18n\Translator\Loader;

use VuFind\I18n\Translator\Loader\Event\InitialEvent;
use VuFind\I18n\Translator\TranslatorRuntimeException;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\I18n\Translator\TextDomain;

class Loader implements LoaderInterface, EventManagerAwareInterface
{
    use LoaderTrait;

    /**
     * @param string $locale
     * @param string $textDomain
     * @return TextDomain
     */
    public function load(string $locale, string $textDomain): TextDomain
    {
        $result = new TextDomain();
        $event = new InitialEvent($locale, $textDomain);

        foreach ($this->trigger($event) as $file => $data) {
            $result['@files'][] = $file;
            $result['@data'][$file] = $data;
            $result = (clone $data)->merge($result);
        }

        if (!isset($file) && $textDomain === 'default') {
            throw new TranslatorRuntimeException("Locale $locale could not be loaded!");
        }

        return $result;
    }
}