<?php

namespace VuFind\Config\Filter;

use VuFind\Config\Provider\Base;
use Zend\EventManager\Filter\FilterIterator;

class ParentYaml
{
    public function __invoke(
        Base $ctx,
        array $args,
        FilterIterator $chain
    ): array {
        list($path, $data) = $args;
        $data = $this->mergeParentYaml($ctx, $data);
        return $chain->isEmpty() ? $data
            : $chain->next($ctx, [$path, $data], $chain);
    }

    /**
     * Merges a parent configuration declared with the «@parent_yaml» directive.
     *
     * @param array $child
     *
     * @return array
     */
    protected function mergeParentYaml(Base $ctx, array $child): array
    {
        if (!isset($child['@parent_yaml'])) {
            return $child;
        }
        $parent = $ctx->loadFile($child['@parent_yaml']);
        unset($child['@parent_yaml']);
        return array_replace($parent, $child);
    }
}