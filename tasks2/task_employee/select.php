<?php
global $auth;

$minDateEnd = $_REQUEST['minDateEnd'];
$maxDateEnd = $_REQUEST['maxDateEnd'];

if ($minDateEnd && $maxDateEnd) {
    $minDateEnd = varInt('minDateEnd');
    $maxDateEnd = varInt('maxDateEnd');
    $_REQUEST['minDateEnd'] = date('Y-m-d', $minDateEnd);
    $_REQUEST['maxDateEnd'] = date('Y-m-d', $maxDateEnd);
}

$userID = varInt('userID');
$userSQL = $auth->getAccessUsersSQL();
$usersList = DB::getAssocs("
    SELECT su.UserID, su.Fullname 
    FROM sys_users su 
    INNER JOIN employee em on em.UserID = su.UserID
    WHERE Active = 1 AND {$userSQL} ORDER BY Fullname
");

$typeID = varInt('typeID');
$typeList = DB::getAssocs("
    SELECT TaskTypeID, TaskTypeName
    FROM employee_task_type
    WHERE Active = 1
");

$resultID = varInt('resultID');
$resultList = DB::getAssocs("
    SELECT ResultID, ResultName
    FROM employee_task_result
");
?>
<form class="">
    <div class="form-group form-inline">
        <div class="mb-2">
            <?=\UI::drawSelect($usersList, 'userID', ['Selected' => $userID, 'CanBeEmpty' => "Сотрудник"]);?>
            <?=\UI::drawSelect($typeList, 'typeID', ['Selected' => $typeID, 'CanBeEmpty' => "Тип"]);?>
            <?=\UI::drawSelect($resultList, 'resultID', ['Selected' => $resultID, 'CanBeEmpty' => "Статус"]);?>
        </div>
        <div class="mb-2">
            Дедлайн с <?=genCalendar('minDateEnd', $minDateEnd);?>
            по <?=genCalendar('maxDateEnd', $maxDateEnd);?>
        </div>
        <input type='submit' value='Показать' class='button btn btn-primary btn-sm' />
    </div>
</form>
<?php
$form = new cls_form($type, $act);
$form->draw();

