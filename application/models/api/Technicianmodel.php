<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Technicianmodel extends CI_Model
{
        public function __construct()
        {
                parent::__construct();             
        }

        public function get_imei_data($params)
        {
                $this->db->select('serial.s_serial_id, serial.s_serial_number, serial.s_imei, serial.s_iccid, serial.s_mobile, serial.s_mobile_2, serial.s_dealer_id, serial.s_used, serial.fitment, customer.c_customer_name, dealer.user_name, serial.assign_to_customer_on, model.ve_model_name');
                $this->db->where('s_imei', $params['imei']);
                $this->db->from($this->db->table_serial_no . ' as serial');
                $this->db->join($this->db->table_users . ' as dealer', 'dealer.user_id = serial.s_dealer_id', 'left');
                $this->db->join($this->db->table_customers . ' as customer', 'customer.c_customer_id = serial.customer_id', 'left');
                $this->db->join($this->db->table_device_fitment . ' as fitment', 'fitment.fitment_imei = serial.s_imei', 'left');
                $this->db->join($this->db->table_vehicle . ' as certificate', 'certificate.veh_serial_no = serial.s_serial_id', 'left');
                $this->db->join($this->db->table_model . ' as model', 'model.ve_model_id = certificate.veh_model_no', 'left');

                $result = $this->db->get();
                $result = $result->result_array();

                $DB2 = $this->load->database('postgre_db', TRUE);
                $query = "select * from public.tbl_unregistered_device_data where imei = '" . $params['imei'] . "'  ORDER BY id DESC LIMIT 1";
                $data = $DB2->query($query)->result();

                $return_data = [];
                if (!empty($result)) {
                        $return_data["s_serial_id"] = (isset($result[0]["s_serial_id"]) && $result[0]["s_serial_id"] != null) ? $result[0]["s_serial_id"] : '';
                        $return_data["s_serial_number"] = (isset($result[0]["s_serial_number"]) && $result[0]["s_serial_number"] != null) ? $result[0]["s_serial_number"] : '';
                        $return_data["s_imei"] = (isset($result[0]["s_imei"]) && $result[0]["s_imei"] != null) ? $result[0]["s_imei"] : '';
                        $return_data["s_iccid"] = (isset($result[0]["s_iccid"]) && $result[0]["s_iccid"] != null) ? $result[0]["s_iccid"] : '';
                        $return_data["s_mobile"] = (isset($result[0]["s_mobile"]) && $result[0]["s_mobile"] != null) ? $result[0]["s_mobile"] : '';
                        $return_data["s_mobile_2"] = (isset($result[0]["s_mobile_2"]) && $result[0]["s_mobile_2"] != null) ? $result[0]["s_mobile_2"] : '';
                        $return_data["s_dealer_id"] = (isset($result[0]["s_dealer_id"]) && $result[0]["s_dealer_id"] != null) ? $result[0]["s_dealer_id"] : '';
                        $return_data["fitment"] = (isset($result[0]["fitment"]) && $result[0]["fitment"] != null) ? $result[0]["fitment"] : '';
                        $return_data["dealer_name"] = (isset($result[0]["user_name"]) && $result[0]["user_name"] != null) ? $result[0]["user_name"] : '';
                        $return_data["customer_name"] = (isset($result[0]["c_customer_name"]) && $result[0]["c_customer_name"] != null) ? $result[0]["c_customer_name"] : '';
                        $return_data["vehicle_model"] = (isset($result[0]["ve_model_name"]) && $result[0]["ve_model_name"] != null) ? $result[0]["ve_model_name"] : '';
                        $return_data["vehicle_rc_no"] = (isset($result[0]["veh_rc_no"]) && $result[0]["veh_rc_no"] != null) ? $result[0]["veh_rc_no"] : '';
                        $return_data["createdOn"] = (isset($data[0]) && property_exists($data[0], 'created_time')) ? date('D, d, M, Y H:i A', strtotime($data[0]->created_time)) : '';
                        $return_data["latitude"] = (isset($data[0]) && property_exists($data[0], 'latitude')) ? $data[0]->latitude : '';
                        $return_data["longitude"] = (isset($data[0]) && property_exists($data[0], 'longitude')) ? $data[0]->longitude : '';
                        $return_data["s_used"] = (isset($result[0]["s_used"]) && $result[0]["s_used"] != null) ? $result[0]["s_used"] : '';

                        if ($return_data["fitment"] == 1) {
                                $return_data["fitment_data"] = $this->get_fitment_list(['imei' => $return_data["s_imei"]]);
                        } else {
                                $return_data["fitment_data"] = [];
                        }

                        if ($result[0]["s_dealer_id"] == 0) {
                                $return_data["case_type"] = 1;
                        } else if ($result[0]["s_dealer_id"] > 0 && $result[0]["fitment"] == 0 && $result[0]["s_used"] == 0) {
                                $return_data["case_type"] = 2;
                        } else if ($result[0]["s_dealer_id"] > 0 && $result[0]["fitment"] == 1 && $result[0]["s_used"] == 0) {
                                $return_data["case_type"] = 3;
                        } else if ($result[0]["s_dealer_id"] > 0 && $result[0]["fitment"] == 1 && $result[0]["s_used"] == 1) {
                                $return_data["case_type"] = 4;
                        } else {
                                $return_data["case_type"] = 5;
                        }
                }

                return $return_data;
        }

        public function get_fitment_list($params = [])
        {
                $this->db->select('fitment.*, serial.fitment, serial.s_serial_number, serial.s_serial_number, serial.s_dealer_id, dealer.user_name as dealer_name');
                if (isset($params['fitment_id'])) {
                        $this->db->where('fitment.fitment_id', $params['fitment_id']);
                }
                if (isset($params['search'])) {
                        // $this->db->where('fitment.fitment_imei', $params['imei']);
                        $this->db->like('fitment.fitment_imei', $params['search'], 'both');
                }
                if (isset($params['imei'])) {
                        $this->db->where('fitment.fitment_imei', $params['imei']);
                }
                $this->db->from($this->db->table_device_fitment . ' as fitment');
                $this->db->join($this->db->table_serial_no . ' as serial', 'serial.s_imei = fitment.fitment_imei', 'left');
                $this->db->join($this->db->table_users . ' as dealer', 'dealer.user_id = serial.s_dealer_id', 'left');
                if (isset($params['limit']) && isset($params['offset']))
                {
                        $this->db->limit($params['limit'], $params['offset']);
                }
                
                $this->db->order_by("fitment_id", "desc");
                $result = $this->db->get();
                $result = $result->result_array();
                // echo "<pre>";print_r($this->db->last_query());exit;

                $return_data = [];
                if (!empty($result)) {
                        for ($i=0; $i < count($result); $i++) {
                                $return_data[$i]['fitment_id'] = (isset($result[$i]["fitment_id"]) && $result[$i]["fitment_id"] != null) ? $result[$i]["fitment_id"] : '';
                                $return_data[$i]['fitment_imei'] = (isset($result[$i]["fitment_imei"]) && $result[$i]["fitment_imei"] != null) ? $result[$i]["fitment_imei"] : '';
                                $return_data[$i]['fitment_latitude'] = (isset($result[$i]["fitment_latitude"]) && $result[$i]["fitment_latitude"] != null) ? $result[$i]["fitment_latitude"] : '';
                                $return_data[$i]['fitment_longitude'] = (isset($result[$i]["fitment_longitude"]) && $result[$i]["fitment_longitude"] != null) ? $result[$i]["fitment_longitude"] : '';
                                $return_data[$i]['fitment_photo'] = (isset($result[$i]["fitment_photo"]) && $result[$i]["fitment_photo"] != null) ? $result[$i]["fitment_photo"] : '';
                                $return_data[$i]['fitment_userid'] = (isset($result[$i]["fitment_userid"]) && $result[$i]["fitment_userid"] != null) ? $result[$i]["fitment_userid"] : '';
                                $return_data[$i]['fitment_comments'] = (isset($result[$i]["fitment_comments"]) && $result[$i]["fitment_comments"] != null) ? $result[$i]["fitment_comments"] : '';
                                $return_data[$i]['fitment_createdOn'] = (isset($result[$i]["fitment_createdOn"]) && $result[$i]["fitment_createdOn"] != null) ? date('D, n, M, Y', strtotime($result[$i]["fitment_createdOn"])) : '';
                                $return_data[$i]['fitment_createdOn_time'] = (isset($result[$i]["fitment_createdOn"]) && $result[$i]["fitment_createdOn"] != null) ? date('D, d, M, Y H:i A', strtotime($result[$i]["fitment_createdOn"])) : '';
                                $return_data[$i]['s_serial_number'] = (isset($result[$i]["s_serial_number"]) && $result[$i]["s_serial_number"] != null) ? $result[$i]["s_serial_number"] : '';
                                $return_data[$i]['s_dealer_id'] = (isset($result[$i]["s_dealer_id"]) && $result[$i]["s_dealer_id"] != null) ? $result[$i]["s_dealer_id"] : '';
                                $return_data[$i]['dealer_name'] = (isset($result[$i]["dealer_name"]) && $result[$i]["dealer_name"] != null) ? $result[$i]["dealer_name"] : '';
                                $return_data[$i]['fitment_status'] = (isset($result[$i]["fitment"]) && $result[$i]["fitment"] != null) ? $result[$i]["fitment"] : '';

                                /*if ($return_data[$i]['fitment_imei'] != '' && $return_data[$i]['fitment_status'] == 1) {
                                        $return_data[$i]["fitment_data"] = $this->get_fitment_list(['imei' => $return_data[$i]['fitment_imei']]);
                                } else {
                                        $return_data[$i]["fitment_data"] = [];
                                }*/
                        }
                }

                return $return_data;
        }
        
        public function get_lat_long($params){
            if($params['type'] == 1){
                $this->db->select('fitment_latitude as latitude,fitment_longitude as longitude');
                $this->db->from($this->db->table_device_fitment);
                $this->db->where('fitment_imei', $params['imei']);
                $result = $this->db->get();
                $result = $result->result_array();
            }
            if($params['type'] == 2){
                $DB3 = $this->load->database('tracking', TRUE);
                // echo "<pre>";print_r($DB3);exit;
                $query = "select latitude,longitude from gps_livetracking_data where imei = '" . $params['imei'] . "' ";
                // echo "<pre>";print_r("select * from gps_livetracking_data where imei = '" . $params['imei'] . "' ");exit;
                $result = $DB3->query($query)->result_array();
                
            }
            return $result;
        }
        
        public function get_fitment_list_new($params = [],$user)
        {
                $this->db->select('fitment.*, serial.fitment, serial.s_serial_number, serial.s_serial_number, serial.s_dealer_id, dealer.user_name as dealer_name');
                if (isset($params['fitment_id'])) {
                        $this->db->where('fitment.fitment_id', $params['fitment_id']);
                }
                if (isset($params['search'])) {
                        // $this->db->where('fitment.fitment_imei', $params['imei']);
                        $this->db->like('fitment.fitment_imei', $params['search'], 'both');
                }
                if (isset($params['imei'])) {
                        $this->db->where('fitment.fitment_imei', $params['imei']);
                }
                $this->db->from($this->db->table_device_fitment . ' as fitment');
                $this->db->join($this->db->table_serial_no . ' as serial', 'serial.s_imei = fitment.fitment_imei', 'left');
                $this->db->join($this->db->table_users . ' as dealer', 'dealer.user_id = serial.s_dealer_id', 'left');
                if (isset($params['limit']) && isset($params['offset']))
                {
                        $this->db->limit($params['limit'], $params['offset']);
                }
                $this->db->order_by("fitment_id", "desc");
                if($user['user_type'] == 1){
                    $this->db->where('serial.s_dealer_id', $user['user_id']);
                }
                if($user['user_type'] == 6){
                    $this->db->where('fitment.fitment_userid', $user['user_id']);
                }
                $result = $this->db->get();
                $result = $result->result_array();
                // echo "<pre>";print_r($this->db->last_query());exit;

                $return_data = [];
                if (!empty($result)) {
                        for ($i=0; $i < count($result); $i++) {
                                $return_data[$i]['fitment_id'] = (isset($result[$i]["fitment_id"]) && $result[$i]["fitment_id"] != null) ? $result[$i]["fitment_id"] : '';
                                $return_data[$i]['fitment_imei'] = (isset($result[$i]["fitment_imei"]) && $result[$i]["fitment_imei"] != null) ? $result[$i]["fitment_imei"] : '';
                                $return_data[$i]['fitment_latitude'] = (isset($result[$i]["fitment_latitude"]) && $result[$i]["fitment_latitude"] != null) ? $result[$i]["fitment_latitude"] : '';
                                $return_data[$i]['fitment_longitude'] = (isset($result[$i]["fitment_longitude"]) && $result[$i]["fitment_longitude"] != null) ? $result[$i]["fitment_longitude"] : '';
                                $return_data[$i]['fitment_photo'] = (isset($result[$i]["fitment_photo"]) && $result[$i]["fitment_photo"] != null) ? $result[$i]["fitment_photo"] : '';
                                $return_data[$i]['fitment_userid'] = (isset($result[$i]["fitment_userid"]) && $result[$i]["fitment_userid"] != null) ? $result[$i]["fitment_userid"] : '';
                                $return_data[$i]['fitment_comments'] = (isset($result[$i]["fitment_comments"]) && $result[$i]["fitment_comments"] != null) ? $result[$i]["fitment_comments"] : '';
                                $return_data[$i]['fitment_createdOn'] = (isset($result[$i]["fitment_createdOn"]) && $result[$i]["fitment_createdOn"] != null) ? date('D, n, M, Y', strtotime($result[$i]["fitment_createdOn"])) : '';
                                $return_data[$i]['fitment_createdOn_time'] = (isset($result[$i]["fitment_createdOn"]) && $result[$i]["fitment_createdOn"] != null) ? date('D, d, M, Y H:i A', strtotime($result[$i]["fitment_createdOn"])) : '';
                                $return_data[$i]['s_serial_number'] = (isset($result[$i]["s_serial_number"]) && $result[$i]["s_serial_number"] != null) ? $result[$i]["s_serial_number"] : '';
                                $return_data[$i]['s_dealer_id'] = (isset($result[$i]["s_dealer_id"]) && $result[$i]["s_dealer_id"] != null) ? $result[$i]["s_dealer_id"] : '';
                                $return_data[$i]['dealer_name'] = (isset($result[$i]["dealer_name"]) && $result[$i]["dealer_name"] != null) ? $result[$i]["dealer_name"] : '';
                                $return_data[$i]['fitment_status'] = (isset($result[$i]["fitment"]) && $result[$i]["fitment"] != null) ? $result[$i]["fitment"] : '';

                                /*if ($return_data[$i]['fitment_imei'] != '' && $return_data[$i]['fitment_status'] == 1) {
                                        $return_data[$i]["fitment_data"] = $this->get_fitment_list(['imei' => $return_data[$i]['fitment_imei']]);
                                } else {
                                        $return_data[$i]["fitment_data"] = [];
                                }*/
                        }
                }

                return $return_data;
        }

        public function get_console_data($params)
        {
                $this->db->select('fitment.*, serial.fitment, serial.s_serial_number, serial.s_serial_number, serial.s_dealer_id, serial.s_used, serial.fitment');
                $this->db->where('fitment_imei', $params['imei']);
                $this->db->from($this->db->table_device_fitment . ' as fitment');
                $this->db->join($this->db->table_serial_no . ' as serial', 'serial.s_imei = fitment.fitment_imei', 'left');

                $data = $this->db->get();
                $data = $data->result_array();

                if ((isset($data[0]['fitment']) && $data[0]['fitment'] == 1) && (isset($data[0]['s_used']) && $data[0]['s_used'] == 1)) {

                        $result = array(
                                "id"           => $data[0]['fitment_id'],
                                "imei"         => $data[0]['fitment_imei'],
                                "created_time" => date('D, d, M, Y H:i A', strtotime($data[0]['fitment_createdOn'])),
                                "latitude"     => trim($data[0]['fitment_latitude']),
                                "longitude"    => trim($data[0]['fitment_longitude']),
                                "data"         => '',
                        );
                } else {
                        $DB2 = $this->load->database('postgre_db', TRUE);
                        $query = "select * from public.tbl_unregistered_device_data where imei = '" . $params['imei'] . "'  ORDER BY id DESC LIMIT 1";
                        $result = (array) $DB2->query($query)->result();

                        if (!empty($result)) {
                                for($i = 0; $i < count($result); $i++) {
                                        $result[$i]->created_time = date('D, d, M, Y H:i A', strtotime($result[$i]->created_time));
                                }
                        }
                }

                return $result;
        }

        public function get_registered_console_data($params)
        {
                $this->db->select('fitment.*, serial.fitment, serial.s_serial_number, serial.s_serial_number, serial.s_dealer_id, serial.s_used, serial.fitment');
                $this->db->where('fitment_imei', $params['imei']);
                $this->db->from($this->db->table_device_fitment . ' as fitment');
                $this->db->join($this->db->table_serial_no . ' as serial', 'serial.s_imei = fitment.fitment_imei', 'left');

                $data = $this->db->get();
                $data = $data->result_array();
                $result = [];

                if ((isset($data[0]['fitment']) && $data[0]['fitment'] == 1) && (isset($data[0]['s_used']) && $data[0]['s_used'] == 1)) {
                        $DB2 = $this->load->database('postgre_db', TRUE);
                        $query = "select * from public.tbl_registered_device_data where imei = '" . $params['imei'] . "'  ORDER BY id DESC LIMIT 1";
                        $result = (array) $DB2->query($query)->result();

                        if (!empty($result)) {
                                for($i = 0; $i < count($result); $i++) {
                                        $result[$i]->created_time = date('D, d, M, Y H:i A', strtotime($result[$i]->created_time));
                                }
                        }
                } else if(!empty($result)) {
                        $result = array(
                                "id"           => $data[0]['fitment_id'],
                                "imei"         => $data[0]['fitment_imei'],
                                "created_time" => date('D, d, M, Y H:i A', strtotime($data[0]['fitment_createdOn'])),
                                "latitude"     => trim($data[0]['fitment_latitude']),
                                "longitude"    => trim($data[0]['fitment_longitude']),
                                "data"         => '',
                        );
                }

                return $result;
        }

        public function is_fitment_completed($params)
        {
                $this->db->select('fitment_id');
                $this->db->where('fitment_imei', $params['fitment_imei']);
                $this->db->from($this->db->table_device_fitment);

                $result = $this->db->get();
                $result = $result->result_array();

                if (count($result) > 0) {
                        return true;
                } else {
                        return false;
                }
        }

        public function save_fitment_data($params)
        {
                $updateRecords = array();
                $updateRecords['fitment'] = 1;
                $this->db->where('s_imei', $params['fitment_imei']);
                $this->db->update($this->db->table_serial_no, $updateRecords);
                return 1;
        }

        /**
         * Save vehicle details
         * 
        */
        public function save_fitment_records($user, $params)
        {
                $user_type = $user['user_type'];
                $user_id   = $user['user_id'];

                $this->db->select('certificate.veh_id');
                $this->db->where('s_imei', $params['fitment_imei']);
                $this->db->from($this->db->table_serial_no . ' as serial');
                $this->db->join($this->db->table_vehicle . ' as certificate', 'certificate.veh_serial_no = serial.s_serial_id', 'left');
                $result = $this->db->get();
                $result = $result->result_array();

                $insertRecords=array();
                $latlng = isset($params['fitment_lat_lng']) ? explode(',', $params['fitment_lat_lng']) : "";
                $insertRecords['fitment_vehicle_id'] = isset($result[0]['veh_id']) ? $result[0]['veh_id'] : "";
                $insertRecords['fitment_imei']      = isset($params['fitment_imei'])?$params['fitment_imei']:"";
                $insertRecords['fitment_latitude']  = isset($latlng[0]) ? $latlng[0] : "";
                $insertRecords['fitment_longitude'] = isset($latlng[1]) ? $latlng[1] : "";
                $insertRecords['fitment_photo']     = isset($params['fitment_picture'])?$params['fitment_picture']:"";
                $insertRecords['fitment_comments']  = isset($params['fitment_comments'])?$params['fitment_comments']:"";
                $insertRecords['fitment_userid']    = isset($params['fitment_user_id'])?$params['fitment_user_id']:"";
                $insertRecords['fitment_createdOn'] = date('Y-m-d H:i:s');

                $this->db->insert($this->db->table_device_fitment,$insertRecords);
                return $this->db->insert_id();
        }
}