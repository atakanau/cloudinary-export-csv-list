<?php 
/* 

Download list of all stored resources on Cloudinary as CSV file.

https://atakanau.blogspot.com/2018/12/cloudinary-yuklu-tum-dosyalar-listeleme.html

*/
	function src_read($resource_type,$next_cursor=false,$first=false){
		$result = new stdClass();
		$result -> output = '';
		$result -> first = $first;
		$output_new = '';
		$handle = curl_init();

/* 	Replace with your own parameters : 
		API_KEY
		API_SECRET
		CLOUD_NAME
		 */
		$url = 'https://API_KEY:API_SECRET@api.cloudinary.com/v1_1/CLOUD_NAME/resources/'.$resource_type
			.'?max_results=500'
			.( $next_cursor ? '&next_cursor='.$next_cursor : '' )
			;
		curl_setopt($handle, CURLOPT_URL, $url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		$readed = curl_exec($handle);
		curl_close($handle);
		$data=json_decode($readed, true);

		$result -> next_cursor = isset($data['next_cursor']) ? $data['next_cursor'] : false;
		
		if( isset($data['resources']) && count($data['resources']) ){
			if( $result -> first ){
				$result -> first = false;
				foreach($data['resources'] as $rsc){
					foreach ($rsc as $key => $value){
						$output_new .= "$key\t";
					}
					$output_new .= "\r\n";
					break;
				}
			}
		
			foreach($data['resources'] as $rsc){
				foreach ($rsc as $key => $value){
					$output_new .= "$value\t";
				}
				$output_new .= "\r\n";
			}
			$result -> output = $output_new;
		}
		
		return $result;
	}

	$output = '';
	$first = true;
	$next_cursor = false;
	$resource_types = ["raw","image","video"];
	foreach($resource_types as $resource_type){
		do{
			$r = src_read($resource_type,$next_cursor,$first);
			$output .= $r -> output;
			$first = $r-> first;
		}while($next_cursor = $r -> next_cursor);
	}
	header("Content-type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"cloudinary-all-resources-list.csv\"");
	echo $output;

?>
