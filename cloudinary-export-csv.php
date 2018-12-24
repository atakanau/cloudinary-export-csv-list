<?php 
/* 

Download list of all stored resources on Cloudinary as CSV file.

https://atakanau.blogspot.com/2018/12/cloudinary-yuklu-tum-dosyalar-listeleme.html

*/
	function src_read($output,$next_cursor='',$page=0){
		$result = new stdClass();
		$output_new = '';
		$result -> output = $output;
		$result -> next_cursor = false;
		$result -> page = $page;
		$handle = curl_init();

/* 	Replace with your own parameters : 
		API_KEY
		API_SECRET
		CLOUD_NAME
		 */
		$url = 'https://API_KEY:API_SECRET@api.cloudinary.com/v1_1/CLOUD_NAME/resources/image'
			.'?max_results=500'
			.( $next_cursor == '' ? '' : '&next_cursor='.$next_cursor )
			;
		curl_setopt($handle, CURLOPT_URL, $url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		$readed = curl_exec($handle);
		curl_close($handle);

		$data=json_decode($readed, true);

		if( isset($data['next_cursor']) )
			$result -> next_cursor = $data['next_cursor'];
		
		if($output=='')
			foreach($data['resources'] as $rsc){
				foreach ($rsc as $key => $value){
					$output_new .= "$key\t";
				}
				$output_new .= "\r\n";
				break;
			}
		
		foreach($data['resources'] as $rsc){
			foreach ($rsc as $key => $value){
				$output_new .= "$value\t";
			}
			$output_new .= "\r\n";
		}
		$result -> output .= $output_new;
		$result -> page += 1;
		
		return $result;
	}

	$output = '';
	$r = src_read($output);
	do{
		$r = src_read($r -> output ,$r -> next_cursor ,$r -> page );
	}while($r -> next_cursor);

	header("Content-type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"cloudinary-all-resources-list.csv\"");
	echo $r -> output;

?>
