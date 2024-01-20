<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commonmodel extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function send_sms($phone, $otpMsg)
    {
        // echo "sms service"; exit;
        log_message('error', $phone);
        log_message('error', $otpMsg);
        $msgContent = "http://api.msg91.com/api/sendhttp.php?route=4&sender=PSDNIN&mobiles=" . $phone . "&authkey=273355AY7MXrX08qN5cbc5435&encrypt=&message=" . urlencode($otpMsg) . "&unicode=1&country=91";
        log_message('error', $msgContent);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $msgContent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }

    public function getNoOfCount($user)
    {
        $user_type = $user['user_type'];
        $user_company_id = $user['user_company_id'];
        $response = array();
        $user_id = $user['user_id'];

        // RTO
        $this->db->select('*');
        //$this->db->where('user_type',3);
        $this->db->from($this->db->table_rto);
        $result = $this->db->count_all_results();
        $response['rto'] = $result;

        // Vehicle
        $this->db->select('*');

        if ((string)$user_type != '0') {
            $this->db->where('veh_company_id', $user_company_id);
            $this->db->where('veh_created_user_id', $user_id);
        }
        $this->db->from($this->db->table_vehicle);
        $result = $this->db->count_all_results();
        $response['vehicle'] = $result;

        // Devices
        $this->db->select('*');
        if ((string)$user_type != '0') {
            $this->db->where('s_dealer_id', $user_id);
        }
        $this->db->from($this->db->table_serial_no);
        $result = $this->db->get()->result_array();
        // echo $this->db->last_query();exit();
        $response['deviceTotal'] = count($result);
        $count = array_count_values(array_column($result, 's_used'));
        //  echo "<pre>";print_r($count);exit();
        $response['deviceUsed'] = isset($count[1]) ? $count[1] : 0;
        $response['deviceNew'] = isset($count[0]) ? $count[0] : 0;

        $this->db->select('*');
        $from = date('Y-m-d') . ' 00:00:00';
        $to = date('Y-m-d') . ' 23:59:59';
        $this->db->where('veh_create_date >=', $from);
        $this->db->where('veh_create_date <=', $to);
        if ((string)$user_type != '0') {
            $this->db->where('veh_company_id', $user_company_id);
            $this->db->where('veh_created_user_id', $user_id);
        }
        $this->db->from($this->db->table_vehicle);
        $result = $this->db->count_all_results();

        $response['today'] = $result;
        $this->db->select('*');
        $this->db->from($this->db->table_customers);
        $this->db->where('c_created_by',$user_id);
        $result = $this->db->count_all_results();
        // echo $this->db->last_query();exit();
        $response['customer'] = $result;

        $trackingInfo = $this->trackingInfo($user_id);
        // echo "<pre>";print_r($trackingInfo);exit();
        $response['tagged_all'] = $trackingInfo['tagged_all'];
        $response['tagged_working'] = $trackingInfo['tagged_working'];
        $response['tagged_offline'] = $trackingInfo['tagged_offline'];
        
        $faultyCount = $this->commonmodel->countFaulty($user_id);
// 		$data['totalNoOfcliVehicles'] = strval($faultyCount);
        $response['tagged_client_issue'] = strval($faultyCount);
 
        return $response;
    }

    // public function trackingInfo_old($user_id)
    // {
    //     $sql = "SELECT 
    //         COUNT(vtrackingId) as tagged_all, 
    //         COUNT(CASE WHEN dealer_id = ".$user_id." and ignition = 1 AND DATE(lastupdatedTime) >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)  THEN vtrackingId END) as tagged_working, 
    //         GROUP_CONCAT(CASE dealer_id = ".$user_id." and  WHEN ignition = 1 AND DATE(lastupdatedTime) >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)  THEN vehicleId END) as tagged_working_ids, 
    //         COUNT(CASE WHEN dealer_id = ".$user_id." and  ignition = 0 AND DATE(lastupdatedTime) >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)  THEN vtrackingId END) as tagged_offline, 
    //         GROUP_CONCAT(CASE dealer_id = ".$user_id." and  WHEN ignition = 0 AND DATE(lastupdatedTime) >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)  THEN vehicleId END) as tagged_offline_ids, 
    //         COUNT(CASE WHEN dealer_id = ".$user_id." and  DATE(lastupdatedTime) < DATE_SUB(CURDATE(), INTERVAL 10 DAY) THEN vtrackingId END) as tagged_client_issue, 
    //         GROUP_CONCAT(CASE WHEN  dealer_id = ".$user_id." and DATE(lastupdatedTime) < DATE_SUB(CURDATE(), INTERVAL 10 DAY) THEN vehicleId END) as tagged_client_issue_ids 
    //     from gps_livetracking_data where createdUser = " . $user_id;
    //     echo "<pre>";print_r($sql);exit;
    //     $tracking = $this->load->database('tracking', TRUE);
        
    //     $resultSet = $tracking->query($sql);
    //     $trackingInfo = $resultSet->row_array();
    //     // echo "<pre>";print_r($trackingInfo);exit;
    //     return $trackingInfo;
    // }
    
    public function trackingInfo($user_id)
    {
        $sql = "SELECT 
            COUNT(vtrackingId) as tagged_all, 
            COUNT(CASE WHEN dealer_id = ".$user_id." and lastupdatedTime > DATE_SUB(NOW(), INTERVAL 5 MINUTE)  THEN vtrackingId END) as tagged_working, 
            GROUP_CONCAT(CASE WHEN dealer_id = ".$user_id." and lastupdatedTime > DATE_SUB(NOW(), INTERVAL 5 MINUTE)  THEN vehicleId END) as tagged_working_ids, 
            COUNT(CASE WHEN dealer_id = ".$user_id." and lastupdatedTime < DATE_SUB(NOW(), INTERVAL 5 MINUTE)  THEN vtrackingId END) as tagged_offline, 
            GROUP_CONCAT(CASE WHEN dealer_id = ".$user_id." and lastupdatedTime < DATE_SUB(NOW(), INTERVAL 5 MINUTE)  THEN vehicleId END) as tagged_offline_ids, 
            COUNT(CASE WHEN DATE(lastupdatedTime) < DATE_SUB(CURDATE(), INTERVAL 10 DAY) THEN vtrackingId END) as tagged_client_issue, 
            GROUP_CONCAT(CASE WHEN DATE(lastupdatedTime) < DATE_SUB(CURDATE(), INTERVAL 10 DAY) THEN vehicleId END) as tagged_client_issue_ids 
            from gps_livetracking_data where createdUser = " . $user_id;
        $tracking = $this->load->database('tracking', TRUE);
        // echo "<pre>";print_r($sql);exit;
        $resultSet = $tracking->query($sql);
        $trackingInfo = $resultSet->row_array();
        //  echo "<pre>";print_r($trackingInfo);exit;
        return $trackingInfo;
    }

    public function countFaulty($dealer_id){
        $this->db->select('count(*)');
        $this->db->from($this->db->table_serial_no);
        $this->db->where('s_dealer_id', $dealer_id);
        $this->db->where('s_status', 1);
        $result = $this->db->count_all_results();
        return $result;
    }
    public function trackingInfoNew($user_id)
    {
        $sql = "SELECT COUNT(vtrackingId) as tagged_all, COUNT(CASE WHEN ignition = 1 THEN vtrackingId END) as tagged_working, COUNT(CASE WHEN ignition = 0 THEN vtrackingId END) as tagged_offline, COUNT(CASE WHEN DATE(lastupdatedTime) < DATE_SUB(CURDATE(), INTERVAL 10 DAY) THEN vtrackingId END) as tagged_client_issue from gps_livetracking_data where createdUser = " . $user_id;
        // echo "<pre>";print_r($sql);exit;
        $tracking = $this->load->database('tracking', TRUE);
        $resultSet = $tracking->query($sql);
        $trackingInfo = $resultSet->row_array();

        return $trackingInfo;
    }

    public function qrcode($data)
    {
        $this->load->library('ciqrcode');
        header("Content-Type: image/png");
        $params['data'] = $data;
        return $this->ciqrcode->generate($params);
    }

    public function saveQrCode($qrparams)
    {
        $this->load->library('ciqrcode');
        $params['data'] = $qrparams;
        $params['level'] = 'H';
        $params['size'] = 10;
        $params['savename'] = FCPATH . 'public/qrcode/' . time() . '.png';
        $this->ciqrcode->generate($params);
        return $params['savename'];
    }


    public function allRtoNumbers($params)
    {
        $this->db->select('*');
        if (isset($params['search']) && $params['search'] != '')
        {
            $search = $params['search'];
            $this->db->group_start();
            $this->db->or_like('rto_number', $search, 'both');
            $this->db->or_like('rto_place', $search, 'both');
            $this->db->group_end();
        }

        if (isset($params['state_id']) && $params['state_id'] != '')
        {
            $this->db->where('state_id', $params['state_id']);
        }
        $this->db->order_by("rto_place", "asc");
        $this->db->from($this->db->table_rto);
        $result = $this->db->get();
        $result = $result->result_array();
        // echo $this->db->last_query();exit();
        return $result;
    }

    public function allStatesList($params)
    {
        // echo "<pre>";print_r(($params['search']) );exit;
        $this->db->select('id as state_id, s_name as state_name, s_key as state_code');
        if (isset($params['state_id']) && $params['state_id'] != '')
        {
            $this->db->where('id', $params['state_id']);
        }

        if (isset($params['search']) && $params['search'] != '')
        {
            $search = $params['search'];
            $this->db->group_start();
            $this->db->or_like('s_name', $search, 'both');
            $this->db->or_like('s_key', $search, 'both');
            $this->db->group_end();
        }
        $this->db->order_by("state_name", "asc");
        $this->db->from($this->db->table_state);
        $result = $this->db->get();
        $result = $result->result_array();
        return $result;
    }
    
    public function alltechnicianList($params)
    {
        // echo "<pre>";print_r(($params['search']) );exit;
        $this->db->select('user_id as technician_id, user_name as technician_name');
        if (isset($params['technician_id']) && $params['technician_id'] != '')
        {
            $this->db->where('user_id', $params['technician_id']);
        }

        if (isset($params['search']) && $params['search'] != '')
        {
            $search = $params['search'];
            $this->db->group_start();
            $this->db->like('user_name', $search, 'both');
            $this->db->group_end();
        }
       
        if($params['user_type'] == 1){    
            if (isset($params['user_id']) && $params['user_id'] != '')
            {
                $this->db->where('created_by', $params['user_id']);
            }
        }
       
        
        $this->db->where('user_type', 6);
        $this->db->where('user_status', 1);
        $this->db->order_by("user_id", "asc");
        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->result_array();
        // echo $this->db->last_query();exit();
        return $result;
        
    }

    public function removeWhiteSpace()
    {
        $this->db->select('veh_id,veh_rc_no');
        $this->db->from($this->db->table_vehicle);
        $result = $this->db->get();
        $result = $result->result_array();
        foreach ($result as $key => $value) {
            $val = preg_replace('/\s+/', '', $value['veh_rc_no']);
            $insertRecords = array();
            $insertRecords['veh_rc_no'] = $val;
            $this->db->where('veh_id', $value['veh_id']);
            $this->db->update($this->db->table_vehicle, $insertRecords);
        }
    }


    public function fetch($whereFrom, $whereTo, $fields, $table, $single = true)
    {
        $this->db->select($fields);
        $this->db->where($whereFrom, $whereTo);
        $this->db->from($table);
        $result = $this->db->get();
        if ($single) {
            $result = $result->row_array();
        } else {
            $result = $result->result_array();
        }

        return $result;
    }


    public function allMakeList($params)
    {
        $this->db->select('v_make_id, v_make_name');
        if (isset($params['search']) && $params['search'] != '')
        {
            $search = $params['search'];
            $this->db->or_like('v_make_name', $search, 'both');
        }
        $this->db->order_by("v_make_name", "asc");
        $this->db->from($this->db->table_make);
        $result = $this->db->get();
        $result = $result->result_array();
        // echo $this->db->last_query();exit();
        return $result;
    }


    public function allModelList($params)
    {
        $this->db->select('ve_model_id,ve_model_name');
        $makeId = (isset($params['make_id']) ? $params['make_id'] : 0);
        if ((string)$makeId != '0') {
            $this->db->where('ve_make_id ', $makeId);
        }
        if (isset($params['search']) && $params['search'] != '')
        {
            $search = $params['search'];
            $this->db->like('ve_model_name', $search, 'both');
        }
        $this->db->order_by("ve_model_name", "asc");
        $this->db->from($this->db->table_model);
        $result = $this->db->get();
        $result = $result->result_array();
        // echo $this->db->last_query();exit();
        return $result;
    }

    public function allSerialListCount($dealer_id, $params = "") 
    {
        $this->db->select('count(s_serial_id) as total_count, count(case s_used when 1 then 1 else null end) as used_count, count(case s_used when 0 then 1 else null end) as new_count');
        $this->db->where('s_dealer_id', $dealer_id);
        $this->db->where('inScan', 0);

        if (isset($params['customer_id']) && $params['customer_id'] > 0) {
            $this->db->where('customer_id', $params['customer_id']);
        }

        if (isset($params['search']) && $params['search'] != '')
        {
            $search = $params['search'];
            $this->db->group_start();
            $this->db->or_like('s_serial_number', $search, 'both');
            $this->db->or_like('s_imei', $search, 'both');
            $this->db->or_like('s_iccid', $search, 'both');
            // $this->db->or_like('s_mobile', $search, 'both');
            // $this->db->or_like('s_mobile_2', $search, 'both');
            $this->db->group_end();
        }

        $this->db->from($this->db->table_serial_no);
        $this->db->join($this->db->table_company, 'c_company_id = s_company_id', 'left');
        
        $result = $this->db->get();
        $result = $result->result_array();
        // echo $this->db->last_query();exit();
        return $result;
    }

    public function allSerialList($dealer_id, $params)
    {
        $this->db->select('*');
        $this->db->where('s_dealer_id', $dealer_id);
        $this->db->where('inScan', 0);

        if (isset($params['is_used']) && in_array($params['is_used'], [0, 1]))
        {
            $this->db->where('s_used', $params['is_used']);
        } else {
            //$this->db->where('s_used', 0);
        }

        if (isset($params['customer_id']) && $params['customer_id'] != '') {
            $this->db->where('customer_id', $params['customer_id']);
        }

        if (isset($params['search']) && $params['search'] != '')
        {
            $search = $params['search'];
            $this->db->group_start();
            $this->db->or_like('s_serial_number', $search, 'both');
            $this->db->or_like('s_imei', $search, 'both');
            $this->db->or_like('s_iccid', $search, 'both');
            // $this->db->or_like('s_mobile', $search, 'both');
            // $this->db->or_like('s_mobile_2', $search, 'both');
            $this->db->group_end();
        }

        $this->db->from($this->db->table_serial_no);
        $this->db->join($this->db->table_company, 'c_company_id = s_company_id', 'left');
        $this->db->join($this->db->table_vehicle, 'veh_serial_no = s_serial_id', 'left');
        $this->db->order_by("s_serial_id", "desc");
        $limit = ($params['limit'] > 0 ? $params['limit'] : 50);
        $offset = ($params['offset'] > 0 ? $params['offset'] : 0);
        $this->db->limit($limit, $offset);

        $result = $this->db->get();
        $result = $result->result_array();
        // echo $params;exit;

        $response = [];
        if (!empty($result)) {
            foreach($result as $k => $v) {
                $v['veh_id'] = $v['veh_id'] ? $v['veh_id'] : '';
                $response[] = $v;
            }
        }
        // echo $this->db->last_query();exit();
        return $response;
    }

    public function fetchSavedHistory()
    {
        $this->db->select('*');
        $this->db->from($this->db->table_imei_history);
        $result = $this->db->get();
        $result = $result->result_array();

        return $result;
    }

    public function fetch_list_of_users($params, $needAdmin, $avoid = 0)
    {
        $this->db->select('user_id,CONCAT(user_name, " - ", user_phone) AS user_name');
        $this->db->where('user_status', 1);
        if (isset($avoid) && (int)$avoid > 0) {
            $this->db->where('user_id  !=', $avoid);
        }
        if (isset($params['user_company_id']) && strlen($params['user_company_id']) > 0) {
            $this->db->where('user_company_id ', $params['user_company_id']);
        }
        if (isset($params['user_type']) && (int)$params['user_type'] > 0) {
            $this->db->where('user_type ', $params['user_type']);
        }

        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');
        if ($user_type == 2) {
            $this->db->where('user_id', $user_id);
        } else if ((string)$needAdmin === '1') {
            $this->db->or_where('user_id ', 1);
        }

        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function fetch_list_of_dealers($params, $needAdmin, $avoid = 0)
    {
        $this->db->select('user_id,CONCAT(user_name, " - ", user_phone) AS user_name');
        $this->db->where('user_status', 1);
        if (isset($avoid) && (int)$avoid > 0) {
            $this->db->where('user_id  !=', $avoid);
        }
        if (isset($params['user_company_id']) && strlen($params['user_company_id']) > 0) {
            $this->db->where('user_company_id ', $params['user_company_id']);
        }
        if (isset($params['user_type']) && (int)$params['user_type'] > 0) {
            $this->db->where('user_type ', $params['user_type']);
        }

        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');
        if ($user_type == 2) {
            $this->db->where('created_by', $user_id);
        } else if ((string)$needAdmin === '1') {
            $this->db->or_where('user_id ', 1);
        }

        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function fetch_list_of_products($params)
    {
        $this->db->select('p_product_id,p_product_name');

        if (isset($params['p_company_id']) && strlen($params['p_company_id']) > 0) {
            $this->db->where('p_company_id ', $params['p_company_id']);
        }
        $this->db->from($this->db->table_products);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function fetch_list_of_unassigned_serial_numbers($params)
    {
        $this->db->select('s_serial_id,s_serial_number,s_imei,s_mobile,admin_price,distributor_price,dealer_price,p_unit_price');

        if (isset($params['s_company_id']) && strlen($params['s_company_id']) > 0) {
            $this->db->where('s_company_id ', $params['s_company_id']);
        }
        if (isset($params['s_product_id']) && strlen($params['s_product_id']) > 0) {
            $this->db->where('s_product_id ', $params['s_product_id']);
        }
        //$this->db->where('s_user_type', 0);
        //$this->db->or_where('s_user_type', 2);
        $this->db->from($this->db->table_serial_no);
        $this->db->join($this->db->table_products, 'p_product_id = s_product_id', 'left');
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function fetch_list_of_selected_serial_numbers($params)
    {
        $this->db->select('s_serial_id,s_serial_number,s_imei,s_mobile,admin_price,distributor_price,dealer_price');

        // if(isset($params['s_company_id']) && strlen($params['s_company_id'])>0)
        // {
        // 	$this->db->where('s_company_id ',$params['s_company_id']);
        // }
        // if(isset($params['s_product_id']) && strlen($params['s_product_id'])>0)
        // {
        // 	$this->db->where('s_product_id ',$params['s_product_id']);
        // }
        //var_dump($params['serial_ids']);exit;
        $this->db->where_in('s_serial_id', $params['serial_ids']);
        //$this->db->or_where('s_user_type', 2);
        $this->db->from($this->db->table_serial_no);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

//		public function fetch_imei_numbers($params)
//		{
//			$this->db->select('*');
//			$this->db->where('s_imei', $params);
//			$this->db->or_where('s_serial_number', $params);
//			$this->db->from($this->db->table_serial_no);
//            $result1 = $this->db->get();
//			$result1 = $result1->result_array();
//			//echo"<pre>";print_r($result1);exit();
//
//
//			if(count($result1) < 1) {
//				$result["status"] = "N";
//				$result["data"] = "Cannot find this device-IMEI in the System";
//			} else {
//				// $params = $result0[0]['s_imei'];
//				// $this->db->select('*');
//				// $this->db->where('veh_serial_no',$result0[0]["s_serial_id"]);
//
//				// $this->db->from($this->db->table_vehicle);
//				// $result1 = $this->db->get();
//				// $result1 = $result1->result_array();
//				// $res  =array();
//				// print_r($result1);exit();
//				// if(count($result1) < 1) {
//				// 	$result["status"] = "N";
//				// 	$result["data"] = "The Certificate is not issued for this IMEI";
//				// }
//				// else
//				// {
//
//
//					// $this->db->select('*');
//					// $this->db->from($this->db->table_serial_no);
//					// //$this->db->where('s_used','=', 1);
//					// $this->db->where('s_imei',$params);
//					// $this->db->or_where('s_serial_number', $params);
//		   			// $result1 = $this->db->get();
//					// $result1 = $result1->result_array();
//
//					//echo "<pre>";print_r($result1);exit();
//
//					//if($result1[0]['s_used'] == 1)
//
//
//					if($result1[0]['s_distributor_id'] != 0)
//					{
//
//						$this->db->select('user_name as distributerName');
//						$this->db->from($this->db->table_users);
//						$this->db->where('user_id', $result1[0]['s_distributor_id']);
//			            $distributerName = $this->db->get();
//						$distributerName = $distributerName->result_array();
//						//print_r($distributerName);exit();
//					}
//					if($result1[0]['s_distributor_id'] != 0 and $result1[0]['s_dealer_id'] != 0)
//					{
//
//
//						$this->db->select('user_name as dealerName');
//						$this->db->from($this->db->table_users);
//						$this->db->where('user_id', $result1[0]['s_dealer_id']);
//			            $dealerName = $this->db->get();
//						$dealerName = $dealerName->result_array();
//						//print_r($dealerName);exit();
//					}
//
//						/*$this->db->select('user_name as customerName');
//						$this->db->from($this->db->table_users);
//						$this->db->where('user_id', $result1[0]['customer_id']);
//			            $customerName = $this->db->get();
//						$customerName = $customerName->result_array();
//						print_r($customerName);exit();*/
//
//						$otherdb = $this->load->database('tracking', TRUE);
//						$otherdb->select('*');
//						$otherdb->where('imei',$params);
//						$otherdb->from($otherdb->table_tracking);
//						$result2 = $otherdb->get();
//						$result2 = $result2->result_array();
//					   // echo "<pre>";print_r($result2);exit();
//
//					if($result1[0]['s_distributor_id'] != 0 and $result1[0]['s_dealer_id'] != 0 and  $result2[0]['customerID'] != 0)
//					{
//					    $this->db->select('c_customer_name as customerName');
//						$this->db->from($this->db->table_customers);
//						$this->db->where('c_customer_id', $result2[0]['customerID']);
//			            $customerName2 = $this->db->get();
//						$customerName2 = $customerName2->result_array();
//						//print_r($customerName2);exit();
//					}
//
//						$result1 = !empty($result1[0])?$result1[0]:array();
//						$result2 = !empty($result2[0])?$result2[0]:array();
//						$distributerName = !empty($distributerName[0])?$distributerName[0]:array();
//						$dealerName = !empty($dealerName[0])?$dealerName[0]:array();
//						//$customerName = !empty($customerName[0])?$customerName[0]:array();
//						$customerName2 = !empty($customerName2[0])?$customerName2[0]:array();
//
//						$result3=array_merge($result2,$result1);
//						$result4=array_merge($distributerName,$dealerName);
//						$result5=array_merge($result4,$customerName2);
//						$result6=array_merge($result3,$result5);
//						//print_r($result2);exit();
//						if(count($result2) < 1 && count($result1) < 1) {
//							$result["status"] = "N";
//							$result["data"] = "The Device not connected to server";
//						} else {
//
//							$result["status"] = "Y";
//							$res[] =$result6;
//
//							$result["data"] = $res;
//							//print_r($res);exit();
//						}
//
//
//
//					/*$otherdb = $this->load->database('tracking', TRUE);
//					$otherdb->select('*');
//					$otherdb->where('imei',$params);
//					$otherdb->from($otherdb->table_tracking);
//					$result2 = $otherdb->get();
//					$result2 = $result2->result_array();
//					echo "<pre>";print_r($result2);exit();
//					if(count($result2) < 1) {
//						$result["status"] = "N";
//						$result["data"] = "The Device not connected to server";
//					} else {
//						$result["status"] = "Y";
//						$result["data"] = $result2;
//					}
//					*/
//
//				// }
//
//			}
//			//echo $this->db->last_query();exit();
//			return $result;
//		}



    public function saveHisData($params,$start_date,$start_time,$end_time)
    {
//        $DB2 = $this->load->database('postgre_db', TRUE);
//
//        $from = $start_date.' '.$start_time;
//        $to = $start_date.' '.$end_time;
//       $query  = 'INSERT INTO `ci_saved_history`(`id`, `imei`, `date`, `start_time`, `end_time`, `created_at`) VALUES (1,\'32423423\',\'2010-09-23\',\'00:00\',\'23:00\',\'2010-09-23 00:00\')';




        $cust_datains = array("imei" => $params, "date" => $start_date, "start_time" => $start_time, "end_time" => $end_time);
        $data  = $this->db->insert("ci_saved_history", $cust_datains);



        if (count($data) == 0) {
            $resultData["status"] = "N";
            $resultData["data"] = "Can't save history";
        } else {

            $resultData["status"] = "Y";
            // $res[] =$serialTableArray;

            $resultData["data"] = "Inserted successfully";
//
//                exit();
        }

        return $resultData;
    }


    public function fetch_imei_data($params,$start_date,$start_time,$end_time)
    {
        $DB2 = $this->load->database('postgre_db', TRUE);

        $from = $start_date.' '.$start_time;
        $to = $start_date.' '.$end_time;
        $query = "select * from public.tbl_health_data where imei = '" . $params . "' AND server_reached between '" . $from . "' AND '" . $to . "'  ORDER BY id DESC LIMIT 5";
//       echo $query;exit;
        $data = $DB2->query($query)->result();
//

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
        }

        if (count($data) == 0) {
            $resultData["status"] = "N";
            $resultData["data"] = "Health data not found on server";
        } else {

            $resultData["status"] = "Y";
            // $res[] = $serialTableArray;

            $resultData["data"] = $rowData;
//
//                exit();
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
//
//                exit();
        }

        return $resultData;
    }


    public function fetch_imei_numbers($params)
    {
        $this->db->select('*');
        $this->db->where('s_imei', $params);
        $this->db->or_where('s_serial_number', $params);
        $this->db->from($this->db->table_serial_no);
        $result1 = $this->db->get();
        $result1 = $result1->result_array();


//			echo"<pre>";print_r($result1);exit();


        if (count($result1) < 1) {
            $result["status"] = "N";
            $result["data"] = "Cannot find this History in the System";
        } else {
            $serialTableArray = $result1[0];
            $serialTableArray["stockBy"] = "-";
            $serialTableArray["distributerName"] = "-";
            $serialTableArray["dealerName"] = "-";
            $this->db->select('user_name as stockBy');
            $this->db->from($this->db->table_users);
            $this->db->where('user_id', $result1[0]['s_created_by']);
            $stockBy = $this->db->get();
            $stockBy = $stockBy->result_array();
            $serialTableArray["stockBy"] = $stockBy[0]["stockBy"];
            $serialTableArray['certificateLink'] = "-";
//            print_r($stockBy);exit();
            if ($result1[0]['s_distributor_id'] != 0) {

                $this->db->select('user_name as distributerName');
                $this->db->from($this->db->table_users);
                $this->db->where('user_id', $result1[0]['s_distributor_id']);
                $distributerName = $this->db->get();
                $distributerName = $distributerName->result_array();
                $serialTableArray["distributerName"] = $distributerName[0]["distributerName"];
            }
            if ($result1[0]['s_distributor_id'] != 0 and $result1[0]['s_dealer_id'] != 0) {


                $this->db->select('user_name as dealerName');
                $this->db->from($this->db->table_users);
                $this->db->where('user_id', $result1[0]['s_dealer_id']);
                $dealerName = $this->db->get();
                $dealerName = $dealerName->result_array();
                $serialTableArray["dealerName"] = $dealerName[0]["dealerName"];
                //print_r($dealerName);exit();
            }

            $otherdb = $this->load->database('tracking', TRUE);
            $otherdb->select('*');
            $otherdb->where('imei', $params);
            $otherdb->from($otherdb->table_trackings);
            $result2 = $otherdb->get();
            $result2 = $result2->result_array();
            if($result2)
            $serialTableArray = array_merge($result2[0], $serialTableArray);
          //   echo "<pre>";print_r( $serialTableArray['vehicleRegnumber']);exit();

            if ($result1[0]['s_distributor_id'] != 0 and $result1[0]['s_dealer_id'] != 0 and $result2[0]['customerID'] != 0) {
                $this->db->select('*');
                $this->db->from($this->db->table_vehicle);
                $this->db->where('veh_rc_no', $serialTableArray['vehicleRegnumber']);
                $customerName2 = $this->db->get();
                $customerName2 = $customerName2->result_array();
                if(count($customerName2)>1){
                    $result["status"] = "N";
                    $result["data"] = "Vehicle Number Duplicate with vehicle ".$customerName2[0]['veh_id'].','.$customerName2[1]['veh_id'];
              return $result;
                }
              //  echo "<pre>";print_r(  $customerName2->result_array());exit();



                $this->db->select('c_email');
                $this->db->from($this->db->table_customers);
                $this->db->where('c_customer_id', $result2[0]['customerID']);
                $customerName = $this->db->get();
                $customerEmail = $customerName->result_array();
                $serialTableArray["customerEmail"] = $customerEmail[0]["c_email"];

                $pdfEncode = base64_encode(base64_encode(base64_encode($customerName2[0]['veh_id'])));
                $href = base_url() . "admin/downloadwebpdf?id=" . $pdfEncode;
                $serialTableArray['certificateLink'] = $href;
                $serialTableArray = array_merge($customerName2[0], $serialTableArray);

            }


            //print_r($result2);exit();
            if (count($result2) < 1 && count($result1) < 1) {
                $result["status"] = "N";
                $result["data"] = "The Device not connected to server";
            } else {

                $result["status"] = "Y";
                // $res[] =$serialTableArray;

                $result["data"] = $serialTableArray;
//                print_r(json_encode($serialTableArray));
//                exit();
            }


        }
        return $result;
    }


    public function fetch_serial_numbers($params)
    {
        $this->db->select('s_serial_id,s_serial_number,s_imei,s_mobile,admin_price,distributor_price,dealer_price,s_used');
        $this->db->where('s_serial_number', $params);
        //$this->db->or_where('s_user_type', 2);
        $this->db->from($this->db->table_serial_no);
        $result0 = $this->db->get();
        $result0 = $result0->result_array();
        //echo"<pre>";print_r($result0);exit();

        if (count($result0) < 1) {
            $result["status"] = "N";
            $result["data"] = "Cannot find this device-IMEI in the System";
        } else {
            $this->db->select('*');
            $this->db->where('veh_serial_no', $result0[0]["s_serial_id"]);

            $this->db->from($this->db->table_vehicle);
            $result1 = $this->db->get();
            $result1 = $result1->result_array();
            $res = array();
            //print_r($result1);exit();
            if (count($result1) < 1) {
                $result["status"] = "N";
                $result["data"] = "The Certificate is not issued for this IMEI";
            } else {
                $this->db->select('*');
                $this->db->from($this->db->table_serial_no);
                //$this->db->where('s_used','=', 1);
                $this->db->where('s_serial_number', $params);
                $result1 = $this->db->get();
                $result1 = $result1->result_array();

                //echo "<pre>";print_r($result1[0]['s_used']);exit();

                if ($result1[0]['s_used'] == 1) {
                    $this->db->select('user_name as distributerName');
                    $this->db->from($this->db->table_users);
                    $this->db->where('user_id', $result1[0]['s_distributor_id']);
                    $distributerName = $this->db->get();
                    $distributerName = $distributerName->result_array();

                    $this->db->select('user_name as dealerName');
                    $this->db->from($this->db->table_users);
                    $this->db->where('user_id', $result1[0]['s_dealer_id']);
                    $dealerName = $this->db->get();
                    $dealerName = $dealerName->result_array();

                    $this->db->select('user_name as customerName');
                    $this->db->from($this->db->table_users);
                    $this->db->where('user_id', $result1[0]['customer_id']);
                    $customerName = $this->db->get();
                    $customerName = $customerName->result_array();

                    $otherdb = $this->load->database('tracking', TRUE);
                    $otherdb->select('*');
                    $otherdb->where('imei', $params);
                    $otherdb->from($otherdb->table_tracking);
                    $result2 = $otherdb->get();
                    $result2 = $result2->result_array();
                    //echo "<pre>";print_r($result2);exit();
                    $result1 = !empty($result1[0]) ? $result1[0] : array();
                    $result2 = !empty($result2[0]) ? $result2[0] : array();
                    $distributerName = !empty($distributerName[0]) ? $distributerName[0] : array();
                    $dealerName = !empty($dealerName[0]) ? $dealerName[0] : array();
                    $customerName = !empty($customerName[0]) ? $customerName[0] : array();

                    $result3 = array_merge($result2, $result1);
                    $result4 = array_merge($distributerName, $dealerName);
                    $result5 = array_merge($result4, $customerName);
                    $result6 = array_merge($result3, $result5);

                    if (count($result2) < 1) {
                        $result["status"] = "N";
                        $result["data"] = "The Device not connected to server";
                    } else {

                        $result["status"] = "Y";
                        $res[] = $result6;

                        $result["data"] = $res;
                        //print_r($res);exit();
                    }

                } else {
                    print_r("www");
                    exit();
                }

                /*$otherdb = $this->load->database('tracking', TRUE);
					$otherdb->select('*');
					$otherdb->where('imei',$params);
					$otherdb->from($otherdb->table_tracking);
					$result2 = $otherdb->get();
					$result2 = $result2->result_array();
					echo "<pre>";print_r($result2);exit();
					if(count($result2) < 1) {
						$result["status"] = "N";
						$result["data"] = "The Device not connected to server";
					} else {
						$result["status"] = "Y";
						$result["data"] = $result2;
					}
					*/

            }

        }
        //echo $this->db->last_query();exit();
        return $result;
    }


    public function selectCompanyInfo($companyID = 0, $userID = 0)
    {
        $this->db->select('c_tac_no');
        $this->db->from($this->db->table_company);
        $this->db->where('c_company_id', $companyID);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function getCustomerInfo($phone)
    {
        if ($phone) {
            $this->db->select('c_customer_id,c_email,c_customer_name,c_address');
            $this->db->from($this->db->table_customers);
            $this->db->like('c_phone', $phone, 'both');
            $result = $this->db->get();
            $result = $result->row();
        }
        //echo $this->db->last_query();exit();
        return $result;
    }


    public function allSerialNumberByCompany($companyID = 0, $userID = 0)
    {
        $this->db->select('ser.s_serial_id,ser.s_serial_number,com.c_tac_no');
        $this->db->from($this->db->table_serial_no . ' as ser');
        $this->db->join($this->db->table_company . ' as com', 'ser.s_company_id = com.c_company_id');
        if ((string)$companyID != '0') {
            $this->db->where('ser.s_company_id', $companyID);
        }
        if ((string)$userID != '0' && (string)$userID != '1') {
            $this->db->where('ser.s_user_id', $userID);
        }
        $this->db->where('ser.s_used', 0);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }


    public function companyProductList($companyID = 0)
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('p_product_id,p_product_name');

        if ((string)$user_type != '0') {
            $this->db->where('p_company_id', $user_company_id);
        }
        $this->db->from($this->db->table_products);

        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function allCompanyList($userID = 0)
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('com.c_company_name,com.c_company_id,com.c_tac_no');
        if ((string)$user_type != '0') {
            $this->db->where('c_company_id', $user_company_id);
        }
        $this->db->from($this->db->table_company . ' as com');
        if ((string)$userID != '0' && (string)$userID != '1') {
            $this->db->join($this->db->table_users . ' as usr', 'usr.user_company_id = com.c_company_id');
            $this->db->where('usr.user_id', $userID);
        }
        $this->db->where('com.c_status', 1);

        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function allCustomerList($userID = 0)
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');

        $this->db->select('c_customer_name,c_customer_id,c_phone');

        $this->db->from($this->db->table_customers);
        // if((string)$userID!='0' && (string)$userID !='1')
        // {
        // 	$this->db->join($this->db->table_users.' as usr', 'usr.user_company_id = com.c_company_id');
        // 	$this->db->where('usr.user_id', $userID);
        // }
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function listofTodayEntrys($selectedReportDate, $rtoNo)
    {
        $this->db->select('veh.*,ser.s_serial_number');

        $this->db->where('veh_create_date', $selectedReportDate);
        $this->db->where('veh_rto_no', $rtoNo);
        $this->db->order_by("veh_id", "asc");

        $this->db->from($this->db->table_vehicle . ' veh');
        $this->db->join($this->db->table_serial_no . ' as ser', 'ser.s_serial_id = veh.veh_serial_no', 'left');

        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function totalNoOflistofTodayEntrys($selectedReportDate, $rtoNo)
    {
        $this->db->select('*');
        $this->db->where('veh_create_date', $selectedReportDate);
        $this->db->where('veh_rto_no', $rtoNo);
        $this->db->order_by("veh_id", "asc");
        $this->db->from($this->db->table_vehicle);
        $result = $this->db->count_all_results();
        return $result;
    }

    public function listofVehicleMakes($limit, $offset, $search = '')
    {
        $this->db->select('v_make_id,v_make_name');

        if (strlen($search) > 0) {
            $this->db->or_like('v_make_name', $search, 'both');
        }
        $this->db->from($this->db->table_make);
        $this->db->limit($limit, $offset);
        $this->db->order_by("v_make_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        return $result;
    }

    //-------- Add on starts ---------
    public function listofcertificates($limit, $offset, $search = '')
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        //---------------
        $this->db->select('ser.allotted,ser.used,us.user_name,us.user_type');
        if ((string)$user_type != '0') {
            $this->db->where('us.user_company_id', $user_company_id);
        }
        if (strlen($search) > 0) {
            $this->db->or_like('us.user_name', $search, 'both');
        }

        //if(strlen($company_id)>0 && (int)$company_id>0)
        //{
        //$this->db->or_like('ser.s_company_id',$company_id, 'both');
        //}
        //if(isset($_GET['used_status']) && strlen($_GET['used_status'])>0)
        //{
        //$this->db->where('s_used',$_GET['used_status']);
        //}

        $this->db->from($this->db->table_certificate . ' as ser');
        //$this->db->join($this->db->table_users.' as com', 'com.user_id = ser.created_by', 'left');
        $this->db->join($this->db->table_users . ' as us', 'us.user_id = ser.created_to', 'left');
        $this->db->limit($limit, $offset);
        $this->db->order_by("ser.sl", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        return $result;


        // ---------------
        /*
			$this->db->select('c_company_name,c_tac_no,c_cop_validity,c_company_id');

			if(strlen($search)>0)
			{
				$this->db->or_like('c_company_name',$search, 'both');
				$this->db->or_where("FIND_IN_SET('$search',c_tac_no) !=", 0);
			}

			$this->db->from($this->db->table_company);
			$this->db->limit($limit, $offset);
			$this->db->order_by("c_company_id", "desc");
            $result = $this->db->get();
			$result = $result->result_array();
			return $result;
*/
    }

    //-------- Addon Ends --------

    public function listofcompanys($limit, $offset, $search = '')
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('c_company_name,c_tac_no,c_cop_validity,c_company_id');
        if ((string)$user_type != '0') {
            $this->db->where('c_company_id', $user_company_id);
        }
        if (strlen($search) > 0) {
            $this->db->or_like('c_company_name', $search, 'both');
            $this->db->or_where("FIND_IN_SET('$search',c_tac_no) !=", 0);
        }

        $this->db->from($this->db->table_company);
        $this->db->limit($limit, $offset);
        $this->db->order_by("c_company_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        return $result;
    }

    public function listofProducts($limit, $offset, $search = '')
    {

        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('p_product_id,p_product_name,p_product_description,p_tac_no,p_unit_price,c_company_name');
        if ((string)$user_type != '0') {
            $this->db->where('c_company_id', $user_company_id);
        }
        if (strlen($search) > 0) {
            $this->db->like('p_product_name', $search, 'both');
        }

        if (isset($_GET['company_id']) && (int)$_GET['company_id'] > 0) {
            $this->db->where('p_company_id', $_GET['company_id']);
        }

        $this->db->from($this->db->table_products);
        $this->db->join($this->db->table_company, 'p_company_id = c_company_id', 'left');
        $this->db->limit($limit, $offset);
        $this->db->order_by("p_product_id", "desc");
        $result = $this->db->get();
        // echo $this->db->last_query();exit();
        $result = $result->result_array();
        return $result;
    }

    public function totalNoOfProducts()
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('*');
        if ((string)$user_type != '0') {
            $this->db->where('p_company_id', $user_company_id);
        }
        if (strlen($search) > 0) {
            $this->db->like('p_product_name', $search, 'both');
        }

        if (isset($_GET['company_id']) && (int)$_GET['company_id'] > 0) {
            $this->db->where('p_company_id', $_GET['company_id']);
        }

        $this->db->from($this->db->table_products);

        $result = $this->db->count_all_results();
        return $result;
    }

    public function listofdealers($limit, $offset, $search = '')
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('user_id,user_name,user_phone,user_type,user_status');
        if ((string)$user_type != '0') {
            $this->db->where('user_company_id', $user_company_id);
            $this->db->where('user_type !=', $user_type);
        }

        if (strlen($search) > 0) {
            $this->db->or_like('user_name', $search, 'both');
            $this->db->or_like('user_phone', $search, 'both');
        }

        if (isset($_GET['user_type']) && (int)$_GET['user_type'] > 0) {
            $this->db->where('user_type', $_GET['user_type']);
        }

        $this->db->where('user_type != ', 0);

        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');

        if ($user_type != 0) {
            $this->db->where('created_by', $user_id);
        }

        $this->db->from($this->db->table_users);
        $this->db->limit($limit, $offset);
        $this->db->order_by("user_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        //print_r($this->db->last_query());
        return $result;
    }

    public function totalNoOfDealers()
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('*');
        if ((string)$user_type != '0') {
            $this->db->where('user_company_id', $user_company_id);
        }
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('user_name', $_GET['search'], 'both');
            $this->db->or_like('user_email', $_GET['search'], 'both');
        }
        if (isset($_GET['user_type']) && (int)$_GET['user_type'] > 0) {
            $this->db->where('user_type', $_GET['user_type']);
        }
        $this->db->where('user_type != ', 0);

        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');
        if ($user_type == 2) {
            $this->db->where('user_distributor_id', $user_id);
        }
        $this->db->from($this->db->table_users);

        $result = $this->db->count_all_results();
        return $result;
    }

    public function listofUnassignedSerialNumbers($limit, $offset, $search = '', $company_id = '')
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('ser.s_serial_id,ser.s_serial_number,ser.s_imei,ser.s_mobile,com.c_company_name,di.user_name as distributor_name,de.user_name as dealer_name,ser.s_distributor_id,ser.s_dealer_id,ser.s_used');
        if ((string)$user_type != '0') {
            $this->db->where('com.c_company_id', $user_company_id);
        }
        $user_type = $this->session->userdata('user_type');

        if ($user_type == '0') {
            $this->db->where('ser.s_distributor_id', '0');
        } else if ($user_type == '2') {
            $this->db->where('ser.s_dealer_id', '0');
        }

        $this->db->where('ser.s_used', 0);
        $this->db->where('ser.s_distributor_id', 0);

        if (strlen($search) > 0) {
            $this->db->like('ser.s_serial_number', $search, 'both');
        }
        if (isset($_GET['company_id']) && strlen($_GET['company_id']) > 0) {
            $this->db->where('ser.s_company_id', $_GET['company_id'], 'both');
        }
        if (isset($_GET['s_product_id']) && strlen($_GET['s_product_id']) > 0) {
            $this->db->where('ser.s_product_id', $_GET['s_product_id']);
        }

        //$this->db->where('s_user_id', '0');
        $this->db->from($this->db->table_serial_no . ' as ser');
        $this->db->join($this->db->table_company . ' as com', 'com.c_company_id = ser.s_company_id', 'left');
        $this->db->join($this->db->table_users . ' as di', 'di.user_id = ser.s_distributor_id', 'left');
        $this->db->join($this->db->table_users . ' as de', 'de.user_id = ser.s_dealer_id', 'left');
        $this->db->limit($limit, $offset);
        $this->db->order_by("ser.s_serial_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        //print_r($this->db->last_query());exit();
        return $result;
    }

    public function listofSerialNumbers($limit, $offset, $search = '', $company_id = '')
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('ser.s_serial_id,ser.s_serial_number,ser.s_imei,ser.s_mobile,com.c_company_name,,di.user_name as distributor_name,de.user_name as dealer_name,ser.s_distributor_id,ser.s_dealer_id,ser.s_used');
        if ((string)$user_type != '0') {
            $this->db->where('ser.s_company_id', $user_company_id);
        }
        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');

        if ($user_type == '0') {
            $this->db->where('s_distributor_id >', '0');
        } else if ($user_type == '2') {
            $this->db->where('s_distributor_id', $user_id);
        } else if ($user_type == '1') {
            $this->db->where('s_dealer_id', $user_id);
        }

        $this->db->where('ser.s_used', 0);
        $this->db->where('ser.s_distributor_id>', 0);
        $this->db->where('ser.s_dealer_id', 0);

        if (strlen($search) > 0) {
            $this->db->like('ser.s_serial_number', $search, 'both');
        }
        if (isset($_GET['company_id']) && strlen($_GET['company_id']) > 0) {
            $this->db->where('ser.s_company_id', $_GET['company_id'], 'both');
        }
        if (isset($_GET['s_product_id']) && strlen($_GET['s_product_id']) > 0) {
            $this->db->where('ser.s_product_id', $_GET['s_product_id']);
        }
        if (isset($_GET['used_status']) && strlen($_GET['used_status']) > 0) {
            $this->db->where('ser.s_used', $_GET['used_status']);
        }
        //$this->db->where('s_user_id >', 0);
        $this->db->from($this->db->table_serial_no . ' as ser');
        $this->db->join($this->db->table_company . ' as com', 'com.c_company_id = ser.s_company_id', 'left');
        $this->db->join($this->db->table_users . ' as di', 'di.user_id = ser.s_distributor_id', 'left');
        $this->db->join($this->db->table_users . ' as de', 'de.user_id = ser.s_dealer_id', 'left');
        $this->db->limit($limit, $offset);
        $this->db->order_by("ser.s_serial_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        //print_r($this->db->last_query());exit();
        return $result;
    }

    public function listofAssignedSerialNumbers($limit, $offset, $search = '', $company_id = '')
    {
        $user_type = $this->session->userdata('user_type');
        //print_r($user_type);exit();
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('ser.s_serial_id,ser.s_serial_number,ser.s_imei,ser.s_mobile,com.c_company_name,,di.user_name as distributor_name,de.user_name as dealer_name,ser.s_distributor_id,ser.s_dealer_id,ser.s_used');
        if ((string)$user_type != '0') {
            $this->db->where('ser.s_company_id', $user_company_id);
        }
        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');

        if ($user_type == '0') {
            $this->db->where('ser.s_distributor_id >', '0');
        } else if ($user_type == '2') {
            $this->db->where('ser.s_distributor_id', $user_id);
        } else if ($user_type == '1') {
            $this->db->where('ser.s_dealer_id', $user_id);
        }

        //$this->db->where('ser.s_used', '0');
        //$this->db->where('ser.s_distributor_id>', 0);
        //$this->db->where('ser.s_dealer_id>', 0);

        if (strlen($search) > 0) {
            $this->db->like('ser.s_serial_number', $search, 'both');
        }
        if (isset($_GET['company_id']) && strlen($_GET['company_id']) > 0) {
            $this->db->where('ser.s_company_id', $_GET['company_id'], 'both');
        }
        if (isset($_GET['s_product_id']) && strlen($_GET['s_product_id']) > 0) {
            $this->db->where('ser.s_product_id', $_GET['s_product_id']);
        }
        //$this->db->where('s_user_id >', 0);
        $this->db->from($this->db->table_serial_no . ' as ser');
        $this->db->join($this->db->table_company . ' as com', 'com.c_company_id = ser.s_company_id', 'left');
        $this->db->join($this->db->table_users . ' as di', 'di.user_id = ser.s_distributor_id', 'left');
        $this->db->join($this->db->table_users . ' as de', 'de.user_id = ser.s_dealer_id', 'left');
        $this->db->limit($limit, $offset);
        $this->db->order_by("ser.s_serial_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        // print_r($result);exit();

        return $result;
    }

    public function listofVehicleModels($limit, $offset, $search = '')
    {
        $this->db->select('mo.ve_model_id,mo.ve_make_id,mo.ve_model_name,mk.v_make_name');
        if (isset($_GET['make_id']) && strlen($_GET['make_id']) > 0) {
            $this->db->where('ve_make_id', $_GET['make_id'], 'both');
        }
        if (strlen($search) > 0) {
            $this->db->or_like('ve_model_name', $search, 'both');
        }

        $this->db->from($this->db->table_model . ' as mo');
        $this->db->join($this->db->table_make . ' as mk', 'mk.v_make_id = mo.ve_make_id', 'left');
        //	$this->db->limit($limit, $offset);
        $this->db->order_by("ve_model_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function totalNoOfvehicleModel()
    {
        $this->db->select('*');
        if (isset($_GET['make_id']) && strlen($_GET['make_id']) > 0) {
            $this->db->where('ve_make_id', $_GET['make_id'], 'both');
        }
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('ve_model_name', $_GET['search'], 'both');
        }

        $this->db->from($this->db->table_model);
        $result = $this->db->count_all_results();
        return $result;
    }

    public function getModelInfo($modelID)
    {
        $this->db->select('*');
        $this->db->where('ve_model_id = ', $modelID);
        $this->db->from($this->db->table_model);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function getRtoInfo($RTONo)
    {
        $this->db->select('*');
        $this->db->where('rto_no = ', $RTONo);
        $this->db->from($this->db->table_rto);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function getSerialNumberInfo($serial_number)
    {
        $this->db->select('ser.*,di.user_name as distributor_name, de.user_name as dealer_name');
        $this->db->where('ser.s_serial_id = ', $serial_number);
        $this->db->from($this->db->table_serial_no . ' as ser');
        $this->db->join($this->db->table_users . ' as di', 'di.user_id = ser.s_distributor_id', 'left');
        $this->db->join($this->db->table_users . ' as de', 'de.user_id = ser.s_dealer_id', 'left');
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }


    public function totalNoOfvehicleMake()
    {
        $this->db->select('*');
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('v_make_name', $_GET['search'], 'both');
        }
        $this->db->from($this->db->table_make);
        $result = $this->db->count_all_results();
        return $result;
    }

    public function totalNoOfRTO()
    {
        $this->db->select('*');
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('rto_place', $_GET['search'], 'both');
            $this->db->or_like('rto_number', $_GET['search'], 'both');
        }
        $this->db->from($this->db->table_rto);
        $result = $this->db->count_all_results();
        return $result;
    }


    public function listofRtoList($limit, $offset, $search = '')
    {
        $this->db->select('*');
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('rto_place', $_GET['search'], 'both');
            $this->db->or_like('rto_number', $_GET['search'], 'both');
        }

        $this->db->from($this->db->table_rto);
        $this->db->limit($limit, $offset);
        $this->db->order_by("rto_no", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function totalNoOfCustomers()
    {
        $this->db->select('*');
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('c_customer_name', $_GET['search'], 'both');
            $this->db->or_like('c_phone', $_GET['search'], 'both');
        }
        $this->db->from($this->db->table_customers);
        $result = $this->db->count_all_results();
        //echo "<pre>"; print_r($result); exit;
        return $result;
    }

    public function totalNoOfCustomersDealer($dealer_id)
    {
        $this->db->select('*');
        $this->db->where('c_created_by', $dealer_id);
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('c_customer_name', $_GET['search'], 'both');
            $this->db->or_like('c_phone', $_GET['search'], 'both');
        }
        $this->db->from($this->db->table_customers);
        $result = $this->db->count_all_results();
        // echo "<pre>"; print_r($this->db->last_query()); exit;
        return $result;
    }
    public function totalNoOfCustomersDistributor($distributor_id)
    {
        $this->db->select('user_id');
        $this->db->where('created_by', $distributor_id);
        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->result_array();
        foreach($result as  $value)
        {
           $dealerids[]=$value[user_id];
        }
        foreach($dealerids as $key => $value)
        {
            $this->db->select('*');
            $this->db->where('c_created_by',$value );
            $this->db->from($this->db->table_customers);
            $result[$key] = $this->db->count_all_results();
            //echo $value;
        } //exit;

        //echo "<pre>"; print_r($result); exit;
        $result=array_sum($result);
          // echo $result; exit;
        return $result;
    }

    public function listofCustomersList($limit, $offset, $search = '')
    {
        $this->db->select('*');
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('c_customer_name', $_GET['search'], 'both');
            $this->db->or_like('c_phone', $_GET['search'], 'both');
        }

    //     $this->db->select('one.*,cus.c_phone,cus.c_status,cus.c_address,cus.c_email,cus.c_customer_name,three.user_name as distributor_name,four.user_name as dealer_name');
    //    // $this->db->or_where('one.user_type',); or_where
    //     $this->db->limit($limit, $offset);
    //     $this->db->where('cus.c_customer_name is NOT NULL', NULL, FALSE);
    //     $this->db->from($this->db->table_users . ' as one');
    //     $this->db->join($this->db->table_users . ' as two', 'one.user_id = two.created_by', 'left');
    //     $this->db->join($this->db->table_users . ' as three', 'two.user_id = three.created_by', 'left');
    //     $this->db->join($this->db->table_users . ' as four', 'three.user_id = four.created_by', 'left');
    //     $this->db->join($this->db->table_customers . ' as cus', 'one.user_id = cus.c_created_by', 'left');
    //     $result = $this->db->get();
    //     $result = $result->result_array();

    $this->db->select('use.*,cus.c_phone,cus.c_status,cus.c_address,cus.c_email,cus.c_customer_name,use.user_name as dealer_name,two.user_name as distributor_name');
    $this->db->from($this->db->table_customers . ' as cus');
    $this->db->limit($limit, $offset);
  // $this->db->from($this->db->table_users . ' as one');
   //
    $this->db->join($this->db->table_users . ' as use', 'use.user_id = cus.c_created_by', 'left');
    $this->db->join($this->db->table_users . ' as two', 'two.user_id = use.created_by', 'left');
    $result = $this->db->get();
    $result = $result->result_array();


      //echo "<pre>"; print_r($result); exit;

        // $this->db->from($this->db->table_customers);
        // $this->db->limit($limit, $offset);
        // $this->db->order_by("c_customer_name");
        // $result = $this->db->get();
        // $result = $result->result_array();
       // echo $this->db->last_query();exit();
        return $result;
    }

    public function listofCustomersListDealer($limit, $offset, $search = '', $dealer_id, $customer_id)
    {
        $this->db->select('*');
        if (isset($search) && strlen($search) > 0) {
            $this->db->or_like('c_customer_name', $search, 'both');
            $this->db->or_like('c_phone', $search, 'both');
        }
        if (isset($customer_id) && strlen($customer_id) > 0) {
            $this->db->where('c_customer_id', $customer_id);
        }
        $this->db->where('c_created_by', $dealer_id);
        $this->db->from($this->db->table_customers);
        $this->db->limit($limit, $offset);
        $this->db->order_by("c_customer_name");
        $result = $this->db->get();
        $result = $result->result_array();
        // echo $this->db->last_query();exit();

        $response = [];
        if (!empty($result)) {
            foreach($result as $k => $v) {
                unset($v["c_password"], $v["c_otp_reference"], $v["c_user_remember"], $v["c_remember"]);
                $v["c_customer_name"] = ($v["c_customer_name"] ? $v["c_customer_name"] : '');
                $v["c_address"] = ($v["c_address"] ? $v["c_address"] : '');
                $v["c_phone"] = ($v["c_phone"] ? $v["c_phone"] : '');
                $v["c_photo"] = ($v["c_photo"] ? $v["c_photo"] : '');
                $v["c_photo_old"] = ($v["c_photo"] ? $v["c_photo"] : '');
                $v["c_photo_new"] = str_replace("/customer/", "/vehicle_owners_photos/", $v["c_photo"]);
                $v["c_email"] = ($v["c_email"] ? $v["c_email"] : '');
                $v["c_device_id"] = ($v["c_device_id"] ? $v["c_device_id"] : '');
                $v["c_status"] = ($v["c_status"] ? $v["c_status"] : '');
                $v["c_user_status"] = ($v["c_user_status"] ? $v["c_user_status"] : '');
                
                $response[] = $v;
            }
        }

        // echo $this->db->last_query();exit();
        return $response;
    }

    public function getCustomerData($dealer_id, $customer_id)
    {
        $this->db->select('*');
        $this->db->where('c_customer_id', $customer_id);
        $this->db->where('c_created_by', $dealer_id);
        $this->db->from($this->db->table_customers);
        $result = $this->db->get();
        $result = $result->result_array();
        if (!empty($result)) {
            foreach($result as $k => $v) {
                unset($v["c_password"], $v["c_otp_reference"], $v["c_user_remember"], $v["c_remember"]);
                $v["c_customer_name"] = ($v["c_customer_name"] ? $v["c_customer_name"] : '');
                $v["c_address"] = ($v["c_address"] ? $v["c_address"] : '');
                $v["c_phone"] = ($v["c_phone"] ? $v["c_phone"] : '');
                $v["c_photo"] = ($v["c_photo"] ? $v["c_photo"] : '');
                $v["c_photo"] = str_replace("/customer/", "/vehicle_owners_photos/", $v["c_photo"]);
                $v["c_email"] = ($v["c_email"] ? $v["c_email"] : '');
                $v["c_device_id"] = ($v["c_device_id"] ? $v["c_device_id"] : '');
                $v["c_status"] = ($v["c_status"] ? $v["c_status"] : '');
                $v["c_user_status"] = ($v["c_user_status"] ? $v["c_user_status"] : '');
                
                $response[] = $v;
            }
        }

        return $response;
    }

    public function listofCustomersListDistributor($limit, $offset, $search = '',$distributor_id)
    {
        //echo "hai";exit;
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('c_customer_name', $_GET['search'], 'both');
            $this->db->or_like('c_phone', $_GET['search'], 'both');
        }
    //     $this->db->select('user_id,user_name');
    //     $this->db->where('created_by', $distributor_id);
    //     $this->db->from($this->db->table_users);
    //     $result = $this->db->get();
    //     $result = $result->result_array();
    //    // echo "oooeee"."<pre>"; print_r($result); exit;
    //     foreach($result as  $value)
    //     {
    //        $dealerids[]=$value[user_id];
    //        //$dealernames[]=$value[user_name];
    //     }
        $this->db->select('one.user_name,cus.c_phone,cus.c_status,cus.c_customer_id,cus.c_address,cus.c_email,cus.c_customer_name,two.user_name as dealer_name');
        $this->db->where('one.created_by', $distributor_id);
        $this->db->where('cus.c_customer_name is NOT NULL', NULL, FALSE);
        $this->db->from($this->db->table_users . ' as one');
        $this->db->limit($limit, $offset);
        $this->db->join($this->db->table_users . ' as two', 'one.user_id = two.created_by', 'left');
        $this->db->join($this->db->table_customers . ' as cus', 'one.user_id = cus.c_created_by', 'left');
        $result = $this->db->get();
        $result = $result->result_array();

       

        //echo "ooo"."<pre>"; print_r($result); exit;
        

       //echo "<pre>"; print_r($dealerids);
       //echo "<pre>"; print_r($dealernames); exit;
//         $result1=array();
//         $dealer=array();
//         $i=0;
//         foreach($dealerids as $key => $value)
//         {
//             $this->db->select('*');
//             $this->db->where('c_created_by',$value);
//             $this->db->from($this->db->table_customers);
//             $this->db->limit($limit, $offset);
//             $this->db->order_by("c_customer_name");
//             $result = $this->db->get();
//             $result = $result->result_array();
//             $result['dealer_name']=$dealernames[$key];
//             array_push($result1,$result);
//         } 

// echo "<pre>"; print_r($result1); exit;
//         foreach($result1 as $key => $value)
//         {
//            foreach($result1[$key] as $val)
//            {
//                $results[]=$val;
//            }
//         }
//         echo "<pre>"; print_r($results); exit;
        return $result;
    }

    public function distributor_vehicle_list($distributor_id)
    {
        //echo "hai";exit;
        $otherdb = $this->load->database('tracking', TRUE);
        $this->db->select('one.user_name,cus.c_phone,cus.c_status,cus.c_customer_id,cus.c_address,cus.c_email,cus.c_customer_name,two.user_name as dealer_name');
        $this->db->where('one.created_by', $distributor_id);
        $this->db->where('cus.c_customer_name is NOT NULL', NULL, FALSE);
        $this->db->from($this->db->table_users . ' as one');
        $this->db->join($this->db->table_users . ' as two', 'one.user_id = two.created_by', 'left');
        $this->db->join($this->db->table_customers . ' as cus', 'one.user_id = cus.c_created_by', 'left');
        $result = $this->db->get();
        $result = $result->result_array();
        // echo "<pre>";
        // print_r($result); exit;
        $customer_ids=array();
        $result2=array();
        $result1=array();
        for($i=0;$i<count($result);$i++)
        {
            $customer_ids[$i]=$result[$i]['c_customer_id'];
            $otherdb->select('latitude,longitude,vtrackingId,imei');
            $otherdb->from($otherdb->table_trackings);
            $otherdb->where('customerID',$customer_ids[$i]);
          //  echo "idTable==>".$customer_ids[$i]."<br>";
            $result1 = $otherdb->get();
            array_push($result2,$result1->result_array());
        }
        //exit;
        // echo "ohooii"."<pre>"; print_r($customer_ids); exit;
        return $result2;
    }

    public function dealer_vehicle_list($dealer_id)
    {
        $this->db->select('c_customer_id');
        $this->db->where('c_created_by', $dealer_id);
        $this->db->from($this->db->table_customers);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        $customer_ids=array();
        $otherdb = $this->load->database('tracking', TRUE);
        $result2=array();
        $result1=array();
        for($i=0;$i<count($result);$i++)
        {
            $customer_ids[$i]=$result[$i]['c_customer_id'];
            //echo $customer_ids[$i];
            $otherdb->select('latitude,longitude,vtrackingId,imei');
            $otherdb->from($otherdb->table_trackings);
            $otherdb->where('customerID',$customer_ids[$i]);
            $result1 = $otherdb->get();
            array_push($result2,$result1->result_array());
        }
        // echo "<pre>";
        // print_r($result2);
        // exit;
        return $result2;
    }
 
 

    public function totalNoOfCompanys()
    {
        $this->db->select('*');
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('c_company_name', $_GET['search'], 'both');
            $this->db->or_where("FIND_IN_SET('$search',c_tac_no) !=", 0);
        }
        $this->db->from($this->db->table_company);
        $result = $this->db->count_all_results();
        return $result;
    }

    public function totalNoOfUnassignedSerialNumbers()
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('*');
        if ((string)$user_type != '0') {
            $this->db->where('s_company_id	', $user_company_id);
        }
        $user_type = $this->session->userdata('user_type');

        if ($user_type == '0') {
            $this->db->where('s_distributor_id', '0');
        } else if ($user_type == '2') {
            $this->db->where('s_dealer_id', '0');
        }

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->like('s_serial_number', $_GET['search'], 'both');
        }

        if (isset($_GET['company_id']) && strlen($_GET['company_id']) > 0) {
            $this->db->where('s_company_id', $_GET['company_id'], 'both');
        }
        if (isset($_GET['s_product_id']) && strlen($_GET['s_product_id']) > 0) {
            $this->db->where('s_product_id', $_GET['s_product_id']);
        }

        //$this->db->where('s_user_id', 0);

        $this->db->from($this->db->table_serial_no);
        $result = $this->db->count_all_results();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function totalNoOfSerialNumbers()
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('*');
        if ((string)$user_type != '0') {
            $this->db->where('s_company_id', $user_company_id);
        }
        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');

        if ($user_type == '0') {
            $this->db->where('s_distributor_id >', '0');
        } else if ($user_type == '2') {
            $this->db->where('s_distributor_id', $user_id);
        } else if ($user_type == '1') {
            $this->db->where('s_dealer_id', $user_id);
        }

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->like('s_serial_number', $_GET['search'], 'both');
        }
        if (isset($_GET['company_id']) && strlen($_GET['company_id']) > 0) {
            $this->db->where('s_company_id', $_GET['company_id'], 'both');
        }
        if (isset($_GET['s_product_id']) && strlen($_GET['s_product_id']) > 0) {
            $this->db->where('s_product_id', $_GET['s_product_id']);
        }
        if (isset($_GET['used_status']) && strlen($_GET['used_status']) > 0) {
            $this->db->where('s_used', $_GET['used_status']);
        }
        //$this->db->where('s_user_id >', 0);
        $this->db->from($this->db->table_serial_no);
        $result = $this->db->count_all_results();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function totalNoOfAssignedSerialNumbers()
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');
        $this->db->select('*');
        if ((string)$user_type != '0') {
            $this->db->where('s_company_id', $user_company_id);
        }
        $user_type = $this->session->userdata('user_type');
        $user_id = $this->session->userdata('user_id');

        if ($user_type == '0') {
            $this->db->where('s_distributor_id >', '0');
        } else if ($user_type == '2') {
            $this->db->where('s_distributor_id', $user_id);
        } else if ($user_type == '1') {
            $this->db->where('s_dealer_id', $user_id);
        }

        $this->db->where('s_used', '0');

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->like('s_serial_number', $_GET['search'], 'both');
        }
        if (isset($_GET['company_id']) && strlen($_GET['company_id']) > 0) {
            $this->db->where('s_company_id', $_GET['company_id'], 'both');
        }
        if (isset($_GET['s_product_id']) && strlen($_GET['s_product_id']) > 0) {
            $this->db->where('s_product_id', $_GET['s_product_id']);
        }

        //$this->db->where('s_user_id >', 0);
        $this->db->from($this->db->table_serial_no);
        $result = $this->db->count_all_results();
        //echo $this->db->last_query();exit();
        return $result;
    }


    public function updateUserInfo($params)
    {
        $insertRecords = array();
        $insertRecords['user_password'] = isset($params['user_password']) ? $params['user_password'] : "";
        $this->db->where('user_id', $params['user_id']);
        $this->db->update($this->db->table_users, $insertRecords);
        return 1;
    }

    public function getUserInfo($params)
    {
        $this->db->select('*');
        $this->db->where('user_email = ', $params['email']);
        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function getUserInfobyid($id)
    {
        $this->db->select('*');
        $this->db->where('user_id = ', $id);
        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }


    public function verify_exits_rto_number($rto_number, $id)
    {
        $this->db->select('*');
        $this->db->where('rto_number', $rto_number);
        if (strlen($id) > 0) {
            $this->db->where('rto_no !=', $id);
        }
        $this->db->from($this->db->table_rto);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }


    // Verify Exits Company Name
    public function verify_exits_model_make_records($model_name, $make_id, $id)
    {
        $this->db->select('*');
        $this->db->where('ve_model_name', $model_name);
        $this->db->where('ve_make_id', $make_id);
        if (strlen($id) > 0) {
            $this->db->where('ve_model_id !=', $id);
        }
        $this->db->from($this->db->table_model);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }


    public function verify_exits_model_make($model_name, $make_id)
    {
        $this->db->select('*');
        $this->db->where('ve_model_name', $model_name);
        $this->db->where('ve_make_id', $make_id);
        $this->db->from($this->db->table_model);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }


    // Verify Exits Company Name
    public function verify_exits_company_name($c_company_name, $id = "")
    {
        $this->db->select('*');
        $this->db->where('c_company_name', $c_company_name);
        if (strlen($id) > 0) {
            $this->db->where('c_company_id !=', $id);
        }
        $this->db->from($this->db->table_company);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }

    public function verify_exits_product_name($p_product_name, $id = "")
    {
        $this->db->select('*');
        $this->db->where('p_product_name', $p_product_name);
        if (strlen($id) > 0) {
            $this->db->where('p_product_id !=', $id);
        }
        $this->db->from($this->db->table_products);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }

    // Verify Exits Make Name
    public function verify_exits_make_name($make_name, $id = "")
    {
        $this->db->select('*');
        $this->db->where('v_make_name', $make_name);
        if (strlen($id) > 0) {
            $this->db->where('v_make_id !=', $id);
        }
        $this->db->from($this->db->table_make);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }


    // Verify Exits Serial Number
    public function verify_exits_serial_number($serial_numbers, $id = "", $s_imei = '')
    {
        $this->db->select('s_serial_number,s_imei,s_mobile');
        if (is_string($serial_numbers) && strlen($serial_numbers) > 0) {
            $this->db->or_like('s_serial_number', $serial_numbers, 'none');
        }
        $params = array();
        $serial_numbers = explode(',', $serial_numbers);
        $params['serial_numbers'] = array_values(array_filter(array_unique($serial_numbers)));

        if (strlen($s_imei) > 0) {
            $this->db->or_like('s_imei', $s_imei, 'none');
        }

        foreach ($params['serial_numbers'] as $key => $value) {
            list($serial_number, $imei, $mobile) = explode('-', trim($value));
            //$this->db->where('s_serial_number',$value);
            // $this->db->or_where("FIND_IN_SET('$serial_number', s_serial_number) !=", 0);
            // $this->db->or_where("FIND_IN_SET('$imei', s_imei) !=", 0);
            // $this->db->or_where("FIND_IN_SET('$mobile', s_mobile) !=", 0);
            if (strlen($serial_number) > 0) {
                $this->db->or_like('s_serial_number', $serial_number, 'none');
            }
            if (strlen($imei) > 0) {
                $this->db->or_like('s_imei', $imei, 'none');
            }

            //$this->db->or_like('s_mobile',$mobile, 'none');

            //$this->db->or_where("FIND_IN_SET('$imei',s_imei) !=", 0);
            //$this->db->or_where("FIND_IN_SET('$mobile',s_mobile) !=", 0);
            if (strlen($id) > 0) {
                $this->db->where('s_serial_id !=', $id);
            }
        }

        $this->db->from($this->db->table_serial_no);
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }


    // Bug Fixes Version 1.0 Developer: Lalith Kumar

    // Verify Exits Serial Number
    public function verify_exits_IMEI_numbers($serial_numbers, $id = "", $s_imei = '', $s_mobile)
    {

        $this->db->select('s_serial_number,s_imei,s_mobile');
        $this->db->group_start();

        // Serial Number
        if (is_string($serial_numbers) && strlen($serial_numbers) > 0) {
            // OR-1
            $this->db->or_where('s_serial_number', $serial_numbers, 'none');

        }

        $params = array();
        $serial_numbers = explode(',', $serial_numbers);
        $params['serial_numbers'] = array_values(array_filter(array_unique($serial_numbers)));

        // IMEI Number
        if (strlen($s_imei) > 0) {
            // OR-2
            $this->db->or_where('s_imei', $s_imei, 'none');
        }

        if (strlen($s_mobile) > 0) {
            // OR-3
            $this->db->or_where('s_mobile', $s_mobile, 'none');
        }
        $this->db->group_end();

        if (strlen($id) > 0) {
            $this->db->where('s_serial_id !=', $id);
        }

        $this->db->from($this->db->table_serial_no);
        $result = $this->db->get();
        $result = $result->result_array();
        //print_r($this->db->last_query()); exit();
        return $result;
    }


    // Verify Exits Serial Number In Renewal - Starts
    public function verify_renewal_serial_number_exists($serial_number, $vehicle_RC)
    {


        $this->db->select('veh_serial_no');
        $this->db->where('veh_serial_no', $serial_number);
        //$this->db->where('veh_rc_no !=', $vehicle_RC);
        $this->db->from($this->db->table_renewal);
        $result = $this->db->get();
        $result = $result->result_array();
        return $result;

        //echo $this->db->last_query();exit();
        /*if(empty($result))
			{
				return true;
			}
			return false;*/
    }
    // Verify Exits Serial Number In Renewal - Ends


    // Verify Exits Company Name
    public function verify_exits_product_tac_number($tac_number_list, $id = "")
    {
        $params['p_tac_no'] = array_values(array_filter(array_unique($tac_number_list)));

        $this->db->select('p_tac_no');
        foreach ($params['p_tac_no'] as $key => $value) {
            $this->db->or_where("FIND_IN_SET('$value',p_tac_no) !=", 0);
            if (strlen($id) > 0) {
                $this->db->where('p_product_id !=', $id);
            }
        }

        $this->db->from($this->db->table_products);
        $result = $this->db->get();
        $result = $result->result_array();
        return $result;
    }

    public function verify_exits_dealer_phone_number($phone_number, $id = "")
    {
        $this->db->select('*');
        $this->db->where('user_phone', $phone_number);
        if (strlen($id) > 0) {
            $this->db->where('user_id !=', $id);
        }
        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->result_array();

        if (empty($result)) {
            return true;
        }
        return false;
    }

    public function verify_exits_dealer_email($user_email, $id = "")
    {
        $this->db->select('*');
        $this->db->where('user_email', $user_email);
        if (strlen($id) > 0) {
            $this->db->where('user_id !=', $id);
        }
        $this->db->from($this->db->table_users);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }

    public function verify_exits_customer_phone_number($phone_number, $id = "")
    {
        $this->db->select('*');
        $this->db->where('c_phone', $phone_number);
        if (strlen($id) > 0) {
            $this->db->where('c_customer_id !=', $id);
        }
        $this->db->from($this->db->table_customers);
        $result = $this->db->get();
        $result = $result->result_array();
        print_r($this->db->last_query()); exit();
        if (empty($result)) {
            return true;
        }
        return false;
    }

    public function verify_exits_customer_email($user_email, $id = "")
    {
        $this->db->select('*');
        $this->db->where('c_email', $user_email);
        if (strlen($id) > 0) {
            $this->db->where('c_customer_id !=', $id);
        }
        $this->db->from($this->db->table_customers);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }


    public function getCompanyInfo($id)
    {
        $this->db->select('*');
        $this->db->where('c_company_id', $id);
        $this->db->from($this->db->table_company);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function getProductInfo($id)
    {
        $this->db->select('*');
        $this->db->where('p_product_id', $id);
        $this->db->from($this->db->table_products);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function getMakeInfo($id)
    {
        $this->db->select('*');
        $this->db->where('v_make_id', $id);
        $this->db->from($this->db->table_make);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function getDealerInfo($id)
    {


        $this->db->select('one.*,two.user_id as dis_id,two.user_name as dis_name');
        $this->db->where('one.user_id', $id);
        $this->db->from($this->db->table_users . ' as one');
        $this->db->join($this->db->table_users . ' as two', 'one.user_distributor_id = two.user_id', 'left');
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    //------------Add On Starts ------------

    public function Check_Certificate_Validity($id)
    {
        $this->db->select('*');
        $this->db->where('created_to', $id);
        $this->db->from($this->db->table_certificate);
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function Reduce_Certificate($id)
    {
        $insertRecords = array();
        $this->db->set('used', 'used+1', FALSE);
        $this->db->where('created_to', $id);
        $this->db->update($this->db->table_certificate);
        return 1;
    }

    //------------AddOn Ends ---------------


    public function verify_exits_vehicle_records($value, $field, $id = 0)
    {
        if ($value == '') {
            return true;
        }

        $this->db->select('*');
        $this->db->where($field, $value);
        if ((int)$id > 0) {
            $this->db->where('veh_id !=', $id);
        }
        $this->db->from($this->db->table_vehicle);
        $result = $this->db->get();
        $result = $result->result_array();
        if (empty($result)) {
            return true;
        }
        return false;
    }

    public function infoOfvehicle($user, $veh_id)
    {
        $user_type = $user['user_type'];
        $user_company_id = $user['user_company_id'];
        $this->db->select('veh.*,ser.s_serial_number,ser.s_imei,ser.s_iccid,ser.s_mobile,ser.s_mobile_2,ser.s_used, rto.rto_number, rto.rto_place, make.v_make_name, model.ve_model_name, company.c_company_name, customer.c_customer_name, customer.c_phone, customer.c_email, customer.c_address');
        $this->db->where('veh.veh_id', $veh_id);
        $this->db->from($this->db->table_vehicle . ' as veh');
        $this->db->join($this->db->table_serial_no . ' as ser', 'ser.s_serial_id = veh.veh_serial_no', 'left');
        $this->db->join($this->db->table_rto . ' as rto', 'rto.rto_no = veh.veh_rto_no', 'left');
        $this->db->join($this->db->table_make . ' as make', 'make.v_make_id = veh.veh_make_no', 'left');
        $this->db->join($this->db->table_model . ' as model', 'model.ve_model_id = veh.veh_model_no', 'left');
        $this->db->join($this->db->table_company . ' as company', 'company.c_company_id = veh.veh_company_id', 'left');
        $this->db->join($this->db->table_customers . ' as customer', 'customer.c_customer_id = veh.veh_owner_id', 'left');

        $result = $this->db->get();
        $result = $result->result_array();
        
        foreach ($result as $key => $value) {
            $result[$key]['s_imei'] = (!empty($result[$key]['s_imei']) ? $result[$key]['s_imei'] : '');
            $result[$key]['s_iccid'] = (!empty($result[$key]['s_iccid']) ? $result[$key]['s_iccid'] : '');
        }
        
            $otherdb = $this->load->database('tracking', TRUE);
            $otherdb->select('imei,latitude,longitude');
            $otherdb->where('imei', $result[0]['s_imei']);
            $otherdb->from($otherdb->table_trackings);
            $result2 = $otherdb->get();
            $result2 = $result2->result_array();
            // echo "<pre>";print_r($result2);exit;
            if ($this->validateLatitude($result2[0]['latitude']) && $this->validateLongitude($result2[0]['longitude'])) {
                $result[0]['latitude']  = $result2[0]['latitude'];
                $result[0]['longitude'] = $result2[0]['longitude'];
            } 

            else {
               
                // $result[0]['latitude']  = "00.000";
                // $result[0]['longitude'] = "00.000";
                $DB2  = $this->load->database('postgre_db', TRUE);
                $query= "select latitude,longitude,CONCAT (gps_date,' ', gps_time) as gps_value from public.tbl_trackingalldatas where imei = '" . $result[0]['s_imei'] . "' AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY gps_value desc LIMIT 1";
                //   echo "<pre>";print_r("select latitude,longitude,CONCAT (gps_date,' ', gps_time) as gps_value from public.tbl_trackingalldatas where imei = '" . $result[0]['s_imei'] . "' AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY gps_value desc LIMIT 1");exit;
                $data = $DB2->query($query)->result_array();
                // echo "<pre>";print_r($data);exit();
                 $result[0]['latitude']  = $data[0]['latitude'];
                 $result[0]['longitude'] = $data[0]['longitude'];
                
                
            }
            
        //echo $this->db->last_query();exit();
        return $result;
    }
    
    function validateLatitude($latitude) {
        // Check if the latitude is a valid number within the range -90 to 90
        if (preg_match('/^[-]?((\d|[1-8]\d)(\.\d+)?|90(\.0+)?)$/', $latitude)) {
            return true;
        }
        return false;
    }
    
    function validateLongitude($longitude) {
        // Check if the longitude is a valid number within the range -180 to 180
        if (preg_match('/^[-]?((\d|\d\d|1[0-7]\d)(\.\d+)?|180(\.0+)?)$/', $longitude)) {
            return true;
        }
        return false;
    }
    

    public function listofvehicle($user, $search = '', $is_used = 0, $limit = 0, $offset = 0, $customer_id = '', $filteredIds = [])
    {
        $user_type = $user['user_type'];
        $user_company_id = $user['user_company_id'];
        $this->db->select('veh.*,ser.s_serial_number,ser.s_imei,ser.s_iccid,ser.s_mobile,ser.s_mobile_2,ser.s_used, rto.rto_number, rto.rto_place, make.v_make_name, model.ve_model_name, company.c_company_name, customer.c_customer_name, customer.c_phone, customer.c_email');

        if ((string)$user_type != '0') {
            $this->db->where('ser.s_company_id', $user_company_id);
        }
        if ((int)$user['user_id'] != 1) {
            $this->db->where('veh.veh_created_user_id', $user['user_id']);
        }

        if (count($filteredIds) > 0) {
            $this->db->where_in('veh.veh_id', $filteredIds);
        }

        /*if ($_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }
            $this->db->where('veh.veh_create_date >=', $from);
            $this->db->where('veh.veh_create_date <=', $to);
        } elseif ($_GET['start_date'] != 0) {
            $this->db->where('veh.veh_create_date >=', $_GET['start_date']);
            $this->db->where('veh.veh_create_date <=', date('Y-m-d'));
        } elseif ($_GET['end_date'] != 0) {
            $this->db->where('veh.veh_create_date >=', date('Y-m-d'));
            $this->db->where('veh.veh_create_date <=', $_GET['end_date']);
        }*/

        if (strlen($customer_id) > 0) {
            $this->db->where('veh.veh_owner_id', $customer_id);
        }

        if (strlen($search) > 0) {
            $this->db->group_start();
            // $this->db->or_like('veh.veh_rc_no', $search, 'both');
            // $this->db->or_like('veh.veh_chassis_no', $search, 'both');
            $this->db->or_like('ser.s_serial_number', $search, 'both');
            $this->db->or_like('ser.s_imei', $search, 'both');
            $this->db->or_like('ser.s_iccid', $search, 'both');
            // $this->db->or_like('veh.veh_owner_name', $search, 'both');
            // $this->db->or_like('veh.veh_owner_phone', $search, 'both');
            $this->db->group_end();

        }

        $this->db->from($this->db->table_vehicle . ' as veh');
        $this->db->join($this->db->table_serial_no . ' as ser', 'ser.s_serial_id = veh.veh_serial_no', 'left');
        $this->db->join($this->db->table_rto . ' as rto', 'rto.rto_no = veh.veh_rto_no', 'left');
        $this->db->join($this->db->table_make . ' as make', 'make.v_make_id = veh.veh_make_no', 'left');
        $this->db->join($this->db->table_model . ' as model', 'model.ve_model_id = veh.veh_model_no', 'left');
        $this->db->join($this->db->table_company . ' as company', 'company.c_company_id = veh.veh_company_id', 'left');
        $this->db->join($this->db->table_customers . ' as customer', 'customer.c_customer_id = veh.veh_owner_id', 'left');

        if ($limit > 0 || $offset > 0) {
            $this->db->limit($limit, $offset);
        } else {
            $this->db->limit(50, 0);
        }        
        
        $this->db->order_by("veh.veh_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        foreach ($result as $key => $value) {
            $result[$key]['s_imei'] = (!empty($value['s_imei']) ? $value['s_imei'] : '');
            $result[$key]['s_iccid'] = (!empty($value['s_iccid']) ? $value['s_iccid'] : '');

            $result[$key]['veh_speed_governer_photo'] = str_replace("public/upload/vehicle/", "", $value['veh_speed_governer_photo']);
            $result[$key]['veh_photo'] = str_replace("public/upload/vehicle/", "", $value['veh_photo']);
            $result[$key]['vehicle_owner_id_proof'] = str_replace("public/upload/vehicle_owner_id_proof/", "", $value['vehicle_owner_id_proof']);
            $result[$key]['vehicle_owner_photo'] = str_replace("public/upload/vehicle_owners_photos/", "", $value['vehicle_owner_photo']);
            $result[$key]['rc_book_photo'] = str_replace("public/upload/rc_book_photos/", "", $value['rc_book_photo']);
            $result[$key]['img'] = str_replace("public/upload/rc_book_photos/", "", $value['rc_book_photo']);
            $result[$key]['rc_book_photo'] = str_replace("public/upload/rc_book_photos/", "", $value['rc_book_photo']);
            $result[$key]['veh_cop_validity'] = date('D, d, M, Y H:i A', strtotime($value['veh_cop_validity']));
            $result[$key]['validity_from'] = date('D, d, M, Y H:i A', strtotime($value['validity_from']));
            $result[$key]['validity_to'] = date('D, d, M, Y H:i A', strtotime($value['validity_to']));
            $result[$key]['veh_insert_date'] = date('D, d, M, Y H:i A', strtotime($value['veh_insert_date']));

            $result[$key]['base_url'] = base_url();
            $result[$key]['path']     = "public/temp_upload/";

            $pdfEncode=base64_encode(base64_encode(base64_encode($value['veh_id'])));
            $result[$key]['downloadurl'] = "https://www.psdn.tech/admin/downloadwebpdf?id=".$pdfEncode;
        }
        // echo $this->db->last_query();exit();
        return $result;
    }

    public function listofInvoices($user, $limit, $offset, $search = '', $is_used = 0)
    {
        $user_type = $this->session->userdata('user_type');

        $user_company_id = $this->session->userdata('user_company_id');
        //print_r($user_type);exit();

        $subquery = " where 1=1 ";


        if ($_GET['user_type']) {
            $subquery .= " AND t1.i_user_type = '" . $_GET['user_type'] . "' ";
        }

        if ((int)$user['user_id'] != 1) {
            //$subquery			.= " AND ( t1.i_user_id = '".$user_id."' OR t1.i_created_by = '".$user_id."' ) and t1.i_user_id != '0' ";
            $subquery .= " AND ( t1.i_user_id = '" . $user['user_id'] . "' )  ";
        } else {
            $subquery .= " AND t1.i_user_id != '0' ";
        }

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $subquery .= " AND t1.invoice_number like '%" . $_GET['search'] . "%' ";
        }

        if ($_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }

            $subquery .= " AND ( t1.i_created_date >='" . $from . "' AND t1.i_created_date <= '" . $to . "' ) ";

        } elseif ($_GET['start_date'] != 0) {
            $subquery .= " AND ( t1.i_created_date >='" . $_GET['start_date'] . "' AND t1.i_created_date <= '" . date('Y-m-d') . "' ) ";
        } elseif ($_GET['end_date'] != 0) {

            $subquery .= " AND ( t1.i_created_date >='" . date('Y-m-d') . "' AND t1.i_created_date <= '" . $_GET['end_date'] . "' ) ";

        }


        $query = " select t1.*, t2.*, t3.*, (SELECT t4.user_name FROM ci_users t4 WHERE t4.user_id = t1.i_created_by) as user_name2 from ci_invoices t1 left join ci_products t2 on t1.i_product_id = t2.p_product_id left join ci_users t3 on t3.user_id = t1.i_user_id " . $subquery . " order by t1.i_invoice_id desc limit " . $offset . "," . $limit;

        $results = $this->db->query($query)->result_array();

        return $results;

    }

    // Add on - addition Starts &&&&&&&&&&&
    public function listofvehicleRenewals($limit, $offset, $search = '', $user_id = 0)
    {
        $this->db->select('*');

        if (strlen($search) > 0) {
            $this->db->or_like('veh_rc_no', $search, 'both');
            $this->db->or_like('veh_chassis_no', $search, 'both');
            $this->db->or_like('veh_serial_no', $search, 'both');
            $this->db->or_like('veh_invoice_no', $search, 'both');
        }
        if ((int)$user_id != 1) {
            $this->db->where('veh_created_user_id', $user_id);
        }
        if ($_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }
            $this->db->where('veh_create_date >=', $from);
            $this->db->where('veh_create_date <=', $to);
        } elseif ($_GET['start_date'] != 0) {
            $this->db->where('veh_create_date >=', $_GET['start_date']);
            $this->db->where('veh_create_date <=', date('Y-m-d'));
        } elseif ($_GET['end_date'] != 0) {
            $this->db->where('veh_create_date >=', date('Y-m-d'));
            $this->db->where('veh_create_date <=', $_GET['end_date']);
        }
        $this->db->from($this->db->table_renewal);
        //$this->db->join($this->db->table_serial_no.' as ser', 'ser.s_serial_id = veh.veh_serial_no', 'left');

        $this->db->limit($limit, $offset);
        $this->db->order_by("veh_id", "desc");
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function totalNoOfVehicleRenewals($user_id = 0)
    {
        $this->db->select('*');
        if ((int)$user_id != 1) {
            $this->db->where('veh_created_user_id', $user_id);
        }
        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {

            $this->db->or_like('veh_rc_no', $_GET['search'], 'both');
            $this->db->or_like('veh_chassis_no', $_GET['search'], 'both');
            $this->db->or_like('veh_serial_no', $_GET['search'], 'both');
            $this->db->or_like('veh_invoice_no', $_GET['search'], 'both');
        }
        if ($_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }
            $this->db->where('veh_create_date >=', $from);
            $this->db->where('veh_create_date <=', $to);
        } elseif ($_GET['start_date'] != 0) {
            $this->db->where('veh_create_date >=', $_GET['start_date']);
            $this->db->where('veh_create_date <=', date('Y-m-d'));
        } elseif ($_GET['end_date'] != 0) {
            $this->db->where('veh_create_date >=', date('Y-m-d'));
            $this->db->where('veh_create_date <=', $_GET['end_date']);
        }
        $this->db->from($this->db->table_renewal);
        $result = $this->db->count_all_results();
        return $result;
    }

    // Add on - addition Ends
    public function totalNoOfVehicle($user_id = 0)
    {
        $this->db->select('veh_id');
        $this->db->where('veh_created_user_id', $user_id);

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $this->db->or_like('veh_rc_no', $_GET['search'], 'both');
            $this->db->or_like('veh_chassis_no', $_GET['search'], 'both');
            $this->db->or_like('veh_serial_no', $_GET['search'], 'both');
            $this->db->or_like('veh_invoice_no', $_GET['search'], 'both');
        }

        if ((isset($_GET['start_date']) && isset($_GET['end_date'])) && $_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }
            $this->db->where('veh_create_date >=', $from);
            $this->db->where('veh_create_date <=', $to);
        } elseif (isset($_GET['start_date']) && $_GET['start_date'] != 0) {
            $this->db->where('veh_create_date >=', $_GET['start_date']);
            $this->db->where('veh_create_date <=', date('Y-m-d'));
        } elseif (isset($_GET['end_date']) && $_GET['end_date'] != 0) {
            $this->db->where('veh_create_date >=', date('Y-m-d'));
            $this->db->where('veh_create_date <=', $_GET['end_date']);
        }

        if (isset($_GET['customer_id'])) {
            $this->db->where('veh_owner_id', $_GET['customer_id']);
        }

        $this->db->from($this->db->table_vehicle);
        
        $result = $this->db->count_all_results();
        // echo $this->db->last_query();exit();
        return $result;
    }

    public function totalNoOfInvoices($user_id = 0)
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');


        $subquery = " where 1=1 ";

        if ($_GET['user_type']) {
            $subquery .= " AND t1.i_user_type = '" . $_GET['user_type'] . "' ";
        }

        if ((int)$user_id != 1) {

            $subquery .= " AND ( t1.i_user_id = '" . $user_id . "' OR t1.i_created_by = '" . $user_id . "' ) and t1.i_user_id != '0' ";
        } else {
            $subquery .= " AND t1.i_user_id != '0' ";
        }

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $subquery .= " AND t1.invoice_number like '%" . $_GET['search'] . "%' ";
        }

        if ($_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }

            $subquery .= " AND ( t1.i_created_date >='" . $from . "' AND t1.i_created_date <= '" . $to . "' ) ";

        } elseif ($_GET['start_date'] != 0) {
            $subquery .= " AND ( t1.i_created_date >='" . $_GET['start_date'] . "' AND t1.i_created_date <= '" . date('Y-m-d') . "' ) ";
        } elseif ($_GET['end_date'] != 0) {

            $subquery .= " AND ( t1.i_created_date >='" . date('Y-m-d') . "' AND t1.i_created_date <= '" . $_GET['end_date'] . "' ) ";

        }

        $query = " select t1.* from ci_invoices t1 " . $subquery;


        return $query;

    }


    public function getVehicleInfo($id, $user = 0)
    {
        $this->db->select('veh.*,cus.c_email as veh_owner_email,mod.ve_model_name,ser.s_serial_number');
        $this->db->where('veh_id', $id);
        $this->db->from($this->db->table_vehicle . ' as veh');
        $this->db->join($this->db->table_model . ' as mod', 'veh.veh_model_no = mod.ve_model_id', 'left');
        $this->db->join($this->db->table_serial_no . ' as ser', 'ser.s_serial_id = veh.veh_serial_no', 'left');
        $this->db->join($this->db->table_customers . ' as cus', 'cus.c_phone = veh.veh_owner_phone', 'left');
        $result = $this->db->get();
        $result = $result->row_array();

        return $result;
    }

    public function getPdfRenewalVehicleInfo($id)
    {
        $this->db->select('veh.*,ue.user_info,rto.rto_number,rto.rto_place,com.c_company_name,mke.v_make_name,model.ve_model_name');
        $this->db->where('veh.veh_id', $id);
        $this->db->from($this->db->table_renewal . ' veh');
        $this->db->join($this->db->table_users . ' ue', 'veh.veh_created_user_id = ue.user_id', 'left');
        $this->db->join($this->db->table_rto . ' rto', 'rto.rto_no = veh.veh_rto_no', 'left');
        $this->db->join($this->db->table_company . ' com', 'com.c_company_id = veh.veh_company_id', 'left');
        $this->db->join($this->db->table_make . ' mke', 'mke.v_make_id = veh.veh_make_no', 'left');
        //$this->db->join($this->db->table_serial_no.' ser', 'ser.s_serial_id = veh.veh_serial_no', 'left');
        $this->db->join($this->db->table_model . ' model', 'model.ve_model_id = veh.veh_model_no', 'left');
        $result = $this->db->get();
        //  echo $this->db->last_query();exit();
        $result = $result->row_array();

        return $result;
    }

    public function getPdfVehicleInfo($id)
    {
        $this->db->select('veh.*,ue.user_info,rto.rto_number,rto.rto_place,com.c_company_name,mke.v_make_name,ser.s_serial_number,ser.s_imei,ser.s_mobile,model.ve_model_name,veh_customer.c_email, table_invoices_customer.invoice_number');
        $this->db->where('veh.veh_id', $id);
        $this->db->from($this->db->table_vehicle . ' veh');
        $this->db->join($this->db->table_users . ' ue', 'veh.veh_created_user_id = ue.user_id', 'left');
        $this->db->join($this->db->table_rto . ' rto', 'rto.rto_no = veh.veh_rto_no', 'left');
        $this->db->join($this->db->table_company . ' com', 'com.c_company_id = veh.veh_company_id', 'left');
        $this->db->join($this->db->table_make . ' mke', 'mke.v_make_id = veh.veh_make_no', 'left');
        $this->db->join($this->db->table_serial_no . ' ser', 'ser.s_serial_id = veh.veh_serial_no', 'left');
        $this->db->join($this->db->table_model . ' model', 'model.ve_model_id = veh.veh_model_no', 'left');
        $this->db->join($this->db->table_customers . ' veh_customer', 'veh_customer.c_customer_id = veh.veh_owner_id', 'left');
        $this->db->join($this->db->table_invoices_customer . ' table_invoices_customer', 'table_invoices_customer.i_to_customer_id = veh.veh_owner_id', 'left');
        $result = $this->db->get();
        //echo $this->db->last_query();exit();
        $result = $result->row_array();

        return $result;
    }

    public function getPdfInvoiceInfo($id)
    {
        $this->db->select('inv.*,prod.*,brand.*,usr.user_name,usr.user_email, usr.user_photo as companylogo, usr.user_own_company as FromCompany, usr.gstin,usr.user_info,usr2.user_name as user_name2,usr2.user_email as user_email2, usr2.user_own_company as ToCompany, usr2.gstin as gstin2,usr2.user_info as user_info2');
        $this->db->where('inv.i_invoice_id', $id);

        $this->db->from($this->db->table_invoices . ' as inv');
        $this->db->join($this->db->table_products . ' as prod', 'prod.p_product_id = inv.i_product_id', 'left');
        $this->db->join($this->db->table_company . ' as brand', 'prod.p_company_id = brand.c_company_id', 'left');
        $this->db->join($this->db->table_users . ' as usr', 'usr.user_id = inv.i_created_by', 'left');
        $this->db->join($this->db->table_users . ' as usr2', 'usr2.user_id = inv.i_user_id', 'left');
        $result = $this->db->get();
        //  echo $this->db->last_query();exit();
        $result = $result->row_array();

        return $result;
    }

    public function getPdfInvoiceSerialsInfo($serial_ids)
    {
        $this->db->select('*');
        $this->db->from($this->db->table_serial_no);
        $this->db->join($this->db->table_products, 's_product_id = p_product_id', 'left');
        $this->db->where_in('s_serial_id', $serial_ids);
        $this->db->order_by("s_serial_id");
        $result = $this->db->get();
        $result = $result->result_array();
        //echo $this->db->last_query();exit();
        return $result;
    }

    public function getUserIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function userTracking()
    {
        // echo "return data found for item===>"; exit; 
        $this->db->insert($this->db->table_user_histroy, array(
            'user_id' => $this->session->userdata('user_id'),
            'user_type' => $this->session->userdata('user_type'),
            'action_name' => "Login Successfully",
            'ip_address' => $this->getUserIpAddr()
        ));  
    }

    public function listofInvoices_customers($limit, $offset, $search = '', $user_id = 0)
    {
        $user_type = $this->session->userdata('user_type');
        $user_company_id = $this->session->userdata('user_company_id');

        $subquery = " where 1=1 ";
        if ($user_type == 2) {

            $subquery1 = "t1.i_created_by = '" . $user_id . "' ";

            $query = " select distinct  t1.*, (SELECT  t4.user_name FROM ci_users t4 WHERE t4.user_id = t1.i_user_id) as user_name3 ,(SELECT  t4.user_name FROM ci_users t4 WHERE t4.user_id = t1.i_created_by) as user_name2 from ci_invoices t1   where " . $subquery1 . "  ";

            $results = $this->db->query($query)->result_array();
            //echo"<pre>";print_r($results);exit();

            return $results;
            exit();
        } else if ($user_type == 0) {


            $subquery1 = "t1.i_created_by = '" . $user_id . "' ";

            $query = " select distinct  t1.*, (SELECT  t4.user_name FROM ci_users t4 WHERE t4.user_id = t1.i_user_id) as user_name3 ,(SELECT  t4.user_name FROM ci_users t4 WHERE t4.user_id = t1.i_created_by) as user_name2 from ci_invoices t1   where " . $subquery1 . "  ";

            $results = $this->db->query($query)->result_array();
            //echo"<pre>";print_r($results);exit();

            return $results;
            exit();

        }

        if ($_GET['user_type']) {
            $subquery .= " AND t1.i_user_type = '" . $_GET['user_type'] . "' ";
        }

        if ((int)$user_id != 1) {

            $subquery .= " AND ( t1.i_user_id = '" . $user_id . "' OR t1.i_created_by = '" . $user_id . "' ) and t1.i_user_id != '0' ";
        } else {
            $subquery .= " AND t1.i_user_id != '0' ";
        }

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $subquery .= " AND t1.invoice_number like '%" . $_GET['search'] . "%' ";
        }

        if ($_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }

            $subquery .= " AND ( t1.i_created_date >='" . $from . "' AND t1.i_created_date <= '" . $to . "' ) ";

        } elseif ($_GET['start_date'] != 0) {
            $subquery .= " AND ( t1.i_created_date >='" . $_GET['start_date'] . "' AND t1.i_created_date <= '" . date('Y-m-d') . "' ) ";
        } elseif ($_GET['end_date'] != 0) {

            $subquery .= " AND ( t1.i_created_date >='" . date('Y-m-d') . "' AND t1.i_created_date <= '" . $_GET['end_date'] . "' ) ";

        }

        $query = " select t1.*, t2.*, t3.*, (SELECT t4.user_name FROM ci_users t4 WHERE t4.user_id = t1.i_user_id) as user_name2, (SELECT t4.c_customer_name FROM ci_customers t4 WHERE t4.c_customer_id = t1.i_to_customer_id) as user_name3 from ci_invoices_customer t1 left join ci_products t2 on t1.i_product_id = t2.p_product_id left join ci_users t3 on t3.user_id = t1.i_to_customer_id " . $subquery . " order by t1.i_invoice_id desc limit " . $offset . "," . $limit;
        $results = $this->db->query($query)->result_array();
        //echo"<pre>";print_r($results);exit();

        return $results;

    }

    public function totalNoOfInvoices_customers($user_id = 0)
    {
        $user_type = $this->session->userdata('user_type');
        
        $user_company_id = $this->session->userdata('user_company_id');


        //echo "id".$user_company_id; exit;

        $subquery = " where 1=1 ";

        if ($_GET['user_type']) {
            $subquery .= " AND t1.i_user_type = '" . $_GET['user_type'] . "' ";
        }

        if ((int)$user_id != 1) {

            $subquery .= " AND ( t1.i_user_id = '" . $user_id . "' OR t1.i_created_by = '" . $user_id . "' ) and t1.i_user_id != '0' ";
        } else {
            $subquery .= " AND t1.i_user_id != '0' ";
        }

        if (isset($_GET['search']) && strlen($_GET['search']) > 0) {
            $subquery .= " AND t1.invoice_number like '%" . $_GET['search'] . "%' ";
        }

        if ($_GET['start_date'] != 0 && $_GET['end_date'] != 0) {
            $from = $_GET['start_date'];
            $to = $_GET['end_date'];
            if (strtotime($_GET['end_date']) < strtotime($_GET['start_date'])) {
                $from = $_GET['end_date'];
                $to = $_GET['start_date'];
            }

            $subquery .= " AND ( t1.i_created_date >='" . $from . "' AND t1.i_created_date <= '" . $to . "' ) ";

        } elseif ($_GET['start_date'] != 0) {
            $subquery .= " AND ( t1.i_created_date >='" . $_GET['start_date'] . "' AND t1.i_created_date <= '" . date('Y-m-d') . "' ) ";
        } elseif ($_GET['end_date'] != 0) {

            $subquery .= " AND ( t1.i_created_date >='" . date('Y-m-d') . "' AND t1.i_created_date <= '" . $_GET['end_date'] . "' ) ";

        }

        $query = " select t1.* from ci_invoices_customer t1 " . $subquery;
       // print_r($query);exit();
        return $query;

    }

    public function getPdfInvoiceInfo_customers($id)
    {
        $this->db->select('inv.*, prod.*, brand.*, usr.user_name, usr.user_email, usr.user_photo as companylogo, usr.user_own_company as FromCompany, usr.gstin, usr.acc_no, usr.acc_name,usr.acc_branch,usr.acc_ifsc_code,usr.user_info, customers.c_customer_name, customers.c_address, customers.c_email, customers.c_phone');
        $this->db->where('inv.i_invoice_id', $id);

        $this->db->from('ci_invoices_customer' . ' as inv');
        $this->db->join($this->db->table_products . ' as prod', 'prod.p_product_id = inv.i_product_id', 'left');
        $this->db->join($this->db->table_company . ' as brand', 'prod.p_company_id = brand.c_company_id', 'left');
        $this->db->join($this->db->table_users . ' as usr', 'usr.user_id = inv.i_user_id', 'left');
        $this->db->join($this->db->table_customers . ' as customers', 'customers.c_customer_id = inv.i_to_customer_id', 'left');
        $result = $this->db->get();
        //echo $this->db->last_query();exit();
        $result = $result->row_array();

        return $result;
    }

    public function veh_lat_long()
    {
        $otherdb = $this->load->database('tracking', TRUE);
        $otherdb->select('vehicleRegNumber,vtrackingId,imei,latitude,longitude,distance');
        $otherdb->from($otherdb->table_trackings);
        $results = $otherdb->get();
        $results = $results->result_array();
        return $results;
    }

    public function getIMEISerialInfo($imei)
    {
        $this->db->select('ser.*');
        $this->db->where('ser.s_imei = ', $imei);
        $this->db->from($this->db->table_serial_no . ' as ser');
        $result = $this->db->get();
        $result = $result->row_array();
        return $result;
    }

    public function updateOTAForIMEI($user, $params)
    {
        $update_data = [
            'IMEI'          => $params['imei'],
            'RespHandling'  => $params['selectedVal'],
            'CreateTime'    => date('Y-m-d H:i:s'),
            'LastUpdatedBy' => $user['user_id'],
        ];

        $DB2 = $this->load->database('postgre_db', TRUE);

        // $query = 'INSERT INTO public.tbl_change_otadata ("IMEI", "RespHandling", "LastUpdatedBy") VALUES ("' . $update_data['IMEI'] . '", "' . $update_data['RespHandling'] . '", "' . $update_data['LastUpdatedBy'] . '")';
        $query = 'INSERT INTO public.tbl_ota_param ("IMEI", "RespHandling", "LastUpdatedBy") VALUES (\'' . $update_data["IMEI"] . '\', \'' . $update_data["RespHandling"] . '\', \'' . $update_data["LastUpdatedBy"] . '\')';

        return $DB2->query($query);
    }

    public function fetch_where($table, $fields, $where = [], $single = true)
    {
        $this->db->select($fields);
        if (!empty($where)) {
            $this->db->where($where);    
        }
        
        $this->db->from($table);
        $result = $this->db->get();
        if ($single) {
            $result = $result->row_array();
        } else {
            $result = $result->result_array();
        }

        return $result;
    }

    public function map_data($imei)
    {
        $DB2 = $this->load->database('postgre_db', TRUE);
        // echo "<pre>";print_r($DB2); exit;

        $where_imei = " AND imei = '" . $imei . "'";

        // get table_alert_data
        $query = "select * from public.tbl_alert_data where isclosed = 'true' " . $where_imei . " ORDER BY id DESC LIMIT 1";
        // echo $query; exit;
        $data = $DB2->query($query)->result();
        $data = (array) (isset($data[0]) ? $data[0] : []);

        $this->db->select('veh_id, veh_rc_no, s_mobile, s_mobile_2, s_used');
        $this->db->where('s_imei', $imei);
        $this->db->from($this->db->table_serial_no);
        $this->db->join($this->db->table_vehicle, 'veh_serial_no = s_serial_id', 'left');
        $result = $this->db->get();
        $result = $result->result_array();
        // echo "<pre>";print_r($result); exit;

        // echo $this->db->last_query();exit();
        $created_date = '';

        // get table_alert_data
        $query = "select * from public.tbl_trackingalldatas where imei = '" . $imei . "' ORDER BY id DESC LIMIT 1";
        // echo $query; exit;
        $alldatasTrackingInfo = $DB2->query($query)->result();
        $alldatasTrackingInfo = (array) (isset($alldatasTrackingInfo[0]) ? $alldatasTrackingInfo[0] : []);

        // get table_alert_data
        $query = "select * from public.tbl_unregistered_device_data where imei = '" . $imei . "' ORDER BY id DESC LIMIT 1";
        // echo $query; exit;
        $unregisteredTrackingInfo = $DB2->query($query)->result();
        $unregisteredTrackingInfo = (array) (isset($unregisteredTrackingInfo[0]) ? $unregisteredTrackingInfo[0] : []);
        // echo $unregisteredTrackingInfo; exit;
        $response['status'] = 0;
        $response['date'] = '';
        $response['time'] = '';
        $response['imei'] = '';
        $response['alert_type'] = 0;
        $response['main_power'] = 0;
        $response['ignition'] = (isset($alldatasTrackingInfo['ignition']) ? $alldatasTrackingInfo['ignition'] : 0);
        $response['latitude'] = (isset($unregisteredTrackingInfo['latitude']) && $unregisteredTrackingInfo['latitude'] != null) ? $unregisteredTrackingInfo['latitude'] : 0;
        $response['longitude'] = (isset($unregisteredTrackingInfo['longitude']) && $unregisteredTrackingInfo['longitude'] != null) ? $unregisteredTrackingInfo['longitude'] : 0;
        // echo "<pre>";print_r($response); exit;
        
        if (!empty($result)) {
            $response['veh_rc_no'] = isset($result[0]['veh_rc_no']) ? $result[0]['veh_rc_no'] : '';
            $response['sim_nos']   = (isset($result[0]['s_mobile']) ? $result[0]['s_mobile'] : '') . (isset($result[0]['s_mobile_2']) ? ', ' . $result[0]['s_mobile_2'] : '');
            $response['status']    = 1;

            $response['imei'] = $imei;
            $response['alert_type'] = (!empty($data['alert_type'])) ? $data['alert_type'] : '0';

            $response['emergency_alert_at'] = '';
            $response['emergency_alert_end_at'] = '';
            $response['wire_disconnected_at'] = '';
            $response['wire_disconnected_end_at'] = '';
            $response['mail_power_at'] = '';
            $response['mail_power_end_at'] = '';
            $response['tilt_alert_at'] = '';
            $response['tilt_alert_end_at'] = '';
            if ($response['alert_type'] == 10) { //Emergency Alert
                $response['emergency_alert_at'] = date('D, d, M, Y H:i A', strtotime($data['start_time']));
                $response['emergency_alert_end_at'] = date('D, d, M, Y H:i A', strtotime($data['end_time']));
            } else if ($response['alert_type'] == 16) {//Wire Disconnect or Panic button wire cut
                $response['wire_disconnected_at'] = date('D, d, M, Y H:i A', strtotime($data['start_time']));
                $response['wire_disconnected_end_at'] = date('D, d, M, Y H:i A', strtotime($data['end_time']));
            } else if ($response['alert_type'] == 3) {//Main power
                $response['mail_power_at'] = date('D, d, M, Y H:i A', strtotime($data['start_time']));
                $response['mail_power_end_at'] = date('D, d, M, Y H:i A', strtotime($data['end_time']));
            } else if ($response['alert_type'] == 22) {//Tilt Alert
                $response['tilt_alert_at'] = date('D, d, M, Y H:i A', strtotime($data['start_time']));
                $response['tilt_alert_end_at'] = date('Y-m-d H:i:s', strtotime($data['end_time']));
            }

            if ($result[0]['s_used'] == 1) {
                if (!empty($alldatasTrackingInfo)) {
                    $created_date = $alldatasTrackingInfo['gps_date'] . ' ' . $alldatasTrackingInfo['gps_time'];
                }
                $response['date'] = ($created_date != '' ? date('D, d, M, Y', strtotime($created_date)) : '');
                $response['time'] = ($created_date != '' ? date('H:i A', strtotime($created_date)) : '');
                
                $response['latitude'] = (isset($alldatasTrackingInfo['latitude']) && $alldatasTrackingInfo['latitude'] != null) ? $alldatasTrackingInfo['latitude'] : 0;
                $response['longitude'] = (isset($alldatasTrackingInfo['longitude']) && $alldatasTrackingInfo['longitude'] != null) ? $alldatasTrackingInfo['longitude'] : 0;
                $response['main_power'] = (isset($alldatasTrackingInfo['main_power_status']) && $alldatasTrackingInfo['main_power_status'] != null) ? $alldatasTrackingInfo['main_power_status'] : 0;
            } else {
                if (!empty($unregisteredTrackingInfo)) {
                    $created_date = $unregisteredTrackingInfo['created_time'];
                }
                $response['date'] = ($created_date != '' ? date('Y-m-d', strtotime($created_date)) : '');
                $response['time'] = ($created_date != '' ? date('H:i:s', strtotime($created_date)) : '');
                $response['main_power'] = (isset($alldatasTrackingInfo['main_power_status']) && $alldatasTrackingInfo['main_power_status'] != null) ? $alldatasTrackingInfo['main_power_status'] : 0;
            }
        }

        return $response;
    }
}