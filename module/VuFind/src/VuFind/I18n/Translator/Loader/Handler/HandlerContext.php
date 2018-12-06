<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use Zend\EventManager\Filter\FilterInterface;

class HandlerContext extends \ArrayObject
{
    /**
     * @var FilterInterface
     */
    protected $filter;

    public function __construct(FilterInterface $filter, array $options)
    {
        parent::__construct($options);
        $this->filter = $filter;
    }

    public function run($command): \Generator
    {
        yield from $this->filter->run($command);
    }
}