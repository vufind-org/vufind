<?php

namespace VuFind\I18n\Translator\Loader;

use VuFind\I18n\Translator\Loader\Command\LoadLocaleCommand;
use VuFind\I18n\Translator\Loader\Handler\HandlerContext;
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

    public function attach(HandlerInterface $handler, array $options, int $priority)
    {
        $this->filterChain->attach(function ($command, $args, FilterIterator $handlers)
        use ($handler, $options): \Generator {
            yield from $handler(new HandlerContext($this->filterChain, $options), $command);
            yield from $handlers->next($command, $args, $handlers) ?? [];
        }, $priority);
    }

    /**
     * @param string $locale
     * @param string $textDomain
     * @return TextDomain
     */
    public function load(string $locale, string $textDomain): TextDomain
    {
        $command = new LoadLocaleCommand($locale, $textDomain);
        foreach ($this->filterChain->run($command) as $file => $fileData) {
            list ($files[], $data[$file]) = [$file, $fileData];
        }

        if (!isset($file) && $textDomain === 'default') {
            throw new TranslatorRuntimeException("Locale $locale could not be loaded!");
        }

        $result = new TextDomain();
        foreach (array_reverse($files) as $file) {
            $result->merge($data[$file]);
        }

        return $result;
    }
}