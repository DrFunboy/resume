<?php
$query = $modx->newQuery('payTBL');
$query->leftJoin("invoicePayTBL", "invoicePayTBL", "invoicePayTBL.payTBL = payTBL.id");
$query->leftJoin("invoiceTBL", "invoiceTBL", "invoicePayTBL.invoiceTBL  = invoiceTBL.id");
$query->leftJoin("invoiceTypeTBL", "invoiceTypeTBL", "invoiceTBL.typeinvoice  = invoiceTypeTBL.id");
$query->select(array(
    "payTBL.*",
    'SUM(IFNULL(invoicePayTBL.`sum`, 0)) as sum',
    'SUM(IFNULL(payTBL.`sum`, 0)) as sumpay',
    'invoiceTypeTBL.name AS name',
    'invoiceTypeTBL.id AS type_id',
    'invoiceTypeTBL.menuindex AS menuindex',
));
$query->where(array(
    "datepay:>=" => $_POST["d1"],
    "AND:datepay:<=" => $_POST["d2"]
));
if ($_POST["groupby"]) {
    $query->groupby($_POST["groupby"]);
} else $query->groupby("invoiceTypeTBL.id");

$query->sortby('invoiceTypeTBL.menuindex', 'ASC');
$query->sortby('sum', 'ASC');

$pays = $modx->getCollection("payTBL", $query);
$avans = array();

foreach($pays as $pay){
    $pay = $pay->toArray();
    if ($pay['sumpay'] > $pay['sum']) {
        $month = date("m", strtotime($pay["datepay"]));
        if ( !$avans[$month] ) {
            $avans[$month] = array(
                'name' => 'Аванс',
                'datepay' => $pay["datepay"],
                'sum' => $pay['sumpay'] - $pay['sum'],
                'menuindex' => 99999
            );
            
        } else $avans[$month]['sum'] += $pay['sumpay'] - $pay['sum'];
    }
    $json["rows"][] = $pay;
}

if (count($avans) > 0)  $json["rows"] = array_merge($json["rows"], array_values($avans));
