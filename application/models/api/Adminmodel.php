<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Adminmodel extends CI_Model {

        public function __construct()
        {
                parent::__construct();             
        }
		
	public function verify_user($params)
	{
		$this->db->select('*');
		$this->db->where('user_phone', $params['phone_number']);
		$this->db->where('user_password', md5($params['password_value']));
		$this->db->where('user_status', 1);
		//$this->db->where('user_type', 1);//dealer only allowed
		$this->db->from($this->db->table_users);
		$result = $this->db->get();
		$result = $result->row_array();
		return $result;
	}


	public function fetch_imei_data($params,$start_date,$start_time,$end_time)
	{
		$DB2 = $this->load->database('postgre_db', TRUE);

		$from = $start_date.' '.$start_time;
		$to = $start_date.' '.$end_time;
		$query = "select * from public.tbl_health_data where imei = '" . $params . "' AND server_reached between '" . $from . "' AND '" . $to . "'  ORDER BY id DESC LIMIT 5";
		//       echo $query;exit;
		$data = $DB2->query($query)->result();
		/*
		$rowData = "";
		for ($i = 0; $i < count($data); $i++) {
			$rowData = $rowData . "<tr>";

			$rowData = $rowData . '<td>' . $data[$i]->vendor_name . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->firmware_v . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->imei . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->server_reached . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->battery_percentage . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->battery_threshold . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->memory_percentage . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->data_interval . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->input_value . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->output_value . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->adc_one . '</td>';
			$rowData = $rowData . '<td>' . $data[$i]->adc_two . '</td>';

			$rowData = $rowData . "</tr>";
		}*/


		if (count($data) == 0) {
			$resultData = "Health data not found on server";
		} else {
			$resultData = $data;
		}

		return $resultData;
	}


	public function fetch_imei_history($params, $imei_count,$start_date,$start_time,$end_time)
	{
		$from = $start_date.' '.$start_time;
		$to = $start_date.' '.$end_time;

		$DB2 = $this->load->database('postgre_db', TRUE);
		$query = "select * from public.tbl_trackingalldatas where imei = '" . $params . "' AND server_reached between '" . $from . "' AND '" . $to. "' ORDER BY gps_sent desc";
		//      echo $query;exit();
		$data = $DB2->query($query)->result();
		//

		$rowData = "";
		$coordinates = array();
		for ($i = 0; $i < count($data); $i++) {
		$rowData = $rowData . "<tr>";

		$rowData = $rowData . '<td>' . ($i + 1) . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->vendor_id . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->firmware_version . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->packet_type . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->packet_status . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->imei . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->vehicle_reg_no . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->latitude . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->longitude . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->vehicle_speed . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->distance . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->cumulative_distance . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->lat_direction . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->long_direction . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->gps_sent . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->server_reached . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->ignition . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->battery_status . '</td>';
		$rowData = $rowData . '<td>' . $data[$i]->emergency_status . '</td>';

		$rowData = $rowData . "</tr>";
		$latlng = new \stdClass();
		$latlng->lat = (double)$data[$i]->latitude;
		$latlng->lng = (double)$data[$i]->longitude;

		array_push($coordinates, $latlng);
		}

		if (count($data) == 0) {
			$resultData["status"] = "N";
			$resultData["data"] = "History not found for given duration";
		} else {
			$resultData["status"] = "Y";
			// $res[] =$serialTableArray;
			$resultData["latlng"] = $coordinates;
			$resultData["data"] = $rowData;
		}

		return $resultData;
	}
		
	public function update_profile_records($data, $where)
	{
		$this->db->where($where);
		return $this->db->update($this->db->table_users, $data);	
	}

	public function isSerialUsed($serial_id)
	{
		$this->db->select('s_used');
		$this->db->from($this->db->table_serial_no);
		$this->db->where('s_serial_id', $serial_id); 
		$result = $this->db->get();
		$serial_no_array = $result->row_array();
		return $serial_no_array['s_used'];
	}

	public function isCustomerIdExist($customer_id)
	{
		$this->db->select('c_customer_id');
		$this->db->from($this->db->table_customers);
		$this->db->where('c_customer_id', $customer_id); 
		$result = $this->db->get();
		$customer_array = $result->row_array();
		return $customer_array['c_customer_id'];
	}

	/**
	 * Save vehicle details
	 * 
	*/
	public function create_new_vehicle_records($user, $params)
	{
		$user_type = $user['user_type'];
		$user_id   = $user['user_id'];
    //   echo "<pre>";print_r(($params['validity_to']));exit;
		/*$this->db->select('s_serial_id');
		$this->db->from($this->db->table_serial_no);
		$this->db->where('s_serial_number', $params['veh_serial_no']); 
		$result = $this->db->get();
		$serial_no_array = $result->row_array();
		$params['veh_serial_no'] = $serial_no_array['s_serial_id'];*/

		if(!$params['veh_owner_id'] && $params['veh_owner_phone']){
			$this->db->select('c_customer_id');
			$this->db->from($this->db->table_customers);
			$this->db->where('c_phone', $params['veh_owner_phone']); 
			$result = $this->db->get();
			$customer_array = $result->row_array();

			if (empty($customer_array)) {
				$insert=array(
					'c_customer_name' => isset($params['veh_owner_name'])?$params['veh_owner_name']:"",
					'c_address' => isset($params['veh_address'])?$params['veh_address']:"",
					'c_phone' => isset($params['veh_owner_phone'])?$params['veh_owner_phone']:"",
					'c_user_status'=>1, "c_created_by" => $user_id
				);

				if(isset($params['veh_owner_email']) && strlen($params['veh_owner_email'])>0)
				{
					$insert['c_email']=isset($params['veh_owner_email'])?$params['veh_owner_email']:"";
				}
				
				$this->db->insert($this->db->table_customers,$insert);

				$params['veh_owner_id'] = $this->db->insert_id();
			} else {
				$params['veh_owner_id'] = $customer_array['c_customer_id'];
			}
		}

		$this->db->select('user_id, invoice_prefix, invoice_sequence');
		$this->db->from($this->db->table_users);
		$this->db->where('user_id', $user['user_id']); 
		$result = $this->db->get();
		$user = $result->row();
		$user->invoice_sequence = $user->invoice_sequence + 1;

		$veh_invoice_no =(isset($params['veh_invoice_no'])?strtoupper($params['veh_invoice_no']):"")  . $user->invoice_sequence;

		$insertRecords=array();
		$insertRecords['veh_create_date']=isset($params['veh_create_date'])?$params['veh_create_date']:"";
		$insertRecords['veh_rc_no']=isset($params['veh_rc_no'])?$params['veh_rc_no']:"";
		$insertRecords['veh_chassis_no']=isset($params['veh_chassis_no'])?$params['veh_chassis_no']:"";
		$insertRecords['veh_engine_no']=isset($params['veh_engine_no'])?$params['veh_engine_no']:"";
		$insertRecords['veh_make_no']=isset($params['veh_make_no'])?$params['veh_make_no']:"";
		$insertRecords['veh_model_no']=isset($params['veh_model_no'])?$params['veh_model_no']:"";
		$insertRecords['veh_owner_id']=isset($params['veh_owner_id'])?$params['veh_owner_id']:"";
		$insertRecords['veh_owner_name']=isset($params['veh_owner_name'])?$params['veh_owner_name']:"";
		$insertRecords['veh_address']=isset($params['veh_address'])?$params['veh_address']:"";
		$insertRecords['veh_owner_phone']=isset($params['veh_owner_phone'])?$params['veh_owner_phone']:"";
		$insertRecords['veh_serial_no']=isset($params['veh_serial_no'])?$params['veh_serial_no']:"";
		$insertRecords['veh_rto_no']=isset($params['veh_rto_no'])?$params['veh_rto_no']:"";
		$insertRecords['veh_speed']=isset($params['veh_speed'])?$params['veh_speed']:"";
		$insertRecords['veh_tac']=isset($params['veh_tac'])?$params['veh_tac']:"";
		$insertRecords['veh_cat']=isset($params['veh_cat'])?$params['veh_cat']:"";

		$insertRecords['veh_company_id']=isset($params['veh_company_id'])?$params['veh_company_id']:"";
		$insertRecords['veh_cop_validity']=isset($params['veh_cop_validity'])?$params['veh_cop_validity']:"";
		$insertRecords['veh_sld_make']=isset($params['veh_sld_make'])?$params['veh_sld_make']:"";
		$insertRecords['validity_from']=isset($params['validity_from'])?$params['validity_from']:"";
		$insertRecords['validity_to']=isset($params['validity_to'])?$params['validity_to']:"";
		$insertRecords['selling_price']=isset($params['selling_price'])?$params['selling_price']:"";

		$insertRecords['veh_invoice_no']=$veh_invoice_no;
		$insertRecords['veh_speed_governer_photo']=isset($params['veh_speed_governer_photo'])?$params['veh_speed_governer_photo']:"";
		$insertRecords['veh_photo']=isset($params['veh_photo'])?$params['veh_photo']:"";
		$insertRecords['vehicle_owner_id_proof']=isset($params['vehicle_owner_id_proof_photo'])?$params['vehicle_owner_id_proof_photo']:"";
		$insertRecords['vehicle_owner_photo']=isset($params['vehicle_owners_photo'])?$params['vehicle_owners_photo']:"";
		$insertRecords['rc_book_photo']=isset($params['rc_book_photo'])?$params['rc_book_photo']:"";
		$insertRecords['veh_created_user_id']=isset($params['veh_created_user_id'])?$params['veh_created_user_id']:"";
		$insertRecords['veh_status']=1;
		$insertRecords['veh_channel']=1;
		$insertRecords['veh_state_id']=56;
		$insertRecords['veh_technician_id']=$params['technician_id'];
		$insertRecords['veh_panic_button']=isset($params['panic_button'])?$params['panic_button']:"";
		$insertRecords['veh_validity_validation']=isset($params['validity_validation'])?$params['validity_validation']:"";

		$this->db->insert($this->db->table_vehicle,$insertRecords);
		$insert_id=$this->db->insert_id();
		
		// Used Flag on Serial number
		$updateRecords=array();
		$updateRecords['s_used']=1;
		$updateRecords['customer_id']=$params['veh_owner_id'];
		$updateRecords['assign_to_customer_on']=date('Y-m-d H:i:s');

		if (isset($params['is_fitment_skipped']) && $params['is_fitment_skipped'] == 1) {
			$updateRecords['fitment'] = 1;
		}

		$this->db->where('s_serial_id', $insertRecords['veh_serial_no']);
		$this->db->update($this->db->table_serial_no,$updateRecords);

		if (isset($params['is_fitment_skipped']) && $params['is_fitment_skipped'] == 1) {
			$fitmentRecords['fitment_vehicle_id'] = $insert_id;
			$this->db->select('s_imei');
			$this->db->where('s_serial_id', $insertRecords['veh_serial_no']);
			$this->db->from($this->db->table_serial_no);
			$imei_id = $this->db->get();
			$imei_id = $imei_id->row_array();
			$fitmentRecords['fitment_imei']      = isset($imei_id['s_imei']) ? $imei_id['s_imei'] : '';
			$fitmentRecords['fitment_latitude']  = 0;
			$fitmentRecords['fitment_longitude'] = 0;
			$fitmentRecords['fitment_photo']     = "";
			$fitmentRecords['fitment_comments']  = "";
			$fitmentRecords['fitment_userid']    = 0;
			$fitmentRecords['fitment_createdOn'] = date('Y-m-d H:i:s');

			$this->db->insert($this->db->table_device_fitment, $fitmentRecords);
		}

		//Update User_Sttaus
		$updateRecords=array();
		$updateRecords['c_user_status']=1;
		$this->db->where('c_customer_id',$params['veh_owner_id']);
		$this->db->update($this->db->table_customers,$updateRecords);

		$this->db->select('s_product_id');
		$this->db->from($this->db->table_serial_no);
		$this->db->where('s_serial_id', $insertRecords['veh_serial_no']);
		$result = $this->db->get();
		$serial = $result->row();

		$this->db->set('invoice_sequence', $user->invoice_sequence, FALSE);
		$this->db->where('user_id', $user->user_id); 
		$this->db->update($this->db->table_users);

		$c_customer_id = $params['veh_owner_id'];
		$i_product_id = $serial->s_product_id;
		$veh_serial_no = isset($params['veh_serial_no'])?$params['veh_serial_no']:"";
		$veh_create_date = isset($params['veh_create_date'])?$params['veh_create_date']:"";
		$datains = array("invoice_number" => ($veh_invoice_no), "i_user_type" => $user_type, "i_user_id" => $user_id,"i_to_customer_id" => $c_customer_id, "i_product_id" => $i_product_id, "i_serial_ids" => $veh_serial_no, "i_created_by" => $veh_create_date);

		$this->db->insert($this->db->table_invoices_customer, $datains);

		$info=array();
		$info['insert_id']=$insert_id;
		$info['veh_owner_id']=$params['veh_owner_id'];
		return $info;
	}

	/**
	 * Save vehicle details
	 * 
	*/
	public function update_vehicle_records($user, $params)
	{
		$updateRecords=array();
		$updateRecords['veh_create_date']=isset($params['veh_create_date'])?$params['veh_create_date']:"";
		$updateRecords['veh_rc_no']=isset($params['veh_rc_no'])?$params['veh_rc_no']:"";
		$updateRecords['veh_chassis_no']=isset($params['veh_chassis_no'])?$params['veh_chassis_no']:"";
		$updateRecords['veh_engine_no']=isset($params['veh_engine_no'])?$params['veh_engine_no']:"";
		$updateRecords['veh_make_no']=isset($params['veh_make_no'])?$params['veh_make_no']:"";
		$updateRecords['veh_model_no']=isset($params['veh_model_no'])?$params['veh_model_no']:"";
		$updateRecords['veh_owner_id']=isset($params['veh_owner_id'])?$params['veh_owner_id']:"";
		$updateRecords['veh_owner_name']=isset($params['veh_owner_name'])?$params['veh_owner_name']:"";
		$updateRecords['veh_address']=isset($params['veh_address'])?$params['veh_address']:"";
		$updateRecords['veh_owner_phone']=isset($params['veh_owner_phone'])?$params['veh_owner_phone']:"";
		$updateRecords['veh_serial_no']=isset($params['veh_serial_no'])?$params['veh_serial_no']:"";
		$updateRecords['veh_rto_no']=isset($params['veh_rto_no'])?$params['veh_rto_no']:"";
		$updateRecords['veh_speed']=isset($params['veh_speed'])?$params['veh_speed']:"";
		$updateRecords['veh_tac']=isset($params['veh_tac'])?$params['veh_tac']:"";
		$updateRecords['veh_cat']=isset($params['veh_cat'])?$params['veh_cat']:"";
		$updateRecords['veh_company_id']=isset($params['veh_company_id'])?$params['veh_company_id']:"";
		$updateRecords['veh_cop_validity']=isset($params['veh_cop_validity'])?$params['veh_cop_validity']:"";
		$updateRecords['veh_sld_make']=isset($params['veh_sld_make'])?$params['veh_sld_make']:"";
		$updateRecords['validity_from']=isset($params['validity_from'])?$params['validity_from']:"";
		$updateRecords['validity_to']=isset($params['validity_to'])?$params['validity_to']:"";
		$updateRecords['selling_price']=isset($params['selling_price'])?$params['selling_price']:"";
		$updateRecords['veh_speed_governer_photo']=isset($params['veh_speed_governer_photo'])?$params['veh_speed_governer_photo']:"";
		$updateRecords['veh_photo']=isset($params['veh_photo'])?$params['veh_photo']:"";
		$updateRecords['vehicle_owner_id_proof']=isset($params['vehicle_owner_id_proof_photo'])?$params['vehicle_owner_id_proof_photo']:"";
		$updateRecords['vehicle_owner_photo']=isset($params['vehicle_owners_photo'])?$params['vehicle_owners_photo']:"";
		$updateRecords['rc_book_photo']=isset($params['rc_book_photo'])?$params['rc_book_photo']:"";
		$updateRecords['veh_created_user_id']=isset($params['veh_created_user_id'])?$params['veh_created_user_id']:"";
		$updateRecords['veh_status']=1;
		$updateRecords['veh_channel']=1;
		$updateRecords['veh_state_id']=56;
// 		$updateRecords['veh_panic_button']=isset($params['panic_button'])?$params['panic_button']:"";
// 		$updateRecords['veh_validity_validation']=isset($params['validity_validation'])?$params['validity_validation']:"";
		

		$this->db->where('veh_id', $params['veh_id']);
		$this->db->update($this->db->table_users, $data);

		/*$vehicleRegnumber=$insertRecords['veh_rc_no'];
		$data=array('vehicleRegnumber'=>$vehicleRegnumber);

		$otherdb = $this->load->database('tracking', TRUE); 
		$otherdb->select('*');
		$otherdb->where('vtrackingId', $veh_id);
		$otherdb->update($otherdb->table_tracking,$data);*/

		return 1;
	}

	/**
	 * Save vehicle details
	 * 
	*/
	public function mbl_update_vehicle_records($user, $params)
	{
		$updateRecords=array();
		if (isset($params['veh_rc_no']) && $params['veh_rc_no'] != '') {
			$updateRecords['veh_rc_no']=$params['veh_rc_no'];
		}
		
		if (isset($params['veh_photo']) && $params['veh_photo'] != '') {
			$updateRecords['veh_photo']=$params['veh_photo'];
		}

		if (isset($params['rc_book_photo']) && $params['rc_book_photo'] != '') {
			$updateRecords['rc_book_photo']=$params['rc_book_photo'];
		}

		$this->db->where('veh_id', $params['veh_id']);
		$this->db->update($this->db->table_vehicle, $updateRecords);

		return 1;
	}
		
	public function add_tracking_entry($user, $params,$ownerID=0)
	{
		try{
			$this->db->select('ser.s_imei,ser.s_dealer_id,ser.s_distributor_id,veh.veh_rc_no');
			$this->db->where('veh.veh_id', $params);
			$this->db->from($this->db->table_serial_no.' as ser');
			$this->db->join($this->db->table_vehicle.' as veh', 'veh.veh_serial_no = ser.s_serial_id','left');	
			$result = $this->db->get();
			$result = $result->row_array();

			if(isset($result['s_imei']))
			{
				$sql = "INSERT INTO gps_livetracking_data(customerID,imei,vehicleRegnumber,createdUser,vehicleId,dealer_id,distributor_id) VALUES ('".$ownerID."','".$result['s_imei']."','".$result['veh_rc_no']."','".$user['user_id']."','".$params."','".$result['s_dealer_id']."','".$result['s_distributor_id']."'); ";
				$tracking = $this->load->database('tracking', TRUE); 
				$tracking->query($sql);
				//$tracking->insert($this->db->table_tracking,array('imei'=>$result['s_imei']));
			}
		}
		catch(Exception $e) {

		}
		return true;
	}
		
	public function find_customer_by_phone($phone)
	{
		$this->db->select('*');
		$this->db->where('c_phone', $phone);
		$this->db->where('c_user_status', 1);
		$this->db->from($this->db->table_customers);
		$result = $this->db->get();
		$result = $result->row_array();
		// echo $this->db->last_query();exit();
		// print_r($result); exit;
		return $result;
	}
}