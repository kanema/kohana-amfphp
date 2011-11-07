<?php defined('SYSPATH') or die('No direct script access.');

/**
 * MirrorService is a test/example service. Remove it for production use
 *
 * @package Amfphp_Services
 * @author Ariel Sommeria-klein
 */
class Controller_Amf_MirrorService extends Controller_Amf {

    public function action_returnOneParam($param)
	{
        return $param;
    }

    public function action_returnSum($number1, $number2)
	{
        return $number1 + $number2;
    }

    public function action_returnNull()
	{
        return null;
    }

    public function action_returnBla()
	{
        return "bla";
    }

}
