<?php

namespace VuFind\Captcha;

class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'figlet' => Figlet::class,
        'image' => Image::class,
        'recaptcha' => ReCaptcha::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Figlet::class => FigletFactory::class,
        Image::class => ImageFactory::class,
        ReCaptcha::class => ReCaptchaFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return AbstractBase::class;
    }
}
