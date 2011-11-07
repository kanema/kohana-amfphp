<?php defined('SYSPATH') or die('No direct script access.');

class Amfphp_Amf {
    
    /**
     * @var Amf 
     */
    public static $_instance;
    
    protected $_data = array();
    
    public static function instance()
    {
        if ( ! Amf::$_instance)
        {
            Amf::$_instance = new Amf;
        }
        return Amf::$_instance;
    }
    
    public function clean()
    {
        $this->_data = array();
    }
    
    public function params($key = NULL, $value = NULL)
    {
        if ($key !== NULL AND $value !== NULL)
        {
            $this->_data[$key] = $value;
        }
        
        if ($key !== NULL)
        {
            return Arr::get($this->_data, $key);
        }
        
        return $this->_data;
    }
    
}