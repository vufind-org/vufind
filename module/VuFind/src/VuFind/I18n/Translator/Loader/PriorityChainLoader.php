<?php

namespace VuFind\I18n\Translator\Loader;

use Zend\EventManager\Filter\FilterIterator;
use Zend\EventManager\FilterChain;
use Zend\I18n\Translator\TextDomain;

class PriorityChainLoader implements LoaderInterface
{
    /**
     * @var FilterChain
     */
    protected $filterChain;

    public function __construct()
    {
        $this->filterChain = new FilterChain();
    }


    public function attach(LoaderInterface $loader, int $prio)
    {
        $this->filterChain->attach($this->toCallback($loader), $prio);
    }

    /**
     * @param string $file
     * @return \Generator|TextDomain[]
     */
    public function load(string $file): \Generator
    {
        yield from $this->filterChain->run($this, compact('file'));
    }

    protected function toCallback(LoaderInterface $loader): \Closure
    {
        return function ($self, array $argv, FilterIterator $tail) use ($loader) : \Generator {
            yield from $loader->load(current($argv));
            yield from $tail->next($self, $argv, $tail) ?? [];
        };
    }
}