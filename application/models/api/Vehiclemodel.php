<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Vehiclemodel extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('api/commonmodel', 'commonmodel');
	}

	/**
	 * Save vehicle details
	 * 
	*/
	public function create_new_vehicle_records($params)
	{
		$user_type = $this->session->userdata('user_type');
		$user_id=$this->session->userdata('user_id');
		if(!$params['veh_owner_id'] && $params['veh_owner_phone']){
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
		}

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

		$insertRecords['veh_invoice_no']=isset($params['veh_invoice_no'])?$params['veh_invoice_no']:"";
		$insertRecords['veh_speed_governer_photo']=isset($params['veh_speed_governer_photo'])?$params['veh_speed_governer_photo']:"";
		$insertRecords['veh_photo']=isset($params['veh_photo'])?$params['veh_photo']:"";
		$insertRecords['vehicle_owner_id_proof']=isset($params['vehicle_owner_id_proof_photo'])?$params['vehicle_owner_id_proof_photo']:"";
		$insertRecords['vehicle_owner_photo']=isset($params['vehicle_owners_photo'])?$params['vehicle_owners_photo']:"";
		$insertRecords['rc_book_photo']=isset($params['rc_book_photo'])?$params['rc_book_photo']:"";
		$insertRecords['veh_created_user_id']=isset($params['veh_created_user_id'])?$params['veh_created_user_id']:"";
		$insertRecords['veh_status']=1;
		
		$this->db->insert($this->db->table_vehicle,$insertRecords);
		$insert_id=$this->db->insert_id();
		
		// Used Flag on Serial number
		$updateRecords=array();
		$updateRecords['s_used']=1;
		$this->db->where('s_serial_id', $insertRecords['veh_serial_no']);
		$this->db->update($this->db->table_serial_no,$updateRecords);

		//Update User_Sttaus
		$updateRecords=array();
		$updateRecords['c_user_status']=1;
		$this->db->where('c_customer_id',$params['veh_owner_id']);
		$this->db->update($this->db->table_customers,$updateRecords);

		$this->db->select('user_id, invoice_prefix, invoice_sequence');
		$this->db->from($this->db->table_users);
		$this->db->where('user_id', $this->session->userdata('user_id')); 
		$result = $this->db->get();
		$user = $result->row();

		$user->invoice_sequence = $user->invoice_sequence + 1;

		$this->db->set('invoice_sequence', $user->invoice_sequence, FALSE);
		$this->db->where('user_id', $user->user_id); 
		$this->db->update($this->db->table_users);

		$veh_invoice_no =isset($params['veh_invoice_no'])?$params['veh_invoice_no']:"";
		$c_customer_id = $params['veh_owner_id'];
		$i_product_id = isset($params['veh_serial_no'])?$params['veh_serial_no']:"";
		$veh_serial_no = isset($params['veh_serial_no'])?$params['veh_serial_no']:"";
		$veh_create_date = isset($params['veh_create_date'])?$params['veh_create_date']:"";
		$datains = array("invoice_number" => $veh_invoice_no . $user->invoice_sequence, "i_user_type" => $user_type, "i_user_id" => $user_id,"i_to_customer_id" => $c_customer_id, "i_product_id" => $i_product_id, "i_serial_ids" => $veh_serial_no, "i_created_by" => $veh_create_date);

		$this->db->insert($this->db->table_invoices_customer, $datains);

		$info=array();
		$info['insert_id']=$insert_id;
		$info['veh_owner_id']=$params['veh_owner_id'];
		return $info;
	}

	/**
	 * Vehicle list
	 * 
	*/
	public function vehicle_list($user, $search_param = '', , $veh_id = 0)
	{
		$this->db->select('*');

		if ((string)$user['user_type'] != '0') {
			$this->db->where('veh_company_id', $user['user_company_id']);
			$this->db->where('veh_created_user_id', $user['user_id']);
		}

		if ($veh_id > 0)
		{
			$this->db->where('veh_id', $veh_id);
		}

		if ($search_param != '')
		{
			$this->db->or_where('veh_rc_no', $search_param);
			$this->db->or_where('veh_chassis_no', $search_param);
			$this->db->or_where('veh_engine_no', $search_param);
			$this->db->or_where('veh_make_no', $search_param);
			$this->db->or_where('veh_model_no', $search_param);
			$this->db->or_where('veh_owner_name', $search_param);
			$this->db->or_where('veh_owner_phone', $search_param);
			$this->db->or_where('veh_serial_no', $search_param);
		}

		$this->db->from($this->db->table_vehicle);

		return $this->db->result_array();
	}
}