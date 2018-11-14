<?php

namespace VuFind\I18n\Locale;

use SlmLocale\LocaleEvent;
use SlmLocale\Strategy\AbstractStrategy;
use Zend\Http\PhpEnvironment\Request;

class LocaleDetectorParamStrategy extends AbstractStrategy
{
    const PARAM_NAME = 'mylang';

    public function detect(LocaleEvent $event)
    {
        /** @var Request $request */
        $request = $event->getRequest();
        $locale = $request->getPost(self::PARAM_NAME);
        if (in_array($locale, $event->getSupported())) {
            return $locale;
        }
    }
}