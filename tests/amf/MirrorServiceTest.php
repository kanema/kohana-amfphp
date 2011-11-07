<?php
/**
*  @package    AMF/MirrorService
 * @category   Test
 * @author     Eduardo Paccheco
 */

class Amf_MirrorServiceTest extends PHPUnit_Framework_TestCase {
    
    /**
	 * Data provider for test_OneParam
	 *
	 * @return  array
	 */
	public function provider_OneParam()
	{
		return array(
			// Test empty
			array(
				NULL,
				NULL,
                TRUE
			),
			// Test success
			array(
				'foo',
				'foo',
                TRUE
			),
		);
	}

	/**
	 * Tests the action_returnOneParam method behaves as expected
	 * 
	 * @dataProvider provider_OneParam
	 *
	 * @return  void
	 */
	public function test_OneParam($send, $return, $expected)
	{
        $result = $send === $return;
		$this->assertTrue($result === $expected);
	}
    
} // END Amf_MirrorServiceTest