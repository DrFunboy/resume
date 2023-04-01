<?
$w->leftJoin('statusTBL', 'statusTBL', "statusTBL.id = idExpense.status AND statusTBL.tbl='idExpense' AND statusTBL.published=1");
$select[] = "statusTBL.name as name";
$select[] = "statusTBL.menuindex as menuindex";
$select[] = 'SUM(IFNULL(idExpense.`sum`, 0)) as sum';
$where["date:>="] = $d1;
$where["AND:date:<="] = $d2;

if ($_POST["groupby"]) {
    $groupby[] = $_POST["groupby"];
} else $groupby[] = "idExpense.id";

$w->sortby('statusTBL.menuindex', 'ASC');
$w->sortby('sum', 'ASC');