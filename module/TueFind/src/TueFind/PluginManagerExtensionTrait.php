<?php

namespace TueFind;

/**
 * This trait is necessary for correct plugin overwriting when inheriting
 * over multiple hierarchy levels.
 *
 * If overrides are performed directly instead, e.g. IxTheo would be called first
 * and TueFind afterwards, so TueFind would overwrite IxTheo.
 *
 * By using this trait, overrides are stored in a cache instead and
 * the order will be reversed before the overrides are applied.
 *
 * Note: applyOverrides needs to be called only once
 *       => in TueFind layer before the parent constructor is called.
 */
trait PluginManagerExtensionTrait {

    protected $overrides = [];

    protected function addOverride($type, $key, $value) {
        $override = ['type' => $type, 'key' => $key, 'value' => $value];
        if (!in_array($override, $this->overrides))
            $this->overrides[] = $override;
    }

    protected function applyOverrides() {
        $overrides = array_reverse($this->overrides);
        foreach ($overrides as $override) {
            switch ($override['type']) {
                // Use ServiceManager's functions to override instead of
                // writing directly to class variables (e.g. to reset cache)
                case 'aliases':
                    $this->setAlias($override['key'], $override['value']);
                    break;
                case 'factories':
                    $this->setFactory($override['key'], $override['value']);
                    break;
                case 'delegators':
                    $this->addDelegator($override['key'], $override['value']);
                    break;
                default:
                    throw new \Exception("Unknown override type: " . $override['type']);
            }
        }
    }
}
