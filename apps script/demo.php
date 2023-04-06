<?
// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);

$mode = $_POST["mode"];
$cfg = getClubConfig("gCalendar", true);
global $exec;
$exec = $cfg['exec'];

if ($mode == "lead") {
    $key = $_POST['key'];
    if (empty($key)) dieJSON("Пустой идентификатор");
    $lead = $modx->getObject('leadTBL', array('key' => $key));
    if (empty($lead)) dieJSON("Кандидат не найден");
    $lead = $lead->toArray();
    if (strtotime($lead["datestart"]) < strtotime("+2 hours") ) $lead["datestart"] =  "";
    $json = $lead;
} else if ($mode == "add") {
    $key = $_POST['key'];
    if (empty($key)) dieJSON("Пустой идентификатор");
    $lead = $modx->getObject('leadTBL', array('key' => $key));
    if (empty($lead)) dieJSON("Кандидат не найден");
    
    $lead = $lead->toArray();
    $d1 = date("Y-m-d\TH:i:s\Z", strtotime($lead["datestart"]));
    $d2 = date("Y-m-d\TH:i:s\Z", strtotime($lead["datestart"]."+ 1 hour"));
    
    $trainer = $modx->getObject("idTrainer", $_POST["trainer"]);
    if ($trainer) $trainerEmail = $trainer->get("email");
    $event =  array(
        'summary' => $lead["name"],
        'start' => $d1,
        'end' => $d2,
        'opts' => array(
            'guests' => implode(',', [$lead["email"], $trainerEmail])
        )
    );
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
    	CURLOPT_URL => $exec,
    	CURLOPT_RETURNTRANSFER => true,
    	CURLOPT_FOLLOWLOCATION => true,
    	CURLOPT_TIMEOUT => 0,
    	CURLOPT_CUSTOMREQUEST => 'POST',
    	CURLOPT_POSTFIELDS => json_encode($event),
    	CURLOPT_HTTPHEADER => array(
    		'Content-Type: application/json'
    	),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    
    $modx->cacheManager->delete('calendarDemoDates');
    $json["ok"] = true;
} else if ($mode == "reserve") {
    $json = json_decode(file_get_contents($exec), true);
} else if ($mode == "del") {
    $lead = $modx->getObject('leadTBL', array(
        'name' => $_POST['name'],
        'datestart' => date("Y-m-d H:i:s", strtotime($_POST["start"]))
    ));
    if (empty($lead)) dieJSON("Кандидат не найден");
    $lead->set("datestart", null);
    $lead->save();
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
    	CURLOPT_URL => $exec,
    	CURLOPT_RETURNTRANSFER => true,
    	CURLOPT_FOLLOWLOCATION => true,
    	CURLOPT_TIMEOUT => 0,
    	CURLOPT_CUSTOMREQUEST => 'POST',
    	CURLOPT_POSTFIELDS => json_encode([
    	    'name' => $_POST['name'],
    	    'start' => date("Y-m-d\TH:i:s\Z", strtotime($_POST["start"])),
            'end' => date("Y-m-d\TH:i:s\Z", strtotime($_POST["start"]."+1 hour")),
            'mode' => 'delete'
	    ]),
    	CURLOPT_HTTPHEADER => array(
    		'Content-Type: application/json'
    	),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $modx->cacheManager->delete('calendarDemoDates');
    $json["ok"] = true;
} else {
    $schtypes = [];
    foreach(getClubStatus("idSchedule") as $v){
        if ($v["extended"]["reservation"]) $schtypes[] = $v["alias"];
    };
    
    $d1 = date("Y-m-d H:i:s", strtotime("+2 hours"));
    $d2 = date("Y-m-d 23:59:59", strtotime("+20 days"));
    
    $q = $modx->newQuery("idSchedule", array(
        "trainer" => $_POST["trainer"],
        "stype:IN" => $schtypes,
    ));
    $q->where(array(
        array( "repeat" => 1 ),
        array( "datestart BETWEEN '$d1' AND '$d2'" )
    ), xPDOQuery::SQL_OR);
    $schList = $modx->getCollection("idSchedule", $q);
    $start = new DateTime($d1);
    $end = new DateTime($d2);
    
    $reserve = json_decode(file_get_contents($exec), true);
    foreach($reserve as $key=>$val){
        $reserve[$key] = ['start' => $key];
    }
    
    $json['reserve'] = $reserve;
    
    $dates = [];
    for (; $start <= $end; $start->modify('+1 day')) {
        foreach($schList as $rawsch) {
            $sch = $rawsch->toArray();
            $dstime = explode(" ", $sch["datestart"])[1];
            $ds = date("l", strtotime($sch["datestart"]));
            if ($ds == $start->format('l')){
                $fulldate = $start->format("Y-m-d $dstime");
                $json['fulldate'][] = $fulldate;
                if ($reserve[$fulldate]) continue;
                
                if ($start == new DateTime($d1) && $d1 >= date("Y-m-d $dstime") ) continue;
                
                $schds = date("H:i", strtotime($sch["datestart"]));
                $dates[$start->format('d.m')]["date"] = $start->format('Y-m-d');
                $dates[$start->format('d.m')]["hours"][ $schds ] = $sch;
            }
        }
    }
    $json['dates'] = $dates;
}