<?php
/**
 * VuFind Classic Configuration Provider
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
 * PHP version 7
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc. 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config\Provider;

/**
 * VuFind Configuration Classic Provider
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Classic extends Basic
{
    /**
     * Classic constructor.
     *
     * @param array $patterns List of glob patterns for looking up
     *                        configuration files.
     */
    public function __construct(array $patterns)
    {
        parent::__construct($patterns);
        $this->attach(new Filter\FlatIni, 3500000);
        $this->attach(new Filter\ParentIni, 2500000);
        $this->attach(new Filter\ParentYaml, 2500000);
        $this->attach(new Filter\UniqueSuffix, 1500000);
    }
}
