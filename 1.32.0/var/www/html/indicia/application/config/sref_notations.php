<?php

/**
 * Indicia, the OPAL Online Recording Toolkit.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/gpl.html.
 *
 * @package	Core
 * @subpackage Config
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

defined('SYSPATH') or die('No direct script access.');

/**
 * List of supported spatial reference notations which are just straight translations to an x,y
 * or lat long format. Any other notations (e.g. grids) need a module to handle the grid notation.
 */
$config['sref_notations'] = array
(
  '4326' =>'Latitude and Longitude (WGS84)',
  '4277' =>'Latitude and Longitude (OSGB36)',
  '27700' =>'British National Grid Easting/Northing',
  '2169' =>'Gauss Luxembourg',
  '27572'=>'NTF (Paris) / Lambert zone II',
  '31370' => 'Belgian Lambert 72'
);

// Set the internally stored geoms to use spherical mercator projection
$config['internal_srid']=900913;

// For each known SRID, specify a rounding value to set the number of digits usually given after the decimal place.
$config['roundings'] = array
(
  '4326'=>5,
  '4277'=>5,
  '2169'=>0
);

// provide a list of systems which translate x,y format into a proper Lat/Long format, and the default ouput format
// 'default_output' : DMS - Degrees:Minutes:Decimal Seconds, DM - Degrees:Decimal Minutes, D - Decimal Degrees (DEFAULT)
// 'indicator' :
// 	PlusMinus - use plus/minus signs prefix to indicate NSEW,
// 	Minus - use minus sign prefix to indicate SW (N+E don't show plus),
//  Prefix_NSEW - use NSEW at start of number, (DEFAULT)
//  Postfix_NSEW - use NSEW at end of number
$config['lat_long_systems'] = array
(
  '4326' => array('default_output' => 'D', 'indicator' => 'Postfix_NSEW'),
  '4277' => array('default_output' => 'D', 'indicator' => 'Postfix_NSEW')
);