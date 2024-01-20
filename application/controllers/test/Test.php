<?php

  
require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/JWT.php';;

class Test extends REST_Controller{
 
 	public function sample_get()
	{
	   // echo "<pre>";print_r("user");exit;
		$this->returnResponse['msg']    = "Success";
		echo "<pre>";print_r("user");exit;
		$this->response($this->returnResponse, REST_Controller::HTTP_OK);
	}
	
}