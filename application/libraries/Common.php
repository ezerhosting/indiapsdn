<?php
if (! defined ( 'BASEPATH' )) exit ( 'No direct script access allowed' );
class Common {
	protected $ci;

	public function __construct() {
		$this->ci = & get_instance ();
	}
	
	/* this function used to validate image */
	function valid_image($files = null) {
		if (isset ( $files ) && ! empty ( $files )) {
			$allowedExts = array (
					"gif",
					"jpeg",
					"jpg",
					"png",
					"GIF",
					"JPEG",
					"JPG",
					"PNG" 
			);
			$temp = explode ( ".", $files ['name'] );
			$extension = end ( $temp );
			
			if (! in_array ( $extension, $allowedExts )) {
				return 'No';
			}
		}
		return "Yes";
	}
	
	/* this function used to validate image */
	function valid_file($files = null) {
		if (isset ( $files ) && ! empty ( $files )) {
			$allowedExts = array (
					"csv",				
			);
			$temp = explode ( ".", $files ['name'] );
			$extension = end ( $temp );
			
			if (! in_array ( $extension, $allowedExts )) {
				return 'No';
			}
		}
		return "Yes";
	}
	
	/* this function used to upload image */
	function upload_file($file_name, $file_path, $ext = 'csv', $name = '') {
		if (isset ( $file_name ) && ! empty ( $file_name ) && $file_path != "") {
			$this->ci->load->helper ( 'string' );
			//$file_name = $files;
			$config ['upload_path'] = FCPATH . 'media/' . $file_path;
			$config ['allowed_types'] = $ext;
			$config['remove_spaces']=true;
			
			if($file_name != '')
			{
				$config['file_name']=$name;
			}
			
			$this->ci->load->library ( 'upload', $config );
			$this->ci->upload->initialize ( $config );
			if (! $this->ci->upload->do_upload ( $file_name )) {
				$this->ci->upload->display_errors(); exit;
			} else {
				$data = $this->ci->upload->data ();
				return $data ['file_name'];
			}
		}
	}
	
	/* this function used to upload image */
	function upload_image($files = null, $image_path = null) {
		if (isset ( $files ) && ! empty ( $files ) && $image_path != "") {
			$this->ci->load->helper ( 'string' );
			$file_name = $files;
			$config ['upload_path'] = FCPATH . UPLOAD_PATH . $image_path;
			$config ['allowed_types'] = 'gif|jpg|jpeg|png|pdf';
			//$config ['file_name'] = random_string ( 'alnum', 50 );
			$config['max_width']='720';
			$config['max_height']='540';
			$config['encrypt_name']=true;
			$config['remove_spaces']=true;
			//echo $image_path;
			//exit;
			$this->ci->load->library ( 'upload', $config );
			$this->ci->upload->initialize ( $config );
			if (! $this->ci->upload->do_upload ( $file_name )) {
				return '';
			} else {
				$data = $this->ci->upload->data ();
				return $data ['file_name'];
			}
		}
	}
	
	function upload_multiple_images($path, $files)
	{
		$config = array(
            'upload_path'   => $path,
            'allowed_types' => 'jpg|gif|png|jpeg',
            'overwrite'     => 1,    
            'encrypt_name'	=> TRUE,                   
        );

        $this->ci->load->library('upload', $config);

        $images = $response = array();

		foreach ($files['name'] as $key => $image) {
				$_FILES['personal_uploads']['name']= $files['name'][$key];
				$_FILES['personal_uploads']['type']= $files['type'][$key];
				$_FILES['personal_uploads']['tmp_name']= $files['tmp_name'][$key];
				$_FILES['personal_uploads']['error']= $files['error'][$key];
				$_FILES['personal_uploads']['size']= $files['size'][$key];

				$fileName = strtotime(date('Y-m-d H:i:s')) .'_'. ($image);

				

				$config['file_name'] = $fileName;

				$this->ci->upload->initialize($config);

				if ($this->ci->upload->do_upload('personal_uploads')) {
					/*insert need to perform here */
						$data = $this->ci->upload->data();
						$response['image'][] = $data['file_name'];
						$response['data'][] = $data;
				} else {
					 $response['data'][] = $this->ci->upload->display_errors();
				}
		}
        return $response;
	}
	
	/* this function used to unlink images */
	function unlink_image($image_name, $module_image_path) {
		if ($image_name != "" && $module_image_path != "") {
			$image_path = FCPATH . "media/" . $module_image_path . "/" . $image_name;
			
			if (file_exists ( $image_path )) {
				@unlink ( $image_path );
			}
		}
	}
	
	/* this function used to unlink images in delete action */
	function delete_unlink_image($folder_name, $field, $where_in_key, $table, $ids) /*  folder name, select field, wherein key  table , id's*/
	{
		$records = $this->ci->Mydb->get_all_records_where_in ( $field, $table, $where_in_key, $ids );
		
		if (! empty ( $records )) {
			foreach ( $records as $image_name ) {
				
				if ($image_name[$field] != "") {
					$image_path = FCPATH . "media/" . $folder_name . "/" . $image_name[$field];				
					if (file_exists ( $image_path )) {
						@unlink ( $image_path );
					}
				}
			}
		}
		
		return true;
	}
}
/* End of file Common_validation.php */
/* Location: ./application/libraries/Common_validation.php */
