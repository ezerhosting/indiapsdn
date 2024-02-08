<?php
   
require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/JWT.php';
require_once FCPATH . 'vendor/autoload.php';
use Aws\S3\S3Client;
     
class Admin extends REST_Controller 
{
	public $returnResponse   = [];

	//upload paths
	public $parenturl;
	public $uploadpath;
	public $veh_speed_governer_folder;
	public $veh_photo_folder;
	public $vehicle_owner_id_proof_folder;
	public $vehicle_owners_folder;
	public $rc_book_folder;

	/**
	 * Get All Data from this method.
	 *
	 * @return Response
	*/
    public function __construct() {
		parent::__construct();
		$this->load->model('api/adminmodel', 'adminmodel');
		$this->load->model('api/commonmodel', 'commonmodel');
		$this->load->model('api/technicianmodel', 'technicianmodel');
		$returnResponse['status'] = false;
		$returnResponse['msg']    = "Something went wrong. Please try again.";
		$returnResponse['data']   = '';

		$this->parenturl                     = '/home/psdntech/public_html/';
		$this->uploadpath                    = $this->parenturl . 'public/upload/';
		$this->veh_speed_governer_folder     = $this->uploadpath . 'vehicle/';
		$this->veh_photo_folder              = $this->uploadpath . 'vehicle/';
		$this->vehicle_owner_id_proof_folder = $this->uploadpath . 'vehicle_owner_id_proof/';
		$this->vehicle_owners_folder         = $this->uploadpath . 'vehicle_owners_photos/';
		$this->rc_book_folder                = $this->uploadpath . 'rc_book_photos/';
		$this->user_folder                   = $this->uploadpath . 'users/';
    }

    public function remove_parent_path($file_name) {
    	return str_replace($this->parenturl, "", $file_name);
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
		} else {
			//Pass params to Model
			$response = $this->adminmodel->verify_user($params);
			if (empty($response)) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "Please Enter valid Credentials.";
			} else {
				if ($response['user_type'] != 1 && $response['user_type'] != 6) {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = "Only dealer or technician can access.";
				} else {
					if (isset($response['user_password'])) {
						unset($response['user_password']);
					}

					$response['base_url'] = 'https://www.psdn.tech/';
					$response['dealer_url'] = 'https://www.psdn.tech/api/admin/';
					$response['technician_url'] = 'https://www.psdn.tech/api/technician/';
					$response['img_path'] = "public/temp_upload/";
					$response['fitment_access'] = isset($params['user_fitment_access']) ? $params['user_fitment_access'] : 0;

					$this->returnResponse['token']  = $this->encode_token_get($response);
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = 'Logged-in successfuly.';
					$this->returnResponse['data']   = $response;
				}
			}
		}
		$this->response($this->returnResponse, REST_Controller::HTTP_OK);
	}

	/**
	* Get configuration setting details.
	*
	* @return Response
	*/
	public function config_get()
	{
		$user = user_verification();

		if (!empty($user)) {
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Config settings listed successfuly.";
			$data['states']                 = $this->commonmodel->allStatesList();
			$data['rto_numbers']            = $this->commonmodel->allRtoNumbers();
			$data['make_list']              = $this->commonmodel->allMakeList();
			$data['model_list']             = $this->commonmodel->allModelList();
			$data['company_list']           = $this->commonmodel->allCompanyList();
			$data['fitment_access']         = 1;
			$this->returnResponse['data']   = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
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
		$user = user_verification();
       
		if (!empty($user)) {
		     
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Successfuly fetched data.";
			$data['countList'] = $this->commonmodel->getNoOfCount($user);
// 			echo "<pre>";print_r($user);exit;

// 			$data['countList']['totalNoOfVehicles'] = $this->commonmodel->totalNoOfVehicle($user['user_id']);

			$trackingInfo = $this->commonmodel->trackingInfo($user['user_id']);
			$data['countList']['totalNoOfVehicles'] = $trackingInfo['tagged_all'];
// 			echo "<pre>";print_r($data);exit;
			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
		  //  echo "<pre>";print_r("data");exit;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function get_user_profile_get()
	{
		$user = user_verification();

		if (!empty($user)) {
			unset($user['user_password']);
			$user['base_url'] = 'https://www.psdn.tech/';
			$user['img_path'] = "public/temp_upload/";

			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = 'User detail fetch successfully.';
			$this->returnResponse['data']   = $user;
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function update_profile_records_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$user_type = $user['user_type'];
			$params = $this->post();
			$this->form_validation->set_data($params);

			// Validation
			$this->form_validation->set_rules('user_id', 'User ID', 'trim|required');
			$this->form_validation->set_rules('user_name', 'Name', 'trim|required');
			$this->form_validation->set_rules('user_phone', 'Phone', 'trim|required');
			$this->form_validation->set_rules('user_email', 'Email', 'trim|required');
			$this->form_validation->set_rules('user_gender', 'Gender', 'trim|required');

			$this->form_validation->set_rules(
				'user_phone', 'Phone',
				array(
					'required',
					array(
						'phone_no_already_exits',
						function ($str) {
							$userID = $this->post('user_id');
							return $this->commonmodel->verify_exits_dealer_phone_number($str, $userID);
						}
					)
				)
			);

			$this->form_validation->set_rules(
				'user_email', 'Email',
				array(
					'required',
					array(
						'email_no_already_exits',
						function ($str) {
							$userID = $this->post('user_id');
							return $this->commonmodel->verify_exits_dealer_email($str, $userID);
						}
					)
				)
			);

			// Validation verify
			if ($this->form_validation->run() == FALSE) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = $this->form_validation->error_array();
			} else {
				// Rename Profile Photo
				if (isset($params['user_photo']) && strlen($params['user_photo']) > 0) {
					if (strpos($params['user_photo'], 'temp_upload') !== false) {
						$user_photo = str_replace('public/temp_upload/', $this->user_folder, $params['user_photo']);
						rename($params['user_photo'], $user_photo);
						$params['user_photo'] = $this->remove_parent_path($user_photo);
					}
				}

				//Pass params to Model
				$response = $this->adminmodel->update_profile_records($params, ['user_id' => $user['user_id']]);
				if (empty($response)) {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = 'Unable to update profile data.';
				} else {
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = 'Successsfully updated profile data.';
				}
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function send_reset_pwd_otp_post()
	{
		$params = $this->post();
		$where = "user_phone='" . $params['phone_number'] . "' OR user_email= '" . $params['phone_number'] . "'";
		$user = $this->commonmodel->fetch_where($this->db->table_users, '*', $where);

		if (empty($user)) {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = "Given phone number or emai is not registered.";
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			// $otpNumber = rand(10000, 99999);
			$otpNumber = '12345';
			$otpMsg    = 'Please use this otp to reset your password';
			$sent_resp = $this->commonmodel->send_sms($user['user_phone'], $otpMsg);

			if ($sent_resp) {
				$updateData['user_forgot_otp'] = $otpNumber;
			 	$response = $this->adminmodel->update_profile_records($updateData, ['user_id' => $user['user_id']]);
				if (empty($response)) {
					$this->returnResponse['msg']    = 'Unable to update profile data.';
				} else {
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = 'Successsfully sent otp to given number.';
				}
			} else {
				$this->returnResponse['msg']    = "Unable to send sms. Please try again or contact admin.";
			}
			
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		}
	}

	public function validate_pwd_otp_post()
	{
		$params = $this->post();
		$where = 'user_phone="' . $params['phone_number'] . '" OR user_forgot_otp= "' . $params['otp_entered'] . '"';

		$user = $this->commonmodel->fetch_where($this->db->table_users, '*', $where);

		if (empty($user)) {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = "Please enter valid otp.";
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = 'OTP verified successfuly.';
			
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		}
	}

	public function reset_password_post()
	{
		$params = $this->post();
		$where = 'user_phone="' . $params['phone_number'] . '" AND user_forgot_otp= "' . $params['otp_entered'] . '"';

		$user = $this->commonmodel->fetch_where($this->db->table_users, '*', $where);

		if (empty($user)) {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = "Please enter valid otp.";
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$where = 'user_phone="' . $params['phone_number'] . '"';

			$user = $this->commonmodel->fetch_where($this->db->table_users, '*', $where);

			if (empty($user)) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "Please enter valid otp.";
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			} else {
				//Pass params to Model
				$response = $this->adminmodel->update_profile_records(['user_password' => md5($params['user_password'])], ['user_phone' => $params['phone_number']]);
				$this->returnResponse['status'] = true;
				$this->returnResponse['msg']    = 'Password changed successfuly.';
				
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			}
		}
	}

	public function search_device_data_get()
    {
    	$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$data['model_list']         = $this->commonmodel->fetch_imei_data($params['imei_no'], $params['start_date'], $params['start_time'], $params['end_time']);
			$data['model_list_history'] = $this->commonmodel->fetch_imei_history($params['imei_no'], $params['imei_count'], $params['start_date'], $params['start_time'], $params['end_time']);

			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Dashboard details listed successfuly.";
			$this->returnResponse['data']   = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

    public function search_device_his_data()
    {
    	$user = user_verification();

		if (!empty($user)) {
			$params = $this->input->post();
			$checkArray['model_list'] = $this->commonmodel->fetch_imei_history($params['imei_no'], $params['imei_count'], $params['start_date'], $params['start_time'], $params['end_time']);

			$data["model_list"] = $checkArray['model_list'];

			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Listed successfuly.";
			$this->returnResponse['data']   = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function device_list_post()
	{
		$user = user_verification();
        // echo "<pre>";print_r($user);exit;
		if (!empty($user)) {
			$params = $this->post();
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Successfuly fetched data.";
			$customer_id = isset($params['customer_id']) ? $params['customer_id'] : '';
			$count = $this->commonmodel->allSerialListCount($user['user_id'], $params);
			$list = $this->commonmodel->allSerialList($user['user_id'], $params);
            // echo "<pre>";print_r($count);exit;
			$data['all_count']       = $count[0]['total_count'];
			$data['used_count']      = $count[0]['used_count'];
			$data['remaining_count'] = $count[0]['new_count'];

			if (!empty($list) && isset($params['is_used']) && $params['is_used'] != 2) {
				$list = array_filter($list, function ($var) use ($params) {
				    return ($var['s_used'] == (string)$params['is_used']);
				});
			}

			$data['result_count'] = count($list);
			$data['device_list']  = array_values($list);

			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function vehicle_make_list_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Successfuly fetched data.";
			$data = $this->commonmodel->allMakeList($params);
			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function vehicle_model_list_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Successfuly fetched data.";
			$data = $this->commonmodel->allModelList($params);
			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function rto_list_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Successfuly fetched data.";
			$data = $this->commonmodel->allRtoNumbers($params);
			// $this->commonmodel->userTracking();
			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function state_list_post()
	{
		$user = user_verification();
        // echo "<pre>";print_r($user);exit;
		if (!empty($user)) {
			$params = $this->post();
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Successfuly fetched data.";
			$data = $this->commonmodel->allStatesList($params);
			// $this->commonmodel->userTracking();
			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}
	
		public function technician_list_post()
	{
		$user = user_verification();
        // echo "<pre>";print_r($user);exit;
        // $all_headers = getallheaders(); // Alternative to the CodeIgnitor method
        // $authorization_header = isset($all_headers['Authorization']) ? $all_headers['Authorization'] : '';
        // $authorization_header = $this->input->request_headers('Authorization');
        // echo "<pre>";print_r(base64_decode($authorization_header['Authorization']));exit;  
        
		if (!empty($user)) {
			$params = $this->post();
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Successfuly fetched data.";
			$data = $this->commonmodel->alltechnicianList($params);
			// $this->commonmodel->userTracking();
			$this->returnResponse['data'] = $data;

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function create_certificate_post()
	{
		$user = user_verification();
		
    //   echo "<pre>";print_r("hai");exit;
		if (!empty($user)) {
			$params = $this->post();
            // echo "<pre>";print_r(($params['panic_button']));exit;

			if (!$this->adminmodel->isSerialUsed($params['veh_serial_no'])) {
				if (!$params['veh_owner_id'] || $this->adminmodel->isCustomerIdExist($params['veh_owner_id'])) {
					$this->form_validation->set_data($params);

					// Validation
					$this->form_validation->set_rules('veh_create_date', 'Vehicle Created Date', 'trim|required');
					$this->form_validation->set_rules('validity_to', 'Vehicle Validity Date', 'trim');
					
					if (isset($params['is_new_vehicle']) && $params['is_new_vehicle'] == 0) {
						$this->form_validation->set_rules('veh_rc_no', 'Vehicle Rc Number', 'trim|required');
					}
					// $this->form_validation->set_rules('veh_chassis_no', 'Vehicle Chassis Number', 'trim|required');
					$this->form_validation->set_rules('veh_company_id', 'Vehicle Company Name', 'trim|required');
					// $this->form_validation->set_rules('veh_engine_no', 'Vehicle Engine Number', 'trim|required');
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
					$this->form_validation->set_rules('validity_to', 'Vehicle validity', 'trim|required');
					$this->form_validation->set_rules('validity_validation', 'Vehicle validation', 'trim|required');
					// $this->form_validation->set_rules('veh_speed_governer_photo', 'Device Photo', 'trim|required');
					$this->form_validation->set_rules('selling_price', 'Selling Price', 'trim|required');
					// $this->form_validation->set_rules('veh_photo', 'Vehicle Photo', 'trim|required');

					if (isset($params['veh_owner_email']) && strlen($params['veh_owner_email']) > 0) {
						$this->form_validation->set_rules('veh_owner_email', 'Email', 'required|valid_email');
					}

					$this->form_validation->set_rules(
						'veh_rc_no', 'Vehicle Rc Number',
						array(
							// 'required',
							array(
								'already_exits',
								function ($str) {
									return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_rc_no');
								}
							)
						)
					);

					$this->form_validation->set_rules(	
						'veh_chassis_no', 'Vehicle Chassis Number',
						array(
							// 'required',
							array(
								'already_exits',
								function ($str) {
									return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_chassis_no');
								}
							)
						)
					);

					$this->form_validation->set_rules(
						'veh_engine_no', 'Vehicle Engine Number',
						array(
							// 'required',
							array(
								'already_exits',
								function ($str) {
									return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_engine_no');
								}
							)
						)
					);

					/*if (isset($params['veh_rc_no']) && strlen($params['veh_rc_no']) > 0) {
						$params['veh_rc_no'] = preg_replace('/\s+/', '', $params['veh_rc_no']);
					}*/

					// Validation verify
					if ($this->form_validation->run() == FALSE) {
						$this->returnResponse['status'] = false;
						$this->returnResponse['msg']    = $this->form_validation->error_array();
					} else {
						$fetchCompanyInfo = $this->commonmodel->fetch('c_company_id', $params['veh_company_id'], 'c_company_name, c_cop_validity', $this->db->table_company);

						$params['veh_cop_validity'] = isset($fetchCompanyInfo['c_cop_validity']) ? $fetchCompanyInfo['c_cop_validity'] : date('Y-m-d H:i:s');
						$params['veh_sld_make'] = isset($fetchCompanyInfo['c_company_name']) ? $fetchCompanyInfo['c_company_name'] : "";
						$params['validity_from'] = date('Y-m-d H:i:s');
						$params['validity_to'] = date('Y-m-d H:i:s', strtotime("+" . EXPIRE_DATE_VALUE . " days"));
						$params['veh_create_date'] = date('Y-m-d');

						// Rename Vehicle Profile Photo
						if (isset($params['vehicle_owner_id_proof_photo']) && strlen($params['vehicle_owner_id_proof_photo']) > 0) {
							if (strpos($params['vehicle_owner_id_proof_photo'], 'temp_upload') !== false) {
								$profile_photo = str_replace('public/temp_upload/', $this->vehicle_owner_id_proof_folder, $params['vehicle_owner_id_proof_photo']);
								rename($params['vehicle_owner_id_proof_photo'], $profile_photo);
								$params['vehicle_owner_id_proof_photo'] = $this->remove_parent_path($profile_photo);
							}
						}

						// Rename Vehicle Owner Photo
						if (isset($params['vehicle_owners_photo']) && strlen($params['vehicle_owners_photo']) > 0) {
							if (strpos($params['vehicle_owners_photo'], 'temp_upload') !== false) {
								$profile_photo = str_replace('public/temp_upload/', $this->vehicle_owners_folder, $params['vehicle_owners_photo']);
								rename($params['vehicle_owners_photo'], $profile_photo);
								$params['vehicle_owners_photo'] = $this->remove_parent_path($profile_photo);
							}
						}

						// Rename RC Book Photo
						if (isset($params['rc_book_photo']) && strlen($params['rc_book_photo']) > 0) {
							if (strpos($params['rc_book_photo'], 'temp_upload') !== false) {
								$profile_photo = str_replace('public/temp_upload/', $this->rc_book_folder, $params['rc_book_photo']);
								rename($params['rc_book_photo'], $profile_photo);
								$params['rc_book_photo'] = $this->remove_parent_path($profile_photo);
							}
						}
						
				// 		// Rename Speed Governor Photo 
				// 		if (isset($params['veh_speed_governer_photo']) && strlen($params['veh_speed_governer_photo']) > 0) {
				// 			if (strpos($params['veh_speed_governer_photo'], 'temp_upload') !== false) {
				// 				$profile_photo = str_replace('public/temp_upload/', $this->veh_speed_governer_folder, $params['veh_speed_governer_photo']);
				// 				rename($params['veh_speed_governer_photo'], $profile_photo);
				// 				$params['veh_speed_governer_photo'] = $this->remove_parent_path($profile_photo);
				// 			}
				// 		}

                        // subash changes start
                        // Rename Speed Governor Photo 
                        if (isset($params['veh_speed_governer_photo']) && strlen($params['veh_speed_governer_photo']) > 0) {
                            $sourcePath = 'public/temp_upload/' . basename($params['veh_speed_governer_photo']);
							if (strpos($params['veh_speed_governer_photo'], 'temp_upload') !== false) {
								$imagePath = $params['veh_speed_governer_photo'];
								$imageData = explode('/', $imagePath);
								$imageName = $imageData[2];
								$path = "public/upload/vehicle";
								$deviceImage = $this->awsImageUpload($imagePath, $imageName, $path);
								// echo "<pre>";print_r($deviceImage);
								$dats = explode('/', $deviceImage);
								$params['veh_speed_governer_photo'] = $path.'/'.$dats[6];
								if (isset($params['veh_speed_governer_photo']) && strlen($params['veh_speed_governer_photo']) > 0) {
									unlink($imagePath);
								}
							}
                            // $destinationPath = '/home/psdntech/public_html/public/upload/vehicle/' . basename($params['veh_speed_governer_photo']);
                            
                            // if (file_exists($sourcePath)) {
                            //     if (rename($sourcePath, $destinationPath)) {
                            //         // Renaming successful
                            //         $params['veh_speed_governer_photo'] = $this->remove_parent_path($destinationPath);
                            //     } else {
                            //         // Handle renaming failure
                            //         $this->returnResponse['status'] = false;
						    //     	$this->returnResponse['msg'] = "Failed to rename the file.";
                            //     }
                            // } else {
                            //     // Handle the case where the source file does not exist
                            //         $this->returnResponse['status'] = false;
						    //     	$this->returnResponse['msg'] = "veh_photo Source file does not exist.";
                            // }
                        }
                        
                        //stop
						// Rename Vehicle Photo
				// 		if (isset($params['veh_photo']) && strlen($params['veh_photo']) > 0) {
				// 			if (strpos($params['veh_photo'], 'temp_upload') !== false) {
				// 				$profile_photo = str_replace('public/temp_upload/', $this->veh_photo_folder, $params['veh_photo']);
				// 				rename($params['veh_photo'], $profile_photo);
				// 				$params['veh_photo'] = $this->remove_parent_path($profile_photo);
				// 			}
				// 		}


                    //subash changes => start
                    
                    if (isset($params['veh_photo']) && strlen($params['veh_photo']) > 0) {
                        if (strpos($params['veh_photo'], 'temp_upload') !== false) {
							$vehImagePath = $params['veh_photo'];
							$vehImageData = explode('/', $vehImagePath);
							$imageName1 = $vehImageData[2];
							$path = "public/upload/vehicle";
							$vehicleImage = $this->awsImageUpload($vehImagePath, $imageName1, $path);
							// echo "<pre>";print_r($vehicleImage);
							$dats = explode('/', $vehicleImage);
							$params['veh_photo']  = $path.'/'.$dats[6];
							// echo "<pre>";print_r($params['veh_photo']);exit;
							if (isset($params['veh_photo']) && strlen($params['veh_photo']) > 0) {
								unlink($vehImagePath);
							}
                            // $source_file = $params['veh_photo'];
                            // $target_file = str_replace('public/temp_upload/', $this->veh_photo_folder, $params['veh_photo']);
                    
                            // if (file_exists($source_file)) {
                            //     if (rename($source_file, $target_file)) {
                            //         $params['veh_photo'] = $this->remove_parent_path($target_file);
                            //     } else {
                            //         // Handle renaming failure
                            //         $this->returnResponse['status'] = false;
						    //     	$this->returnResponse['msg'] = "Failed to rename the file.";
                            //     }
                            // } else {
                            //     // Handle the case where the source file does not exist
                            //         $this->returnResponse['status'] = false;
						    //     	$this->returnResponse['msg'] = "veh_photo Source file does not exist.";
                            // }
                        }
                    }
                    
                    
                    //stop
						$params['veh_created_user_id'] = $user['user_id'];

						//Pass params to Model
						$info     = $this->adminmodel->create_new_vehicle_records($user, $params);
						$insert_id = $info['insert_id'];

						if (empty($insert_id)) {
							$this->returnResponse['status'] = false;
							$this->returnResponse['msg'] = "Please Enter valid Details or Something went wrong.";
						} else {
							$veh_owner_id = $info['veh_owner_id'];
							$this->adminmodel->add_tracking_entry($user, $insert_id, $veh_owner_id);

							$encodeID = base64_encode(base64_encode(base64_encode($insert_id)));
							$tinyurl = $this->get_tiny_url('https://www.psdn.tech/admin/downloadwebpdf?id=' . $encodeID);
							$SMS = 'Cerificate Created successfully, ref url :' . $tinyurl;
							log_message('error', $SMS);
							$this->commonmodel->send_sms($params['veh_owner_phone'], $SMS);
							$this->returnResponse['status'] = true;
							$this->returnResponse['msg']    = "Certificate created successfully.";
						}
					}
				} else {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = 'Selected customer/owner detail is not available.';
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = 'Serial number is already assigned to another vehicle.';
			}
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}


	public function awsImageUpload($imagePath, $imageName, $path){
        try{
            // echo "<pre>";print_r("imagePath =>".$imagePath." imageName =>".$imageName." path =>".$path);exit;
            // "imagePath =>public/temp_upload/1697088606.png imageName =>1697088606.png path =>public/upload/vehicle"
            // s3 Bucket connect
            $credentials = [
                'key'    => 'AKIASHD435HUONSXGNLH',
                'secret' => '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',
            ];
            $s3Client = new S3Client([
                'version'     => 'latest',
                'region'      => 'ap-south-1',
                'credentials' => $credentials
            ]);

            /* $bucket          = "psdn-v1"; */
            $bucket          = "techpsdn";
            $folderName      = $path;
            $folderExists    = false;
            list($txt, $ext) = explode(".", $imageName);
            $contentType     = strtolower($ext);

            //check folder
            try {
                $findFolder = $s3Client->listObjects([
                    'Bucket' => $bucket,
                    'Prefix' => $folderName . '/',
                    'MaxKeys'=> 1,
                ]);
                if ($findFolder['Contents']) {
                    $folderExists = true;
                }
                // echo "Folder exists!";
            } catch (S3Exception $e) {
                if ($e->getStatusCode() !== 404) {
                    echo "An error occurred: " . $e->getMessage();
                    exit;
                }
            }

            // Create the folder if it doesn't exist
            if (!$folderExists) {
                $result = $s3Client->putObject([
                    'Bucket'      => $bucket,
                    'Key'         => $folderName."/".$imageName,
                    'SourceFile'  => $imagePath,	               // public/temp_upload/1686202313.jpg
                    'ContentType' => $contentType,		           // jpg or png (File Type)
                    'StorageClass' => 'REDUCED_REDUNDANCY'       
                ]);
                    // echo "<pre>";print_r($result['ObjectURL']);exit;

                $url = $result['ObjectURL'];
                return $url;
            }

            // Upload the image to the folder  
            try {
                // echo "<pre>";print_r($folderName."/".$imageName);
                // echo "<pre>";print_r("impa".$imagePath);
                // echo "<pre>";print_r("ct".$contentType);exit;
                $result1 = $s3Client->putObject([
                    'Bucket'      => $bucket,
                    'Key'         => $folderName."/".$imageName,   // sample/45614565.jpg
                    'SourceFile'  => $imagePath,	               // public/temp_upload/1686202313.jpg
                    'ContentType' => $contentType,		           // jpg or png (File Type)
                    'StorageClass' => 'REDUCED_REDUNDANCY'
                ]);

                $urlOutput = $result1['ObjectURL'];
                return $urlOutput;

            }catch (S3Exception $e) {
                die('Error:' . $e->getMessage());
            } catch (Exception $e) {
                die('Error:' . $e->getMessage());
            } 
        }catch (S3Exception $e) {
            echo "An error occurred: " . $e->getMessage();
            exit;
        }
    }

	
	public function view_cerficate_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$VehicleID=$params['id'];

			if (!isset($VehicleID) || (string)$VehicleID === '0') {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "Certificate id is invalid.";
			} else {
				$data['vehicleinfo'] = $this->commonmodel->infoOfvehicle($user, $VehicleID);

				if (!empty($data['vehicleinfo'])) {
					$data['vehicleinfo'][0]['cat_name'] = vehicle_category($data['vehicleinfo'][0]['veh_cat']);
					$data['vehicleinfo'][0]['base_url'] = 'https://www.psdn.tech/';
					$data['vehicleinfo'][0]['img_path'] = "public/temp_upload/";
					$pdfEncode=base64_encode(base64_encode(base64_encode($data['vehicleinfo'][0]['veh_id'])));
				// 	echo "<pre>";print_r($pdfEncode);exit;
            		$data['vehicleinfo'][0]['downloadurl'] = "https://www.psdn.tech/admin/downloadwebpdf?id=".$pdfEncode;
            		//$data['map_data'] = $this->commonmodel->map_data($data['vehicleinfo'][0]['s_imei']);

					// Load Content
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = "Certificate listed successfuly.";
					$this->returnResponse['data']    = $data;
				} else {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = "Certificate not found.";
				}
			}
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
	public function certificate_list_post()
	{
		$user = user_verification();
        // echo "<pre>";print_r($user);exit;
		if (!empty($user)) {
			$params = $this->post();
		
			$user_id = $user['user_id'];

			if (!isset($_GET['start_date']) || strlen($_GET['start_date']) === 0) {
				$_GET['start_date'] = 0;
			}

			if (!isset($_GET['end_date']) || strlen($_GET['end_date']) === 0) {
				$_GET['end_date'] = 0;
			}

			$search = isset($params['search']) ? $params['search'] : '';
			$limit = isset($params['limit']) ? $params['limit'] : 50;
			$offset = isset($params['offset']) ? $params['offset'] : 0;
			$is_used = isset($params['is_used']) ? $params['is_used'] : 0;
			$customer_id = isset($params['customer_id']) ? $params['customer_id'] : '';

			$data['totalNoOfVehicles'] = $this->commonmodel->totalNoOfVehicle($user_id, $is_used);

			$trackingInfo = $this->commonmodel->trackingInfo($user_id);
			$data['totalNoOfVehicles']        = $trackingInfo['tagged_all'];
			$data['totalNoOfWorkingVehicles'] = $trackingInfo['tagged_working'];
			$tagged_working_ids               = explode(',', $trackingInfo['tagged_working_ids']);
			$data['totalNoOfoOffVehicles']    = $trackingInfo['tagged_offline'];
			$tagged_offline_ids               = explode(',', $trackingInfo['tagged_offline_ids']);
			
			$faultyCount = $this->commonmodel->countFaulty($user_id);
			$data['totalNoOfcliVehicles'] = strval($faultyCount);
// 			$tagged_client_issue_ids = explode(',', $trackingInfo['tagged_client_issue_ids']);
			$filteredIds = [];
			if ($is_used == 1) {
				$filteredIds = $tagged_working_ids;
			} else if ($is_used == 2) {
				$filteredIds = $tagged_offline_ids;
			} else if ($is_used == 3) {
				$filteredIds = $tagged_client_issue_ids;
			}

			$data['listofvehicles'] = $this->commonmodel->listofvehicle($user, $search, $is_used, $limit, $offset, $customer_id, $filteredIds);
			//$data['customer_list']=$this->commonmodel->allCustomerList();

			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Certificate listed successfuly...";
			$this->returnResponse['data']    = $data;
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
	public function vehicle_category_list_get()
	{
		$user = user_verification();

		if (!empty($user)) {
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Certificate listed successfuly.";
			$this->returnResponse['data']   = vehicle_category();
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function update_certificate_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$this->form_validation->set_data($params);
			if (isset($params['veh_id'])) {
				$params['veh_id'] = base64_decode($params['veh_id']);
				// Validation
				$this->form_validation->set_rules('veh_id', 'Vehicle ID', 'trim|required');
				$this->form_validation->set_rules('veh_create_date', 'Vehicle Created Date', 'trim|required');
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
				$this->form_validation->set_rules('veh_photo', 'Vehicle Photo', 'trim|required');
				$this->form_validation->set_rules('selling_price', 'Selling Price', 'trim|required');
				$this->form_validation->set_rules(
					'veh_rc_no', 'Vehicle Rc Number',
					array(
						'required',
						array(
							'already_exits',
							function ($str) {
								$veh_id = $this->input->post('veh_id');
								$veh_id = base64_decode($veh_id);
								return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_rc_no', $veh_id);
							}
						)
					)
				);

				$this->form_validation->set_rules(
					'veh_chassis_no', 'Vehicle Chassis Number',
					array(
						'required',
						array(
							'already_exits',
							function ($str) {
								$veh_id = $this->input->post('veh_id');
								$veh_id = base64_decode($veh_id);
								return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_chassis_no', $veh_id);
							}
						)
					)
				);

				$this->form_validation->set_rules(
					'veh_engine_no', 'Vehicle Engine Number',
					array(
						'required',
						array(
							'already_exits',
							function ($str) {
								$veh_id = $this->input->post('veh_id');
								$veh_id = base64_decode($veh_id);
								return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_engine_no', $veh_id);
							}
						)
					)
				);

				/*if (isset($params['veh_rc_no']) && strlen($params['veh_rc_no']) > 0) {
					$params['veh_rc_no'] = preg_replace('/\s+/', '', $params['veh_rc_no']);
				}*/

				// Validation verify
				if ($this->form_validation->run() == FALSE) {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = $this->form_validation->error_array();
				} else {
					$fetchCompanyInfo = $this->commonmodel->fetch('c_company_id', $params['veh_company_id'], 'c_company_name, c_cop_validity', $this->db->table_company);
					$params['veh_cop_validity'] = isset($fetchCompanyInfo['c_cop_validity']) ? $fetchCompanyInfo['c_cop_validity'] : date('Y-m-d H:i:s');
					$params['veh_sld_make'] = isset($fetchCompanyInfo['c_company_name']) ? $fetchCompanyInfo['c_company_name'] : "";
					$params['validity_from'] = date('Y-m-d H:i:s', strtotime($params['veh_create_date']));

					// Rename Vehicle owner id Photo
					if (isset($params['vehicle_owner_id_proof_photo']) && strlen($params['vehicle_owner_id_proof_photo']) > 0) {
						if (strpos($params['vehicle_owner_id_proof_photo'], 'temp_upload') !== false) {
							$profile_photo = str_replace('public/temp_upload/', $this->vehicle_owner_id_proof_folder, $params['vehicle_owner_id_proof_photo']);
							rename($params['vehicle_owner_id_proof_photo'], $profile_photo);
							$params['vehicle_owner_id_proof_photo'] = $profile_photo;
						}
					}
			
					// Rename Vehicle Owner photo
					if (isset($params['vehicle_owners_photo']) && strlen($params['vehicle_owners_photo']) > 0) {
						if (strpos($params['vehicle_owners_photo'], 'temp_upload') !== false) {
							$profile_photo = str_replace('public/temp_upload/', $this->vehicle_owners_folder, $params['vehicle_owners_photo']);
							rename($params['vehicle_owners_photo'], $profile_photo);
							$params['vehicle_owners_photo'] = $profile_photo;
						}
					}
			
					// Rename RC Book Photo
					if (isset($params['rc_book_photo']) && strlen($params['rc_book_photo']) > 0) {
						if (strpos($params['rc_book_photo'], 'temp_upload') !== false) {
							$profile_photo = str_replace('public/temp_upload/', $this->rc_book_folder, $params['rc_book_photo']);
							rename($params['rc_book_photo'], $profile_photo);
							$params['rc_book_photo'] = $profile_photo;
						}	
					}
			
					// Rename Speed Governor Photo
					if (isset($params['veh_speed_governer_photo']) && strlen($params['veh_speed_governer_photo']) > 0) {
						if (strpos($params['veh_speed_governer_photo'], 'temp_upload') !== false) {
							$profile_photo = str_replace('public/temp_upload/', $this->veh_speed_governer_folder, $params['veh_speed_governer_photo']);
							rename($params['veh_speed_governer_photo'], $profile_photo);
							$params['veh_speed_governer_photo'] = $profile_photo;
						}
					}

					// Rename Vehicle Profile Photo
					if (isset($params['veh_photo']) && strlen($params['veh_photo']) > 0) {
						if (strpos($params['veh_photo'], 'temp_upload') !== false) {
							$profile_photo = str_replace('public/temp_upload/', $this->veh_photo_folder, $params['veh_photo']);
							rename($params['veh_photo'], $profile_photo);
							$params['veh_photo'] = $profile_photo;
						}
					}

					//Pass params to Model
					$response = $this->adminmodel->update_vehicle_records($user, $params);

					if (empty($response)) {
						$this->returnResponse['status'] = false;
						$this->returnResponse['msg'] = "Please Enter valid Details or Something went wrong.";
					} else{
						$this->returnResponse['status'] = true;
						$this->returnResponse['msg']    = 'Certificate updated successfully.';
					}
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg'] = "Vehicle ID missing.";
			}
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function mbl_update_certificate_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$this->form_validation->set_data($params);
			if (isset($params['veh_id'])) {
				// $params['veh_id'] = base64_decode($params['veh_id']);
				// Validation
				$this->form_validation->set_rules(
					'veh_rc_no', 'Vehicle Rc Number',
					array(
						array(
							'already_exits',
							function ($str) {
								$veh_id = $this->input->post('veh_id');
								$veh_id = base64_decode($veh_id);
								return $this->commonmodel->verify_exits_vehicle_records($str, 'veh_rc_no', $veh_id);
							}
						)
					)
				);

				// Validation verify
				if ($this->form_validation->run() == FALSE) {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = $this->form_validation->error_array();
				} else {
					// Rename RC Book Photo
					if (isset($params['rc_book_photo']) && strlen($params['rc_book_photo']) > 0) {
						if (strpos($params['rc_book_photo'], 'temp_upload') !== false) {
							$profile_photo = str_replace('public/temp_upload/', $this->rc_book_folder, $params['rc_book_photo']);
							rename($params['rc_book_photo'], $profile_photo);
							$params['rc_book_photo'] = $profile_photo;
						}	
					}
			
					// Rename Vehicle Profile Photo
					if (isset($params['veh_photo']) && strlen($params['veh_photo']) > 0) {
						if (strpos($params['veh_photo'], 'temp_upload') !== false) {
							$profile_photo = str_replace('public/temp_upload/', $this->veh_photo_folder, $params['veh_photo']);
							rename($params['veh_photo'], $profile_photo);
							$params['veh_photo'] = $profile_photo;
						}
					}

					//Pass params to Model
					$response = $this->adminmodel->mbl_update_vehicle_records($user, $params);

					if (empty($response)) {
						$this->returnResponse['status'] = false;
						$this->returnResponse['msg'] = "Please Enter valid Details or Something went wrong.";
					} else{
						$this->returnResponse['status'] = true;
						$this->returnResponse['msg']    = 'Certificate updated successfully.';
					}
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg'] = "Vehicle ID missing.";
			}
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	// Upload  photo
	public function upload_image_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$path = "public/temp_upload/";
			
			if (!is_dir($path)) {
				mkdir($path, 0777, TRUE);
			}

			$returnResponse=array();
			$valid_formats = array("jpg", "png", "gif", "bmp","jpeg");

			try {
				if(isset($_POST) and $_SERVER['REQUEST_METHOD'] == "POST") {
					if($_FILES) {
						$name = $_FILES['image_file']['name'];
						$size = $_FILES['image_file']['size'];

						if(strlen($name)) {
							$ext = pathinfo($name, PATHINFO_EXTENSION);

							if (in_array(strtolower($ext),$valid_formats)) {
								if ($size < 5242880) {
									$actual_image_name = time().".".$ext;
									$tmp = $_FILES['image_file']['tmp_name'];
 
									if (move_uploaded_file ($tmp, $path.$actual_image_name))
									{
										$this->returnResponse['status']   = true;
										$this->returnResponse['msg']      = 'Successsfully uplopaded image.';
										$this->returnResponse['base_url'] = base_url();
										// $this->returnResponse['base_url'] = AWS_S3_BUCKET_URL;
										$this->returnResponse['path']     = $path;
										$this->returnResponse['img_name'] = $actual_image_name;
										$config['image_library'] = 'gd2';
										$config['source_image'] =  $path.$actual_image_name;
										$config['create_thumb'] = FALSE;
										$config['maintain_ratio'] = TRUE;
										$config['quality'] = '60%';
										$config['width'] = 400;
										$config['height'] = 400;
										$config['new_image'] =  $path.$actual_image_name;
										$this->load->library('image_lib', $config);
										$this->image_lib->resize();
									} else {
										$this->returnResponse['status'] = false;
										$this->returnResponse['msg']    = 'Failed to upload image';
									}
								} else {
									$this->returnResponse['status'] = false;
									$this->returnResponse['msg']    = 'Image file size max 5 MB';
								}
							} else {
								$this->returnResponse['status'] = false;
								$this->returnResponse['msg']    = 'Invalid file format..';
							}
						} else {
							$this->returnResponse['status'] = false;
							$this->returnResponse['msg']    = 'Please select image..!';
						}
					} else {
						$this->returnResponse['status'] = false;
						$this->returnResponse['msg']    = 'Please select image..!';
					}
				}
			} catch(Exception $e) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = $e->getMessage();
			}
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/**
	 * Admin login api.
	 *
	 * @return Response
	*/
	public function find_customer_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			// Validation
			$params = $this->post();
			$this->form_validation->set_data($params);
			$this->form_validation->set_rules('phone_number', 'Phone Number', 'required');

			// Validation verify
			if ($this->form_validation->run() == FALSE) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = $this->form_validation->error_array();
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			} else {
				//Pass params to Model
				$response = $this->adminmodel->find_customer_by_phone($params['phone_number']);
				// print_r($response); exit;
				if (empty($response)) {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = "No user found.";
					$this->response($this->returnResponse, REST_Controller::HTTP_OK);
				} else {
					if (isset($response['user_password'])) {
						unset($response['user_password']);
					}

					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = 'User found.';
					$this->returnResponse['data']   = $response;
					$this->response($this->returnResponse, REST_Controller::HTTP_OK);
				}
			}
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}
    
    public function customers_list_post()
    {
    	$user = user_verification();
        // echo "<pre>";print_r($user);exit;
		if (!empty($user)) {
			$params = $this->post();
			$limit = ($params['limit'] > 0 ? $params['limit'] : 50);
        	$offset = ($params['offset'] > 0 ? $params['offset'] : 0);

			$search = isset($params['search']) ? $params['search'] : '';
			$customer_id = isset($params['customer_id']) ? $params['customer_id'] : '';

			$response['totalNoOfCustomers'] = $this->commonmodel->totalNoOfCustomersDealer($user['user_id']);
			$response['listofCustomers'] = $this->commonmodel->listofCustomersListDealer($limit, $offset, $search,$user['user_id'],$customer_id);
			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = 'Customer listed successfuly.';
			$this->returnResponse['data']   = $response;
			$this->returnResponse['data']['base_url'] = 'http://www.psdn.live/';
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }
    
    public function view_customer_post()
    {
    	$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			$customer_id = isset($params['customer_id']) ? $params['customer_id'] : '';

			if ($customer_id != '') {
				$response['customer_data'] = $this->commonmodel->getCustomerData($user['user_id'], $customer_id);
				if (!empty($response['customer_data'])) {
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = 'Customer retrieved successfuly.';
					$this->returnResponse['data']   = $response;
					$this->response($this->returnResponse, REST_Controller::HTTP_OK);
				} else {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = 'Customer not found.';
					$this->response($this->returnResponse, REST_Controller::HTTP_OK);
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = 'Customer id missing.';
				$this->response($this->returnResponse, REST_Controller::HTTP_OK);
			}
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

    public function imei_ota_save_post()
    {
		$user = user_verification();

		if (!empty($user)) {
			// Validation
			$params = $this->post();
			$this->form_validation->set_data($params);

			$this->form_validation->set_rules('selectedVal', 'OTA Value', 'required');
			$this->form_validation->set_rules('imei', 'IMEI', 'required');

			// Validation verify
			if ($this->form_validation->run() == FALSE) {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = $this->form_validation->error_array();
			} else {
				// $inserted = $this->commonmodel->updateOTAForIMEI($user, $params);
				if ($this->commonmodel->updateOTAForIMEI($user, $params)) {
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = 'Saved successfully.';
				} else {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = 'Unable to save data.';
				}
			}
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
        } else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

    public function get_fitment_list_post()
    {		
		$user = user_verification();
        // echo "<pre>";print_r($user);exit;
		if (!empty($user)) {
			$params = $this->post();
			 //echo "<pre>";print_r($params);exit;
			$data = $this->technicianmodel->get_fitment_list_new($params,$user);
			$data = json_encode($data, JSON_PRETTY_PRINT );
			$data = json_decode($data, true, JSON_UNESCAPED_SLASHES);

			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Listed successfuly.";
			$this->returnResponse['data']   = $data;
			$this->returnResponse['base_url'] = 'https://www.psdn.tech/';
			$this->returnResponse['img_path'] = "public/temp_upload/";
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }
    
    public function get_lat_long_post(){
        $user = user_verification();
        // echo "<pre>";print_r($user);exit;
        if (!empty($user)) {
			$params = $this->post();
			// echo "<pre>";print_r($params);exit;
			if (isset($params['imei']) && $params['imei'] != "")
			{
				$latLong = $this->technicianmodel->get_lat_long($params);
                $data    = $this->commonmodel->map_data($params['imei']);
                // echo "<pre>";print_r($data['latitude']);
                // echo "<pre>";print_r($latLong[0]);exit;
                // echo "<pre>";print_r($data);exit;
                $data['latitude']  = $latLong[0]['latitude'];
                $data['longitude'] = $latLong[0]['longitude'];
				$this->returnResponse['status'] = true;
				$this->returnResponse['msg']    = "Data Listed successfuly.";
				$this->returnResponse['data']   = $data;
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "IMEI is missing.";
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

    public function get_fitment_data_post()
    {
    	$user = user_verification();
		$params = $this->post();
		
		if (!empty($user)) {
			$params = $this->post();
			if (isset($params['imei']))
			{
				$data = $this->technicianmodel->get_fitment_list($params);

				$this->returnResponse['status'] = true;
				$this->returnResponse['msg']    = "Data Listed successfuly.";
				$this->returnResponse['data']   = $data;
				$this->returnResponse['base_url'] = 'https://www.psdn.tech/';
				$this->returnResponse['img_path'] = "public/temp_upload/";
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "Scanned data is missing.";
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function get_map_data_list_post()
	{
		$user = user_verification();
        // echo "<pre>";print_r($user);exit;
		if (!empty($user)) {
			$params = $this->post();

			$user_id = $user['user_id'];
			$imei = isset($params['imei']) ? $params['imei'] : '';

			if ($imei != '') {
				$data = $this->commonmodel->map_data($imei);
				$this->returnResponse['status'] = true;
				$this->returnResponse['msg']    = "Map data listed successfuly.";
				$this->returnResponse['data']    = $data;
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "IMEI data required.";
				$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function get_app_version_get()
	{
	   // echo "<pre>";print_r("user");exit;
		$this->returnResponse['status'] = true;
		$this->returnResponse['msg']    = "Success";
		$this->returnResponse['app_version']   = APP_VERSION;
		$this->response($this->returnResponse, REST_Controller::HTTP_OK);
	}
	
		public function sample_get()
	{
	    echo "<pre>";print_r("user");exit;
		$this->returnResponse['msg']    = "Success";
		$this->response($this->returnResponse, REST_Controller::HTTP_OK);
	}
	
		public function api_check_get()
	{
	    echo "<pre>";print_r("Hello World");exit;

	}

    public function get_tiny_url($url)
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, 'http://tinyurl.com/api-create.php?url=' . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    
    public function get_scan_manual_data_post()
    {
    	$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			if (isset($params['scanned_data']))
			{
				$search_data = explode(';', $params['scanned_data']);

				if (count($search_data) == 1) {
					$params["imei"] = $search_data[0];
				} else {
					$params["imei"] = $search_data[0];
				}
				
				$data = $this->technicianmodel->get_imei_data($params);

				if ($data) {
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = "Listed successfuly.";
					$this->returnResponse['data']   = $data;
					$this->returnResponse['base_url'] = 'https://www.psdn.tech/';
					$this->returnResponse['img_path'] = "public/temp_upload/";
				} else {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = "No data found.";
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "Scanned data is missing.";
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

	public function save_fitment_data_post()
	{
		$user = user_verification();

		if (!empty($user)) {
			$params = $this->post();
			if (!$this->technicianmodel->is_fitment_completed($params)) {
				$this->form_validation->set_data($params);

				// Validation
				$this->form_validation->set_rules('fitment_imei', 'IMEI value', 'trim|required');
				$this->form_validation->set_rules('fitment_lat_lng', 'Latitude and Longitude', 'trim|required');
				$this->form_validation->set_rules('fitment_picture', 'Picture', 'trim|required');
				$this->form_validation->set_rules('fitment_comments', 'Comments', 'trim');

				// Validation verify
				if ($this->form_validation->run() == FALSE) {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = $this->form_validation->error_array();
				} else {
					// Rename Fitment Photo
					if (isset($params['fitment_picture']) && strlen($params['fitment_picture']) > 0) {
						if (strpos($params['fitment_picture'], 'temp_upload') === false) {
							$params['fitment_picture'] = 'public/temp_upload/' . $params['fitment_picture'];
						}

						if ('public/temp_upload/' . $params['fitment_picture']) {
							$fitment_picture = str_replace('public/temp_upload/', $this->fitment_folder, $params['fitment_picture']);
							rename($params['fitment_picture'], $fitment_picture);
							$params['fitment_picture'] = $this->remove_parent_path($fitment_picture);
						}
					}

					$params['fitment_user_id'] = $user['user_id'];

					//Pass params to Model
					$insert_id     = $this->technicianmodel->save_fitment_records($user, $params);

					if (!$insert_id) {
						$this->returnResponse['status'] = false;
						$this->returnResponse['msg'] = "Please Enter valid Details or Something went wrong.";
					} else {
						$this->technicianmodel->save_fitment_data($params);
						$this->returnResponse['status'] = true;
						$this->returnResponse['msg']    = "Fitment saved successfully.";
					}
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg'] = "Fitment already completed.";
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['status'] = false;
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

 	public function get_console_log_post()
    {
        $user = user_verification();
		$params = $this->post();

		if (!empty($user)) {
			$params = $this->post();
			if (isset($params['imei']))
			{
				$data = $this->technicianmodel->get_console_data($params);

				if ($data){
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = "Data Listed successfuly.";
					$this->returnResponse['data']   = (array) $data;
				} else {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = "Device is not sending data.";
					$this->returnResponse['data']   = (array) $data;
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "IMEI is missing.";
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

 	public function get_registered_console_data_post()
    {
        // echo "<pre>";print_r('get_registered_console_data');exit;
        $user = user_verification();
		$params = $this->post();

		if (!empty($user)) {
			$params = $this->post();
			if (isset($params['imei']))
			{
				$data = $this->technicianmodel->get_registered_console_data($params);

				if ($data){
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = "Data Listed successfuly.";
					$this->returnResponse['data']   = (array) $data;
				} else {
					$this->returnResponse['status'] = false;
					$this->returnResponse['msg']    = "Device is not sending data.";
					$this->returnResponse['data']   = (array) $data;
				}
			} else {
				$this->returnResponse['status'] = false;
				$this->returnResponse['msg']    = "IMEI is missing.";
			}

			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }
}