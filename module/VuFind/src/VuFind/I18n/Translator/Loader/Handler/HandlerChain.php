<?php
namespace VuFind\I18n\Translator\Loader\Handler;

use Laminas\EventManager\Filter\FilterInterface;
use Laminas\EventManager\Filter\FilterIterator;
use VuFind\I18n\Translator\Loader\Handler\Action\ActionInterface;

class HandlerChain
{
    /**
     * @var FilterInterface
     */
    protected $filterChain;

    /**
     * @var FilterIterator
     */
    protected $filterIterator;

    public function __construct(FilterInterface $filterChain, FilterIterator $filterIterator)
    {
        $this->filterChain = $filterChain;
        $this->filterIterator = $filterIterator;
    }

    public function next(ActionInterface $action): \Generator
    {
        yield from $this->filterIterator->next($action, [], $this->filterIterator) ?? [];
    }

    public function head(ActionInterface $action): \Generator
    {
        yield from $this->filterChain->run($action);
    }
}
