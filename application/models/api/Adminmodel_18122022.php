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
		$this->db->where('user_phone',$params['phone_number']);
		$this->db->where('user_password',md5($params['password_value']));
		$this->db->where('user_status',1);
		if(isset($params['user_type']) && (string)$params['user_type']==='dealer')
		{
			$this->db->where('user_type',1);
		}
		if(isset($params['user_type']) && (string)$params['user_type']==='distributor')
		{
		$this->db->where('user_type',2);
		}
		if(isset($params['user_type']) && (string)$params['user_type']==='subadmin')
		{
		$this->db->where('user_type',4);
		}
		$this->db->from($this->db->table_users);
		$result = $this->db->get();
		$result = $result->row_array();
		//echo $this->db->last_query();exit();
		return $result;
	}
}