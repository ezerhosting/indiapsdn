<?php
   
require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/JWT.php';
     
class Admin extends REST_Controller 
{
	public $returnResponse   = [];
	public $loggedUser       = [];

	/**
	 * Get All Data from this method.
	 *
	 * @return Response
	*/
    public function __construct() {
		parent::__construct();
		$this->load->model('api/adminmodel', 'adminmodel');
		$this->load->model('api/commonmodel', 'commonmodel');
		$returnResponse['status'] = false;
		$returnResponse['msg']    = "Something went wrong. Please try again.";
		$returnResponse['data']   = '';
    }

    /**
	 * JWT creation.
	 *
	 * @return Response
	*/
    public function encode_token_get($user) 
    {
		$data = [
			'userId'   => $user['user_id'],
			'phone'    => $user['user_phone'],
			'userType' => $user['user_type'],
		];
		return JWT::encode($data, JWT_SECRET_KEY, 'HS256');
    }

    /**
	 * Decoding JWT token.
	 *
	 * @return Response
	*/
	public function can_access() 
	{
		$headers = getallheaders();
		$token = (isset($headers['Authorization']) ? $headers['Authorization'] : '');

		if ($token != '') {
			$data = JWT::decode($token, JWT_SECRET_KEY, 'HS256');

			if (!empty($data) && isset($data->userId)) {
				$user_data = $this->commonmodel->fetch('user_id', $data->userId, '*', $this->db->table_users);

				if(!empty($user_data)) {
					$this->loggedUser = $user_data;
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Admin login api.
	 *
	 * @return Response
	*/
    public function login_post() 
    {
    	// Validation
		$params = $this->post();	
		$this->form_validation->set_data($params);
		$this->form_validation->set_rules('phone_number', 'Phone Number', 'required');
		$this->form_validation->set_rules('password_value', 'Password', 'required');

		// Validation verify
		if ($this->form_validation->run() == FALSE) {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = $this->form_validation->error_array();
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			//Pass params to Model
			$response = $this->adminmodel->verify_user($params);
			if (empty($response)) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "Please Enter valid Credentials.";
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			}

			if (isset($response['user_password'])) {
				unset($response['user_password']);
			}

			$this->returnResponse['token'] = $this->encode_token_get($response);
			$this->returnResponse['status']   = true;
			$this->returnResponse['msg']      = 'Logged-in successfuly.';
			$this->returnResponse['data']     = $response;
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		}
	}

	/**
	* Get configuration setting details.
	*
	* @return Response
	*/
	public function config_get()
	{
		if ($this->can_access()) {
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Config settings listed successfuly.";
			$data['states']           = $this->commonmodel->allStatesList();
			$data['rto_numbers']      = $this->commonmodel->allRtoNumbers();
			$data['make_list']        = $this->commonmodel->allMakeList();
			$data['model_list']       = $this->commonmodel->allModelList();
			$data['company_list']     = $this->commonmodel->allCompanyList();
			$this->returnResponse['data']   = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function dashboard_get()
	{
		if ($this->can_access()) {
			//Check Permission
			$user_type = $this->loggedUser['user_type'];

			/*if (!check_permission($user_type, 'menu_dashboard')) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "You don't have permission to view";
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			} else {*/
				$this->returnResponse['status'] = true;
				$this->returnResponse['msg']    = "Successfuly fetched data.";
				$data['countList'] = $this->commonmodel->getNoOfCount($this->loggedUser);
				// $this->commonmodel->userTracking();
				$this->returnResponse['data'] = $data;

				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			// }			
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}
}