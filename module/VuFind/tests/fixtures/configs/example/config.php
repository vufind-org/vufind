<?php
/**
 * VuFind Test configuration aggregation
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
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
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org/wiki/development Wiki
 */
use VuFind\Config\Provider\Basic as BasicProvider;
use VuFind\Config\Provider\Classic as ClassicProvider;
use Zend\ConfigAggregator\ConfigAggregator;

return function ($cachePath) {
    return new ConfigAggregator([
        function () {
            $provider = new BasicProvider([
                __DIR__ . '/core/**/*.{ini,json,yaml}',
                __DIR__ . '/local/**/*.{ini,json,yaml}'
            ]);
            return ['basic' => $provider()];
        },
        function () {
            $provider = new ClassicProvider([
                __DIR__ . '/core/**/*.{ini,json,yaml}',
                __DIR__ . '/local/**/*.{ini,json,yaml}'
            ]);
            return ['classic' => $provider()];
        }
    ], $cachePath);
};
