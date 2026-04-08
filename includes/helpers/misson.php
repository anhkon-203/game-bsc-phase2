<?php
//function getEndpointFromMissionCode($missionCode)
//{
//	$missionMap = [
//		DAILY_LOGIN_CODE => DAILY_LOGIN_URL,
//		MTRADER_LOGIN_CODE => MTRADER_LOGIN_URL,
//		EKYC_COMPLETE_CODE => EKYC_COMPLETE_URL,
//		OPEN_BIDV_CODE => OPEN_BIDV_URL,
//		OPEN_NEW_ACCOUNT_CODE => OPEN_NEW_ACCOUNT_URL,
//		FIRST_DEPOSIT_CODE => FIRST_DEPOSIT_URL,
//		OPEN_BSC_DERIVATIVE_ACCOUNT_CODE => OPEN_BSC_DERIVATIVE_ACCOUNT_URL,
//		OPEN_MARGIN_ACCOUNT_CODE => OPEN_MARGIN_ACCOUNT_URL,
//		USE_BSC_BUY_PACKAGE_CODE => USE_BSC_BUY_PACKAGE_URL,
//		USE_MR90_PACKAGE_CODE => USE_MR90_PACKAGE_URL,
//		TRADE_100M_VND_CODE => TRADE_100M_VND_URL,
//	];
//
//
//
//	$base_url = get_option('game_bsc_tasks');
//
//	if (is_array($base_url) && isset($base_url[$missionCode]['api_url']) && trim($base_url[$missionCode]['api_url']) !== '') {
//		$base_url = trim($base_url[$missionCode]['api_url']);
//		$reward_spins = intval($base_url[$missionCode]['reward_spins'] ?? 0);
//		$is_daily = $base_url[$missionCode]['is_daily'] ?? 0;
//		$end_point = $missionMap[$missionCode];
//
//		// push to array 2 option
//		$array_urls = [
//			'base_url' => $base_url,
//			'end_point' => $end_point,
//			'reward_spins' => $reward_spins,
//			'is_daily' => $is_daily,
//		];
//		return $array_urls;
//	}
//
//	return null;
//}

function getRequiredParamsForMission($missionCode)
{
	$paramsMap = [
		DAILY_LOGIN_CODE => [],
		MTRADER_LOGIN_CODE => ['clientID', 'custodycd', 'loginTime'],
		EKYC_COMPLETE_CODE => ['custodycd'],
		OPEN_BIDV_CODE => ['custodycd'],
		OPEN_NEW_ACCOUNT_CODE => ['custodycd'],
		FIRST_DEPOSIT_CODE => ['custodycd'],
		OPEN_BSC_DERIVATIVE_ACCOUNT_CODE => ['custodycd', 'dStart', 'dEnd'],
		OPEN_MARGIN_ACCOUNT_CODE => ['custodycd', 'dStart', 'dEnd'],
		USE_BSC_BUY_PACKAGE_CODE => ['custodycd', 'dStart', 'dEnd'],
		USE_MR90_PACKAGE_CODE => ['custodycd', 'dStart', 'dEnd'],
		TRADE_100M_VND_CODE => ['custodycd', 'txdate'],
	];
	
	return isset($paramsMap[$missionCode]) ? $paramsMap[$missionCode] : [];
}

/**
 * Kiểm tra tham số có đầy đủ không
 */
function validateParams($missionCode, $params)
{
	$requiredParams = getRequiredParamsForMission($missionCode);
	
	if (empty($requiredParams)) {
		return true;
	}
	
	foreach ($requiredParams as $param) {
		if (!isset($params[$param]) || $params[$param] === '') {
			return false;
		}
	}
	
	return true;
}


function executeMissionApi($missionCode, $params = [])
{
	
	$url = getEndpointFromMissionCode($missionCode);
	$apiBaseUrl = $url['base_url'];
	$endpoint = $url['end_point'];
	if (!$url) {
		return null;
	}
	
	// Kiểm tra tham số
	if (!validateParams($missionCode, $params)) {
		return null;
	}
	
	// Lấy danh sách tham số bắt buộc
	$requiredParams = getRequiredParamsForMission($missionCode);
	
	// Chỉ lấy tham số được phép
	$filteredParams = [];
	foreach ($requiredParams as $param) {
		if (isset($params[$param])) {
			$filteredParams[$param] = $params[$param];
		}
	}
	
	// Xây dựng query string
	$queryString = '';
	if (!empty($filteredParams)) {
		$queryString = '?' . http_build_query($filteredParams);
	}
	
	// Xây dựng full URL
	$url = $apiBaseUrl . $endpoint . $queryString;
	
	// Gọi API
	$response = callApi($url, false, 'POST');
	
	return $response;
}

function callApi($url, $data = false, $method = "GET")
{
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_POSTFIELDS => $data,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		),
	));
	
	$response = curl_exec($curl);
	$error = curl_error($curl); // Lấy lỗi nếu có
	$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Mã HTTP trả về
	curl_close($curl);
	
	if ($error) {
		return null;
	}
	
	// Nếu mã HTTP không phải 2xx, ghi log lỗi
	if ($http_code < 200 || $http_code >= 300) {
		return null;
	}
	
	return json_decode($response);
}
