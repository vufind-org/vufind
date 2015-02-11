<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Feed\Reader;

interface ExtensionManagerInterface
{
    /**
     * Do we have the extension?
     *
     * @param  string $extension
     * @return bool
     */
    public function has($extension);

    /**
     * Retrieve the extension
     *
     * @param  string $extension
     * @return mixed
     */
    public function get($extension);
}
