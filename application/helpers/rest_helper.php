<?php

if (! defined ( 'BASEPATH' ))
	exit ( 'No direct script access allowed' );


/* this function used to  200 response */
if(!function_exists('success_response'))
{
	function success_response()
	{
		return 200;
	}
}

/* this function used to  success response */
if(!function_exists('notfound_response'))
{
	function notfound_response()
	{
		return 404;
	}
}

/* this function used to  success response */
if(!function_exists('something_wrong'))
{
	function something_wrong()
	{
		return 400;
	}
}

/* this function used to chnage timezone */
if(!function_exists('change_time_zone'))
{
	function change_time_zone($time_zone=null)
	{
		$time_zone = ($time_zone == "")? "Asia/Singapore" : $time_zone;
		date_default_timezone_set($time_zone);
	}
}

/* verify supplier using access_token */
if(!function_exists('user_verification'))
{
	function user_verification()
	{
		$headers = getallheaders();
		$token = (isset($headers['Authorization']) ? $headers['Authorization'] : '');

		if ($token != '') {
			$data = JWT::decode($token, JWT_SECRET_KEY, 'HS256');

			if (!empty($data) && isset($data->userId)) {
				$CI =& get_instance();
				$user_data = $CI->commonmodel->fetch('user_id', $data->userId, '*', $CI->db->table_users);

				if(!empty($user_data) && $user_data['user_type'] == 1) {
				// if(!empty($user_data)) {
					return $user_data;
				}
			}
		}

		return []; 
	}
}

/* verify supplier using access_token */
if(!function_exists('technician_verification'))
{
	function technician_verification()
	{
		$headers = getallheaders();
		$token = (isset($headers['Authorization']) ? $headers['Authorization'] : '');

		if ($token != '') {
			$data = JWT::decode($token, JWT_SECRET_KEY, 'HS256');

			if (!empty($data) && isset($data->userId)) {
				$CI =& get_instance();
				$user_data = $CI->commonmodel->fetch('user_id', $data->userId, '*', $CI->db->table_users);

				if(!empty($user_data) && $user_data['user_type'] == 6) {
				// if(!empty($user_data)) {
					return $user_data;
				}
			}
		}

		return []; 
	}
}

if(!function_exists('vehicle_category'))
{
	function vehicle_category($cat_id = 0)
	{
		$vehicle_category = [
			"1" => "TRUCK",
        	"2" => "LORRY",
        	"3" => "OFF ROAD",
        	"4" => "BUS",
        	"5" => "VAN",
        	"6" => "CAR",
        	"7" => "BIKES",
        ];

        if ($cat_id > 0) {
        	return $vehicle_category[$cat_id];
        }

        return $vehicle_category;
    }
}
