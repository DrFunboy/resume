<?
$extypeId = getClubStatusAlias("extidTBL", "worksheetTBL");

if ( $_GET["spid"] || $_GET["sidx"] ) {
    if ( $_GET["spid"] ) {
        $query = $modx->newQuery('statusTBL', array(
          "tbl" => 'formTBL'
        ));
        $query->leftJoin('extidTBL', 'extidTBL', "statusTBL.id = extidTBL.extoken AND extidTBL.parent = ".$_GET["spid"]." AND extidTBL.extype = ".$extypeId["id"]);
        $query->select(array(
            'statusTBL.*',
            '`extidTBL`.`id` AS `extidTBL_id`',
            '`extidTBL`.`extended` AS `extidTBL_extended`',
        ));
    }
    
    if ( $_GET["sidx"] ) {
        $query = $modx->newQuery('statusTBL', array(
            "tbl" => 'formTBL'
        ));
        foreach ($_GET["sidx"] as $k => $v){
            $query->sortby($k, $v);
        }
        $query->select(array(
            'statusTBL.*'
        ));
    }
    
    $rows = $query->prepare();
    $answer = array(
        "sql" => $query->toSQL()
    );
    $rows->execute();
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
    $answer["rows"] = $rows;
    echo json_encode($answer);
}

if ( $_POST["spid"] && $_POST["formid"] && $_POST["data"] ) {
    $result = setClubExtId(array(
        'parent' => $_POST["spid"],
        'extoken' => $_POST["formid"],
        'extended' => $_POST["data"],
    ), 'worksheetTBL');
}

