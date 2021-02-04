<?php
namespace VuFind\I18n\Locale;

use Laminas\Http\PhpEnvironment\Request;
use SlmLocale\LocaleEvent;
use SlmLocale\Strategy\AbstractStrategy;

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
