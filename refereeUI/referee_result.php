<?php
$eventId = getClubConfig("aaa_event");
$aaaNum = getClubStatusAlias('idExtid', 'aaaNum');
if ($rq[2] == 'save') {
    if ($_POST["oper"] == 'del') die;
    $splist = explode(',', $_POST["sportsmen"]);
    if ($_POST["oper"] == 'edit') unset($_POST["sportsmen"]);
    $_POST['eventTBL'] = $eventId;
    $dbedit = $modx->runSnippet('dbedit', array(
        'data' => $_POST,
    ));
    $json = json_decode($dbedit, true);
    
    $whereArr = array(
        "eventCategoryTBL" => $_POST["eventCategoryTBL"],
        "eventTBL" => $eventId,
        "sportage" => $_POST["sportage"],
        "sportsmen:IN" => $splist
    );
    $query = $modx->newQuery('eventResultTBL');
    $query->where($whereArr);
    $query->sortby('datestart', 'DESC');
    $results = $modx->getCollection('eventResultTBL', $query);
    
    $query = $modx->newQuery('eventMemberTBL');
    $query->where($whereArr);
    $members = $modx->getCollection("eventMemberTBL", $query);
    
    $json['cntm'] = count($members);
    $json['cntr'] = count($results);
    
    foreach ($splist as $spid) {
        foreach ($members as $mem) {
            if ($mem->get("sportsmen") == $spid) {
                foreach ($results as $res) {
                    if ($res->get("sportsmen") == $spid) {
                        $res = $res->toArray();
                        $json['lastRes'] = $res;
                        $mem->set('result', $res["result"]);
                        $mem->set('place', $res["place"]);
                        $mem->save();
                        $break = true;
                        break;
                    }
                }
            }
            if ($break) {
                $break = false;
                break;
            }
        }
    }
} else if ($_POST["eventCategoryTBL"] && $_POST["sportage"] && $_POST["datestart"] && $_POST["type"]) {
    $query = $modx->newQuery('eventResultTBL');
    $query->leftJoin("idSportsmen", "idSportsmen", "idSportsmen.id = eventResultTBL.sportsmen");
    
    $query->leftJoin("eventMemberTBL", "eventMemberTBL", array( 
        "eventMemberTBL.eventTBL = {$eventId}",
        "eventMemberTBL.eventCategoryTBL = {$_POST['eventCategoryTBL']}",
        "eventMemberTBL.sportage = '{$_POST['sportage']}'",
        "eventMemberTBL.sportsmen = eventResultTBL.sportsmen",
    ));
    
    $query->leftJoin("eventTBL", "eventTBL", array( 
        "eventTBL.id = eventMemberTBL.eventTBL",
    ));
    
    $query->leftJoin("invoiceTBLType", "invoiceTBLType", array( 
        "invoiceTBLType.name = 'Номер участника'",
    ));
    
    $query->leftJoin("invoiceTBL", "invoiceTBL", array( 
        "invoiceTBL.sportsmen = eventMemberTBL.sportsmen",
        "invoiceTBL.typeinvoice = invoiceTBLType.id",
        "invoiceTBL.dateinvoice = eventTBL.datestart",
    ));
    
    $query->where(array(
        "eventTBL" => $eventId,
        "eventCategoryTBL" => $_POST["eventCategoryTBL"],
        "sportage" => $_POST['sportage'],
        "datestart" => $_POST["datestart"],
        "type" => $_POST["type"],
    ));
    
    $query->select(array(
        "eventResultTBL.*",
        "eventResultTBL.id as resid",
        "idSportsmen.name as name",
        "eventMemberTBL.team as team",
        "invoiceTBL.info as cup_num",
    ));
    
    $query->groupby("eventResultTBL.id");
    
    $resList = $modx->getCollection('eventResultTBL', $query);
    $json = array("rows" => array());
    foreach($resList as $row){
        $json["rows"][] = $row->toArray();
    }
} else if ($_POST["eventResultTBL"]) {
    $query = $modx->newQuery('eventResultTBL');
    $query->where(array(
        "eventTBL" => $eventId
    ));
    
    $query->leftJoin("eventCategoryTBL", "eventCategoryTBL", "eventCategoryTBL.id = eventResultTBL.eventCategoryTBL");
    
    $query->leftJoin('idStatus', 'RaceRun', "RaceRun.id = eventResultTBL.type");
    $query->leftJoin('idStatus', 'aAge', "aAge.alias = eventResultTBL.sportage AND aAge.tbl = 'aAge'");

    $query->select( array(
        "eventResultTBL.eventCategoryTBL",
        "eventResultTBL.sportage",
        "eventResultTBL.datestart",
        "eventResultTBL.type",
        "eventCategoryTBL.name as ctg_name",
        "RaceRun.name as race",
        "aAge.name as ageName",
    ) );
    
    $query->groupby("eventResultTBL.eventCategoryTBL,eventResultTBL.sportage,eventResultTBL.datestart,eventResultTBL.type");
    
    $query->sortby('datestart', 'ASC');
    
    $stmt = $query->prepare();
    $stmt->execute();
    $json["rows"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $query = $modx->newQuery('eventMemberTBL');
    
    $query->leftJoin("eventTBL", "eventTBL", array( 
        "eventTBL.id = eventMemberTBL.eventTBL",
    ));
    !empty($_POST["datestart"])?  $datestart = $_POST["datestart"] : $datestart = "{$_POST['date']} {$_POST['time']}";
    $query->leftJoin("eventResultTBL", "eventResultTBL", array( 
        "eventResultTBL.eventTBL = {$eventId}",
        "eventResultTBL.eventCategoryTBL = {$_POST['ctg']}",
        "eventResultTBL.sportage = '{$_POST['age']}'",
        "eventResultTBL.datestart = '{$datestart}'",
        "eventResultTBL.type = '{$_POST['race']}'",
        "eventResultTBL.sportsmen = eventMemberTBL.sportsmen"
    ));
    
    $query->leftJoin("invoiceTBLType", "invoiceTBLType", array( 
        "invoiceTBLType.name = 'Номер участника'",
    ));
    
    $query->leftJoin("invoiceTBL", "invoiceTBL", array( 
        "invoiceTBL.sportsmen = eventMemberTBL.sportsmen",
        "invoiceTBL.typeinvoice = invoiceTBLType.id",
        "invoiceTBL.dateinvoice = eventTBL.datestart",
    ));
    
    $query->leftJoin("idSportsmen");
    
    $query->select(array(
        "eventMemberTBL.id as id",
        "invoiceTBL.info as cup_num",
        "idSportsmen.name as name",
        "eventResultTBL.place as place", 
        "eventResultTBL.result as result", 
        "eventResultTBL.line as line", 
        "eventResultTBL.id as resid",
    ));
    $query->where(array(
        "eventCategoryTBL" => $_POST["ctg"],
        "eventTBL" => $eventId,
        "sportage" => $_POST["age"]
    ));
    $query->groupby("eventMemberTBL.id");
    $spList = $modx->getCollection('eventMemberTBL', $query);
    
    foreach($spList as $row){
        $row = $row->toArray();
        $json["rows"][] = $row;
    } 
}