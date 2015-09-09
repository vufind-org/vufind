<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Feed\Reader\Extension\CreativeCommons;

use Zend\Feed\Reader\Extension;

class Entry extends Extension\AbstractEntry
{
    /**
     * Get the entry license
     *
     * @param int $index
     * @return string|null
     */
    public function getLicense($index = 0)
    {
        $licenses = $this->getLicenses();

        if (isset($licenses[$index])) {
            return $licenses[$index];
        }

        return;
    }

    /**
     * Get the entry licenses
     *
     * @return array
     */
    public function getLicenses()
    {
        $name = 'licenses';
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        $licenses = array();
        $list = $this->xpath->evaluate($this->getXpathPrefix() . '//cc:license');

        if ($list->length) {
            foreach ($list as $license) {
                $licenses[] = $license->nodeValue;
            }

            $licenses = array_unique($licenses);
        } else {
            $cc = new Feed();
            $licenses = $cc->getLicenses();
        }

        $this->data[$name] = $licenses;

        return $this->data[$name];
    }

    /**
     * Register Creative Commons namespaces
     *
     */
    protected function registerNamespaces()
    {
        $this->xpath->registerNamespace('cc', 'http://backend.userland.com/creativeCommonsRssModule');
    }
}
