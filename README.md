# Fully integrated version of AMFPHP to Kohana

## Description

This module addresses communication Adobe's AMF framework Kohana 3.2.

## Using

To use the service creates a folder in `APPPATH classes/controller/amf` with
the desired class and use the actions in the same way that the common controllers
Kohana as an example below:

	// classes/controller/amf/welcome.php
	class Controller_Amf_Welcome extends Controller_Amf {
		
		public function action_test($foo)
		{
			return $foo;
		}

	} // End Controller_Amf_Welcome
	
Now go to the gateway in url, something like: `http://localhost/gateway`
With this url you can test your actions with visual (like original AMFPHP).

It is still a beta, if they have any tips, please help!