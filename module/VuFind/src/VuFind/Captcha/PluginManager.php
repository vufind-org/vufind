<?php

namespace VuFind\Captcha;

use Laminas\ServiceManager\Factory\InvokableFactory;

class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'figlet' => Figlet::class,
        'recaptcha' => ReCaptcha::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Figlet::class => InvokableFactory::class,
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
