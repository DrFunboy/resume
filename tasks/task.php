<?
// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
$mode = $_POST["mode"];
$secret_key = "XXX";
global $modx;
function getByd1d2($ext){
    global $modx;
    $where = [
        "dateend:IS" => NULL,
        "assignedto" => $modx->scrm->user
    ];
    
    if (is_array($ext["_where"]) ) $where = array_merge($where, $ext["_where"]);
    if ($ext["d1"] !== NULL) {
        $d1 = date("Y-m-d H:i:s", strtotime($ext["d1"]));
        $where["duedate:>="] = $d1;
    };
    if ($ext["d2"] !== NULL) {
        $d2 = date("Y-m-d H:i:s", strtotime($ext["d2"]));
        $where["duedate:<="] = $d2;
    }
    
    $q = $modx->newQuery("taskTBL", $where);
    $q->innerJoin("modUserProfile", "modUserProfile", "modUserProfile.id = taskTBL.assignedto");
    
    $q->select(array(
        "taskTBL.*",
        "modUserProfile.fullname AS assignedto_name",
    ));
    
    if (!$where["parent"]) {
        $q->leftJoin("leadTBL", "leadTBL", "leadTBL.id = taskTBL.parent");
        $q->leftJoin("sportsmenTBL", "sportsmenTBL", "sportsmenTBL.id = taskTBL.parent");
        $q->select(array(
            "IF(taskTBL.tbl = 'leadTBL', leadTBL.name, sportsmenTBL.name) AS parent_name",
            "leadTBL.key AS lkey",
            "sportsmenTBL.key AS skey",
        ));
    }
    
    $answ = array(
        "d1" => $d1,
        "d2" => $d2,
    );
    
    $rows = $modx->getCollection("taskTBL", $q);
    foreach($rows as $row){
        $answ["rows"][] = $row->toArray();
    }
    return $answ;
}
if ($rq[2] == "auth") {
    
    $sign_params = array(
        "admin_id"  => $modx->scrm->user,
	    "domain" => $_SERVER['SERVER_NAME'],
	    "created" => gmdate("Y-m-d\TH:i:s\Z", time()),
	    "roles" => join(",", $modx->scrm->userGroups)
    );
    ksort($sign_params);
    $sign_params_query = http_build_query($sign_params);
    
    $sign = hash_hmac('sha256', $sign_params_query, $secret_key); // Получаем хеш-код от строки, используя защищеный ключ приложения. Генерация на основе метода HMAC.
    $curl = curl_init();
    curl_setopt_array($curl, array(
    	CURLOPT_URL => 'https://functions.yandexcloud.net/XXX',
    	CURLOPT_RETURNTRANSFER => true,
    	CURLOPT_ENCODING => '',
    	CURLOPT_TIMEOUT => 0,
    	CURLOPT_CUSTOMREQUEST => 'POST',
    	CURLOPT_POSTFIELDS => json_encode(array_merge($sign_params, array("sign" => $sign))),
    	CURLOPT_HTTPHEADER => array(
    		'Content-Type: application/json'
    	),
    ));
    $json = curl_exec($curl);
    curl_close($curl);
    header('Location: https://t.me/XXX?start='.$sign);
} elseif ($rq[2] == "close") {
    clubLog("task.close", $_POST);
    $body = json_decode(file_get_contents('php://input'), true);
    $sign_params = array(
        "id" => $body["id"],
        "text" => $body["text"],
        "date" => $body["date"]
    );
    ksort($sign_params);
    $sign_params_query = http_build_query($sign_params);
    $sign = hash_hmac('sha256', $sign_params_query, $secret_key); 
    clubLog("task.sign", $sign);
    if ($sign != $body["sign"]) dieJSON("401");
    
    $task = $modx->getObject("taskTBL", $sign_params["id"]);
    if (empty($task)) dieJSON("404");
    
    $closeArr = array(
        "result" => $sign_params["text"],
        "dateend" => $sign_params["date"],
    );
    $log = json_decode($task->get("log"), true);
    $log[date("Y-m-d\TH:i:s")] = array_merge(array("oper"=>"edit"), $closeArr);
    clubLog("task.log", $log);
    $closeArr["log"][] = $log;
    $task->fromArray($closeArr);
    $task->save();
    echo "OK";
} elseif ($mode == "getParents") {
    $name = $_POST['name'];
    
    foreach($modx->getCollection("leadTBL", array(
        "name:LIKE" => "%$name%",
        "sportsmen" => 0
    )) as $lead) {
        $json["rows"][] = array(
            "id" => $lead -> get("id"),
            "name" => $lead -> get("name"),
            "tbl" => "leadTBL",
        );
    };
    
    foreach($modx->getCollection("sportsmenTBL", array(
        "name:LIKE" => "%$name%",
    )) as $sp) {
        $json["rows"][] = array(
            "id" => $sp -> get("id"),
            "name" => $sp -> get("name"),
            "tbl" => "sportsmenTBL",
        );
    };
} elseif ($mode == "getUsers") {
    $roles = [];
    foreach(getClubStatus("idPermission") as $role){
        if ($role["extended"]["crew"] == true) $roles[] = $role["alias"] ;
    };
    
    $group = $modx->getObject("modUserGroup", array( "name:IN" => $roles ));
    $query = $modx->newQuery('modUserProfile');
    $query->innerJoin('modUserGroupMember', 'modUserGroupMember', "modUserProfile.id = modUserGroupMember.member AND user_group = {$group->get('id')}");
    $query->select(array(
        "modUserProfile.id",
        "modUserProfile.fullname"
    ));
    $query->groupBy('modUserProfile.id');
    $users = $modx->getCollection("modUserProfile", $query);
    
    foreach($users as $user){
        $id = $user->get("id");
        $json["rows"][] = array(
            "id" => $id,
            "name" => $user->get("fullname"), 
        );
    }
} elseif ($mode == "d1d2") {
    $json = getByd1d2(array(
        "d1" => $_POST["d1"],
        "d2" => $_POST["d2"],
    ));
} else {
    foreach(getClubStatus('taskTBL_period') as $period){
        $ext = $period["extended"];
        $ext["_where"] = $_POST["_where"];
        $json[] = array_merge($period, getByd1d2($ext));
    }
}