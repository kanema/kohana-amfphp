<?php defined('SYSPATH') or die('No direct script access.');

Route::set('amfphp_gateway', 'gateway(.php)')->defaults(array('controller' => 'gateway', 'action' => 'index'));
