<?php
/**
 * Merge harvested MARC records into a single <collection>
 *
 * PHP version 8
 *
 * Copyright (c) Demian Katz 2010.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Thomas Schwaerzler <thomas.schwaerzler@uibk.ac.at>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:authority_control Wiki
 */

// Manipulate command line to load correct route, then run the main index page:
array_unshift($_SERVER['argv'], array_shift($_SERVER['argv']), 'harvest', 'merge-marc');
$_SERVER['argc'] += 2;
require_once __DIR__ . '/../public/index.php';
