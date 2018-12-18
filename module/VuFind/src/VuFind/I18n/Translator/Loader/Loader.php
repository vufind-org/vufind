<?php

namespace VuFind\I18n\Translator\Loader;

use VuFind\I18n\Translator\Loader\Handler\Action\ActionInterface;
use VuFind\I18n\Translator\Loader\Handler\Action\InitialAction;
use VuFind\I18n\Translator\Loader\Handler\HandlerChain;
use VuFind\I18n\Translator\Loader\Handler\HandlerInterface;
use VuFind\I18n\Translator\TranslatorRuntimeException;
use Zend\EventManager\Filter\FilterIterator;
use Zend\EventManager\FilterChain;
use Zend\I18n\Translator\TextDomain;

class Loader implements LoaderInterface
{
    /**
     * @var FilterChain
     */
    protected $filterChain;

    public function __construct()
    {
        $this->filterChain = new FilterChain();
    }

    public function attach(HandlerInterface $handler, int $priority = 0)
    {
        $this->filterChain->attach(
            function (ActionInterface $action, array $args, FilterIterator $filterIterator)
            use ($handler): \Generator {
                $chain = new HandlerChain($this->filterChain, $filterIterator);
                yield from $handler->handle($action, $chain);
            }, $priority);
    }

    /**
     * @param string $locale
     * @param string $textDomain
     * @return TextDomain
     */
    public function load(string $locale, string $textDomain): TextDomain
    {
        $result = new TextDomain();
        $action = new InitialAction($locale, $textDomain);

        foreach ($this->filterChain->run($action) as $data) {
            $result['@meta'][] = $data['@meta'];
            $result = (new TextDomain($data))->merge($result);
        }

        if (!isset($data) && $textDomain === 'default') {
            throw new TranslatorRuntimeException("Locale $locale could not be loaded!");
        }

        return $result;
    }
}