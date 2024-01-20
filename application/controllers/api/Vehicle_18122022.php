<?php
   
require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/JWT.php';;
     
class Vehicle extends REST_Controller 
{
	public $returnResponse = [];
	public $loggedUser     = [];

	/**
	 * Get All Data from this method.
	 *
	 * @return Response
	*/
    public function __construct() {
		parent::__construct();
		$this->load->model('api/vehiclemodel', 'vehiclemodel');
		$this->load->model('api/commonmodel', 'commonmodel');
		$returnResponse['status'] = false;
		$returnResponse['msg']    = "Something went wrong. Please try again.";
		$returnResponse['data']   = '';
    }

    /**
	 * Decoding JWT token.
	 *
	 * @return Response
	*/
	public function can_access() 
	{
		$headers = getallheaders();
		$token = $headers['Authorization'] ?? '';

		if ($token != '') {
			$data = JWT::decode($token, JWT_SECRET_KEY, 'HS256');

			if (!empty($data) && isset($data['userId'])) {
				$user_data = $this->commonmodel->fetch('id', $data['userId']);

				if(!empty($user_data)) {
					$this->loggedUser = $user_data;
					return true;
				}
			}
		}

		return false;
	}

    /**
	* Save new vehicle.
	*
	* @return Response
	*/
	public function add_vehicle_post()
    {
    	if ($this->can_access()) {
			$params = $this->post();	
			$this->form_validation->set_data($params);
			// Validation
			$this->form_validation->set_rules('veh_create_date', 'Vehicle Created Date', 'trim|required');
			$this->form_validation->set_rules('validity_to', 'Vehicle Validity Date', 'trim|required');
			$this->form_validation->set_rules('veh_rc_no', 'Vehicle Rc Number', 'trim|required');
			$this->form_validation->set_rules('veh_chassis_no', 'Vehicle Chassis Number', 'trim|required');
			$this->form_validation->set_rules('veh_company_id', 'Vehicle Company Name', 'trim|required');
			$this->form_validation->set_rules('veh_engine_no', 'Vehicle Engine Number', 'trim|required');
			$this->form_validation->set_rules('veh_make_no', 'Vehicle Make Number', 'trim|required');
			$this->form_validation->set_rules('veh_model_no', 'Vehicle Model Number', 'trim|required');
			$this->form_validation->set_rules('veh_owner_name', 'Vehicle Owner Name', 'trim|required');
			$this->form_validation->set_rules('veh_address', 'Vehicle Address', 'trim|required');
			$this->form_validation->set_rules('veh_owner_phone', 'Vehicle Owner Number', 'trim|required');
			$this->form_validation->set_rules('veh_serial_no', 'Vehicle Serial Number', 'trim|required');
			$this->form_validation->set_rules('veh_rto_no', 'Vehicle RTO Number', 'trim|required');
			$this->form_validation->set_rules('veh_speed', 'Vehicle Speed', 'trim|required');
			$this->form_validation->set_rules('veh_tac', 'Vehicle Tac Number', 'trim|required');
			$this->form_validation->set_rules('veh_cat', 'Vehicle Category', 'trim|required');
			$this->form_validation->set_rules('veh_invoice_no', 'Vehicle Invoice Number', 'trim|required');
			$this->form_validation->set_rules('veh_speed_governer_photo', 'Device Photo', 'trim|required');
			$this->form_validation->set_rules('selling_price', 'Selling Price', 'trim|required');
			$this->form_validation->set_rules('veh_photo', 'Vehicle Photo', 'trim|required');

			if (isset($params['veh_owner_email']) && strlen($params['veh_owner_email']) > 0) {
				$this->form_validation->set_rules('veh_owner_email', 'Email', 'required|valid_email');
			}

			$this->form_validation->set_rules('veh_rc_no', 'Vehicle Rc Number', ['required', ['already_exits', 
				function ($str) { 
					return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_rc_no'); 
				}
			]]);

			$this->form_validation->set_rules('veh_chassis_no', 'Vehicle Chassis Number', ['required', ['already_exits', function ($str) {
					return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_chassis_no');

				}
			]]);

			$this->form_validation->set_rules('veh_engine_no', 'Vehicle Engine Number', ['required', ['already_exits',
				function ($str) {
					return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_engine_no');

				}
			]]);

			if (isset($params['veh_rc_no']) && strlen($params['veh_rc_no']) > 0) {
				$params['veh_rc_no'] = preg_replace('/\s+/', '', $params['veh_rc_no']);
			}

			$fetchCompanyInfo = $this->commonmodel->fetch('c_company_id', $params['veh_company_id'], 'c_company_name,c_cop_validity', $this->db->table_company);
			$params['veh_cop_validity'] = isset($fetchCompanyInfo['c_cop_validity']) ? $fetchCompanyInfo['c_cop_validity'] : date('Y-m-d H:i:s');
			$params['veh_sld_make'] = isset($fetchCompanyInfo['c_company_name']) ? $fetchCompanyInfo['c_company_name'] : "";
			$params['validity_from'] = date('Y-m-d H:i:s');
			$params['validity_to'] = date('Y-m-d H:i:s', strtotime("+" . EXPIRE_DATE_VALUE . " days"));
			$params['veh_create_date'] = date('Y-m-d');

			// Validation verify
			if ($this->form_validation->run() == FALSE) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = $this->form_validation->error_array();
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			} else {
				// Rename Vehicle Owner Id Proof Photo
				if (isset($params['vehicle_owner_id_proof_photo']) && strlen($params['vehicle_owner_id_proof_photo']) > 0) {
					if (strpos($params['vehicle_owner_id_proof_photo'], 'temp_upload') !== false) {
						$profile_photo = str_replace('public/temp_upload/', 'public/upload/vehicle_owner_id_proof/', $params['vehicle_owner_id_proof_photo']);
						rename($params['vehicle_owner_id_proof_photo'], $profile_photo);
						$params['vehicle_owner_id_proof_photo'] = $profile_photo;
					}
				}

				// Rename Vehicle Owner Photo
				if (isset($params['vehicle_owners_photo']) && strlen($params['vehicle_owners_photo']) > 0) {
					if (strpos($params['vehicle_owners_photo'], 'temp_upload') !== false) {
						$profile_photo = str_replace('public/temp_upload/', 'public/upload/vehicle_owners_photos/', $params['vehicle_owners_photo']);
						rename($params['vehicle_owners_photo'], $profile_photo);
						$params['vehicle_owners_photo'] = $profile_photo;
					}
				}

				// Rename RC Book Photo
				if (isset($params['rc_book_photo']) && strlen($params['rc_book_photo']) > 0) {
					if (strpos($params['rc_book_photo'], 'temp_upload') !== false) {
						$profile_photo = str_replace('public/temp_upload/', 'public/upload/rc_book_photos/', $params['rc_book_photo']);
						rename($params['rc_book_photo'], $profile_photo);
						$params['rc_book_photo'] = $profile_photo;
					}
				}

				// Rename Vehicle Speed Governer Photo
				if (isset($params['veh_speed_governer_photo']) && strlen($params['veh_speed_governer_photo']) > 0) {
					if (strpos($params['veh_speed_governer_photo'], 'temp_upload') !== false) {
						$profile_photo = str_replace('public/temp_upload/', 'public/upload/vehicle/', $params['veh_speed_governer_photo']);
						rename($params['veh_speed_governer_photo'], $profile_photo);
						$params['veh_speed_governer_photo'] = $profile_photo;
					}
				}

				// Rename Vehicle Photo
				if (isset($params['veh_photo']) && strlen($params['veh_photo']) > 0) {
					if (strpos($params['veh_photo'], 'temp_upload') !== false) {
						$profile_photo = str_replace('public/temp_upload/', 'public/upload/vehicle/', $params['veh_photo']);
						rename($params['veh_photo'], $profile_photo);
						$params['veh_photo'] = $profile_photo;
					}
				}

				$params['veh_created_user_id'] = $this->session->userdata('user_id');
				$info = $this->adminmodel->create_new_vehicle_records($params);
				$response = $info['insert_id'];

				if (empty($response)) {
					$this->returnResponse['status'] = false;
		            $this->returnResponse['msg']    = "Unable to add vehicle. Please try again later.";
		            $this->response($this->returnResponse, REST_Controller::HTTP_OK);
				}

				$veh_owner_id = $info['veh_owner_id'];
				$this->adminmodel->add_tracking_entry($response, $veh_owner_id);
				$this->returnResponse['status'] = true;
				$this->returnResponse['msg']    = 'Vehicle added successfully';

				$encodeID = base64_encode(base64_encode(base64_encode($response)));
				$tinyurl = $this->returnResponse['data']['pdf_download']  = $this->get_tiny_url(base_url() . 'admin/downloadwebpdf?id=' . $encodeID);
				$SMS = 'Cerificate Created successfully, ref url :' . $tinyurl;
				log_message('error', $SMS);
				$this->commonmodel->send_sms($params['veh_owner_phone'], $SMS);
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			}
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
	public function list_get()
    {
    	if ($this->can_access()) {
			$search_param = $this->input->get('search');
			$vehicle_list = $this->vehiclemodel->vehicle_list($search_param);
			$returnResponse['status'] = true;
			$returnResponse['msg']    = "Vehicle sent successfully";
			if (empty($vehicle_list)) {
				$returnResponse['msg']    = "No vehicles available.";
			}
			
			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }
}