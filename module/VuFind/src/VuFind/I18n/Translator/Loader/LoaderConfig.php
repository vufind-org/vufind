<?php

namespace VuFind\I18n\Translator\Loader;

class LoaderConfig implements \IteratorAggregate
{
    /**
     * @var array
     */
    protected $chain;

    /**
     * @var array
     */
    protected $config;

    public function __construct(array $config)
    {
        foreach ($this->config = $config as $name => $options) {
            if ($options['add'] ?? true) {
                $this->chain[] = $options;
            }
        }
    }

    public function add(string $name, callable $getOptions): self
    {
        $this->chain[] = call_user_func($getOptions, $this->config[$name]);

        return $this;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->chain);
    }
}