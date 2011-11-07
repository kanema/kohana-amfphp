<?php defined('SYSPATH') or die('No direct access allowed.');

abstract class Amfphp_Controller_Gateway extends Controller {
    
    /**
     * @var mixed
     */
    protected $data;
    
    /**
     * @var Object
     */
    protected $view;
    
    /**
     * @var String
     */
    protected $_target_uri = '';
    
    /**
     * @var String
     */
    protected $_response_uri = '';
    
    /**
     * @var bool 
     */
    protected $_safe_mode = NULL;
    
    public function target_uri($value = NULL)
    {
        if ($value !== NULL)
        {
            $this->_target_uri = $value;
        }
        return $this->_target_uri;
    }
    
    public function response_uri($value = NULL)
    {
        if ($value !== NULL)
        {
            $this->_response_uri = $value;
        }
        return $this->_response_uri;
    }
    
    public function safe_mode()
    {
        if ($this->_safe_mode === NULL)
        {
            $this->_safe_mode = (bool) (Kohana::$is_cli OR Request::current() !== Request::initial()) AND $this->request->query();    
        }
        return $this->_safe_mode;
    }
    
    public function before()
    {
        parent::before();
        if ( ! $this->safe_mode() AND Kohana::$environment === Kohana::DEVELOPMENT AND $this->request->headers('Content-type') != 'application/x-amf')
        {
            $this->request->action('services');
        }
    }
    
    public function action_index()
    {   
        $messages = array();
        
        if ($this->safe_mode() AND $this->request->query('target_uri'))
        {
            $this->target_uri($this->request->query('target_uri'));
        }
        else
        {
            $deserializer = new Amfphp_Core_Deserializer(Arr::get($GLOBALS, 'HTTP_RAW_POST_DATA', file_get_contents('php://input')));
            $messages = $deserializer->deserialize()->messages;
        }
		
        if (isset($messages[0]))
        {
            $this->target_uri($messages[0]->targetUri);
			
			$this->response_uri($messages[0]->responseUri);
            
            if (isset($messages[0]->data[0]))
            {
                $this->data = $messages[0]->data[0];
                
                if ( ! is_array($this->data))
                {
                    $this->data = array($this->data);
                }
            }
        }
        
        $method = explode('.', $this->target_uri());
        if (count($method) < 2)
        {
            $method = explode('/', $method[0]);   
        }
        if (count($method) < 2)
        {
            throw new Kohana_Exception('Invalid Service.Method');
        }
        
        $service_name = $method[0];
        $method_name = $method[1];
        
        // Get Current Action
		if ( ! preg_match('/^action_/', $method_name))
		{
			$method_name = 'action_'.$method_name;
		}
        
        // Get Current Controller
        $service_name = 'Controller_Amf_'.$service_name;
        
        // Load the controller using reflection
        $class = new ReflectionClass($service_name);
        
        if ($class->isAbstract())
        {
            throw new Kohana_Exception('Cannot create instances of abstract :serviceName',
                array(':serviceName' => $service_name));
        }
        
        // Create a new instance of the controller
        $controller = $class->newInstance($this->request, $this->response);

        // If the action doesn't exist, it's a 404
        if ( ! $class->hasMethod($method_name))
        {
            throw new HTTP_Exception_404('The requested method :action was not found on controller :controller.',
                                                array(':action' => $method_name, ':controller' => $service_name));
        }
        
        // Get action method
        $method = $class->getMethod($method_name);

        // Get Parameters
        $method_parameters = $method->getParameters();

        if (count($method_parameters > 1) AND isset($this->data[0]) AND $this->data[0] instanceof stdClass)
        {
            $this->data = $this->data[0];
            $temp_parameters = array();
            
            foreach ($method_parameters as $key => $parameter)
            {
                $name = $parameter->name;
                
                $temp_parameters[$key] = $this->data->$name;
				
				Amf::instance()->params($name, $this->data->$name);
            }

            $this->data = $temp_parameters;
        }
        
        if ($this->safe_mode())
        {
            $this->data = $this->request->query();
            array_shift($this->data);
        }
        
		if ( ! is_array($this->data))
		{
			Amf::instance()->params(0, $this->data);
		}

        $execute_action = $class->getMethod('before')->invoke($controller);
        
        if ($execute_action !== FALSE)
        {
            $response = call_user_func_array(array($controller, $method_name), $this->data);
            $class->getMethod('after')->invoke($controller);
        }
        
        if (isset($response) AND $response)
        {
            $this->view = $response;
        }
        if (isset($controller->body))
        {
            $this->view = $controller->body;
        }
        
        if ( ! $this->safe_mode())
        {
            $packet = new Amfphp_Core_Packet;

            $message = array();
            $message['targetUri'] = $this->response_uri().'/onResult';
            $message['responseUri'] = NULL;
            $message['data'] = $this->view;

            $packet->messages[] = (object) $message;

            $serializer = new Amfphp_Core_Serializer($packet);
            $serializer->serialize();
            $this->response->body($serializer->getOutput());

            if ( ! $this->response->headers('Content-type'))
            {
                $this->response->headers('Content-type', 'application/x-amf');
            }
        }
        else
        {
            $this->response->body(json_encode($this->view));
        }
    }
    
    protected function get_services()
    {
        $path = 'classes'.DIRECTORY_SEPARATOR.'controller'.DIRECTORY_SEPARATOR.'amf'.DIRECTORY_SEPARATOR;
        
        $services = array();
        foreach (Kohana::list_files('classes\controller\amf') as $name => $file)
        {
			$name = str_replace(array($path, EXT), '', $name);
			
			$class = 'Controller_Amf_'.$name;
			$reflection = new ReflectionClass($class);
			if ($reflection->isAbstract())
			{
				continue;
			}
			
            $services[] = $name;
        }
        
        return $services;
    }
    
    protected function get_methods($service)
    {
        $methods = array();
        
        $class = 'Controller_Amf_'.$service;
        
        $reflection = new ReflectionClass($class);
		
        $available_methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
		
        $pattern = '/^action_/';
        foreach ($available_methods as $method)
        {
            if (preg_match($pattern, $method->name))
            {
                $methods[] = preg_replace($pattern, '', $method->name);
            }
        }
        
        return $methods;
    }
    
    protected function get_parameters($service, $method)
    {
        $parameters = array();
        
        $class = 'Controller_Amf_'.$service;
        
        $reflection = new ReflectionClass($class);
        $method_instance = $reflection->getMethod('action_'.$method);
        
        foreach ($method_instance->getParameters() as $parameter)
        {
            $parameters[] = $parameter->name;
        }
        
        return $parameters;
    }
    
    public function action_services()
    {
        if (Kohana::$environment !== Kohana::DEVELOPMENT)
        {
            throw new Kohana_Exception('Access denied.');
        }
		
        $service = $this->request->query('service');
        $method = $this->request->query('method');
		
		
		if ($method)
		{
			$title = HTML::anchor('gateway', 'Services').' > '.HTML::anchor('gateway?service='.$service, $service).' > ';
			if ($this->request->post())
			{
				$title .= HTML::anchor('gateway?service='.$service.'&method='.$method, $method).' > POST';
			}
			else
			{
				$title .= $method;
			}
		}
		elseif ($service)
		{
			$title = HTML::anchor('gateway', 'Services').' > '.$service;
		}
		else
		{
			$title = HTML::anchor('gateway', 'Services');
		}
		
		echo ''.
		'<html>'.
			'<head>'.
				'<link rel="stylesheet" href="http://blueprintcss.org/blueprint/screen.css" type="text/css" media="screen, projection" />'.
				'<title>'.strip_tags($title).'</title>'.
			'</head>'.
			'<body>'.
			'<div class="container" id="content"><br />';
		
		echo $title;
		echo '<br /><br /><hr />';
        
		echo '<div class="span-12">';
		
		echo '<h1>Services:</h1>';
		
        // Services
        foreach ($this->get_services() as $item)
        {
            echo HTML::anchor('gateway?service='.$item, ($service == $item) ? '<b>'.$item.'</b>' : $item).'<br />';
        }
        
        // Methods
        if ($service)
        {
            echo '<br /><br /><hr />';
            echo '<h2>Methods:</h2>';
            foreach($this->get_methods($service) as $item)
            {
                echo HTML::anchor('gateway?service='.$service.'&method='.$item, ($method == $item) ? '<b>'.$item.'</b>' : $item).'<br />';
            }
        }
		
		echo '</div><div class="span-12 last">';
        
        // Action
        if ($method)
        {
            echo '<h3>Action <i>'.$method.'</i>:</h3>';
            $parameters = $this->get_parameters($service, $method);
            echo Form::open('gateway?service='.$service.'&method='.$method);
            if ( ! empty($parameters))
            {
                foreach ($parameters as $parameter)
                {
                    echo '<p>'.
                        Form::label(__($parameter)).':<br />'.
                        Form::input($parameter, $this->request->post($parameter)).
                    '</p>';
                }
            }
            else
            {
                echo 'No parameters.<br />';
            }
            echo Form::submit('gateway_submit', 'Execute');
            echo Form::close();
        }
        
        // Execute Action
        if ($this->request->post())
        {
            $request = Request::factory('gateway');

            $request->query('target_uri', $service.'.'.$method);
            foreach ($this->request->post() as $key => $value)
            {
                if ($key != 'gateway_submit')
                {
                    $request->query($key, $value);
                }
            }
            
            $request->execute();

            echo '<br /><br /><hr /><br />';
            echo '<b>Result:</b><br />';
            echo Debug::vars(json_decode($request->response()->body()));
        }
        
        echo '</div></div></body></html>';
    }
	
	public function after()
	{
		parent::after();
	}
    
}