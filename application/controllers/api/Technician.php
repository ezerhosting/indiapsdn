<?php
   
require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/JWT.php';
     
class Technician extends REST_Controller 
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
		$this->fitment_folder                = $this->uploadpath . 'fitments/';
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
					$this->returnResponse['msg']    = "Only dealer can access.";
				} else {
					if (isset($response['user_password'])) {
						unset($response['user_password']);
					}

					$response['base_url']       = 'https://www.psdn.tech/';
					$response['dealer_url']     = 'https://www.psdn.tech/api/admin/';
					$response['technician_url'] = 'https://www.psdn.tech/api/technician/';
					$response['img_path']       = "public/temp_upload/";

					$this->returnResponse['token']  = $this->encode_token_get($response);
					$this->returnResponse['status'] = true;
					$this->returnResponse['msg']    = 'Logged-in successfuly.';
					$this->returnResponse['data']   = $response;
				}
			}
		}
		$this->response($this->returnResponse, REST_Controller::HTTP_OK);
	}

	public function get_user_profile_get()
	{
		$user = technician_verification();

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
		$user = technician_verification();

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

    public function get_scan_manual_data_post()
    {
    	$user = technician_verification();

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
		$user = technician_verification();

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

    public function get_fitment_list_post()
    {		
		$user = technician_verification();
// 		echo "<pre>";print_r($user);exit;

		if (!empty($user)) {
			$params = $this->post();
			$data = $this->technicianmodel->get_fitment_list_new($params,$user);
			$data = json_encode($data, JSON_PRETTY_PRINT );
			$data = json_decode($data, true, JSON_UNESCAPED_SLASHES);

			$this->returnResponse['status'] = true;
			$this->returnResponse['msg']    = "Listed successfuly.";
			$this->returnResponse['data']   = $data;
			$this->returnResponse['base_url'] = 'https://www.psdn.tech/';
			$this->returnResponse['img_path'] = "public/upload/fitments/";
			$this->response($this->returnResponse, REST_Controller::HTTP_OK);
		} else {
			$this->returnResponse['msg']    = REST_Controller::PermissionErrMsg;
			$this->response($this->returnResponse, REST_Controller::HTTP_UNAUTHORIZED);
		} 

    }

    public function get_fitment_data_post()
    {
    	$user = technician_verification();
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
				$this->returnResponse['img_path'] = "public/upload/fitments/";
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
    
    
 	public function get_console_log_post()
   {
      $user = technician_verification();
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
      $user = technician_verification();
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

	// Upload  photo
	public function upload_image_post()
	{
		$user = technician_verification();

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
								if ($size < 15242880) {
									$actual_image_name = time().".".$ext;
									$tmp = $_FILES['image_file']['tmp_name'];
 
									if (move_uploaded_file ($tmp, $path.$actual_image_name))
									{
										$this->returnResponse['status']   = true;
										$this->returnResponse['msg']      = 'Successsfully uplopaded image.';
										$this->returnResponse['base_url'] = base_url();
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
									$this->returnResponse['msg']    = 'Image file size max 15 MB';
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

	public function imei_ota_save_post()
	{
		$user = technician_verification();

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

	/**
	* Get dashboard datas.
	*
	* @return Response
	*/
	public function get_map_data_list_post()
	{
		$user = technician_verification();

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
		$this->returnResponse['status'] = true;
		$this->returnResponse['msg']    = "Success";
		$this->returnResponse['app_version']   = APP_VERSION;
		$this->response($this->returnResponse, REST_Controller::HTTP_OK);
	}
}