<?php
//require_once __DIR__ .'/../includes/function.php';

$module_path = 'admin.php?page=dashboard-layout';
$sub = "";
if (isset($_GET['sub'])) {
	$sub = trim($_GET['sub']);
}

$module_short_url = str_replace('admin.php?page=','', $module_path);
$mess = '';
$mdlconf = array('title'=>'Dashboard');


if($sub==''){
	include_once __DIR__ .'/dashboard.php';
}

if($sub=='dashboard'){
	include_once __DIR__ .'/dashboard.php';
}
else if($sub=='user-management'){
	include_once __DIR__ .'/user-manage.php';
}
else if($sub=='play-history'){
	
	include_once __DIR__ .'/history-play-session.php';
}
else if($sub=='play-credit-history'){
	include_once __DIR__ .'/play-credit-history.php';
}
else if($sub=='user-detail'){
	
	include_once __DIR__ .'/user-detail.php';
}
else if($sub=='system-log'){
	include_once __DIR__ .'/system-log.php';
}
else if($sub=='voucher-list'){
	include_once __DIR__ .'/voucher-list.php';
}
else if($sub=='gift-detail'){
	include_once __DIR__ .'/gift-detail.php';
}
else if($sub=='test-mission'){
	include_once __DIR__ .'/test-mission-simulate.php';
}
?>



