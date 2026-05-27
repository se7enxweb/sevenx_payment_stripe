<?php
//
// Created on: <12-Jan-2007 12:57:54 gb>
//
// Copyright (C) 2001-2006 Brookins Consulting. All rights reserved.
//
// This file may be distributed and/or modified under the terms of the
// GNU General Public License v2.0 (or any later version), as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@brookinsconsulting.com if any conditions of
// this licencing isn't clear to you.
//

$Module = array( 'name' => 'charge',
                 'variable_params' => true );

$ViewList = array();
$ViewList['charge'] = array(
                          'script' => 'charge.php',
                          "default_navigation_part" => 'ezshopnavigationpart',
                          "unordered_params" => array( 'order' => 'Order' ) );
$ViewList['confirm'] = array(
                          'script' => 'confirm.php',
                          "default_navigation_part" => 'ezshopnavigationpart',
                          "unordered_params" => array() );
$ViewList['verify'] = array(
                          'script' => 'verify.php',
                          "default_navigation_part" => 'ezshopnavigationpart',
                          "unordered_params" => array() );

?>