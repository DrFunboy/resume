<?php
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);

$secret_key = "xxx";
$service_key = "xxx";

if ( empty($_POST) ) $_POST = json_decode(file_get_contents('php://input', true), true);

if ( !empty($_POST['vk_user_id']) ) {
	$outsign = $_POST['sign'];
	foreach ($_POST as $name => $value) {
      if (strpos($name, 'vk_') === 0)  $sign_params[$name] = $value;
  }
} else {
	$outsign = $_GET['sign'];
	foreach ($_GET as $name => $value) {
      if (strpos($name, 'vk_') === 0)  $sign_params[$name] = $value;
  }
}

$role = $sign_params['vk_viewer_group_role'];
$allowed = ($role == 'admin' || $role == 'moder'); // Нужно ли выдавать права пользователю с правами редактора?  || $role == 'editor'
ksort($sign_params); // Сортируем массив по ключам
$sign_params_query = http_build_query($sign_params); // Формируем строку вида "param_name1=value&param_name2=value"
$sign = rtrim(strtr(base64_encode(hash_hmac('sha256', $sign_params_query, $secret_key, true)), '+/', '-_'), '='); // Получаем хеш-код от строки, используя защищеный ключ приложения. Генерация на основе метода HMAC.
$status = $sign === $outsign; // Сравниваем полученную подпись со значением параметра 'sign'

if (!$status) die('wrong sign');
$json = array('is_admin' => false);
if ($allowed) {
    $json['is_admin'] = true;
}

$cfg = json_decode( file_get_contents( __DIR__."/cfg/".$sign_params['vk_group_id']."config.json"), true );
if ( !empty($cfg) ) {
  $json['config'] = $cfg['config'];
  $json['domain'] = $cfg['domain'];
}

if ($_POST["ping"] == true && !empty($_POST['domain']) && $allowed){
    $curl = curl_init();
    curl_setopt_array($curl, array(
    	CURLOPT_URL => $_POST['domain'].'/eform',
    	CURLOPT_RETURNTRANSFER => true,
    	CURLOPT_ENCODING => '',
    	CURLOPT_TIMEOUT => 0,
    	CURLOPT_FOLLOWLOCATION => true,
    ));
    $response =  json_decode(curl_exec($curl), true);
    curl_close($curl);
    if ($response) {
    	file_put_contents(__DIR__."/cfg/".$sign_params['vk_group_id']."config.json", json_encode(array('domain' => $_POST['domain'])));
        $json['saved'] = true;
        $json['domain'] = $_POST['domain'];
    }
	echo json_encode($json);
} else if (!empty($_POST['config']) && $allowed && !empty($cfg)){
    file_put_contents(__DIR__."/cfg/".$sign_params['vk_group_id']."config.json", json_encode( array_merge( 
        $cfg,
        array(
            'config' => $_POST['config']
        )
    )));
    $json['saved'] = true;
    echo json_encode($json);
} else {
	echo str_replace(array(
      "[[backData]]"	
    ), array(
      json_encode($json),
    ), file_get_contents(__DIR__."/tmpl.html"));
}