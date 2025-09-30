<?php
$arTypes = DB::getAssocs("SELECT ID, TypeName as Name FROM ai_questions_types ORDER BY ID", DB::NUM);
?>
    <form method='get' class="form-inline">
        Тип вопроса: <?=\UI::drawSelect($arTypes, 'TypeID', ['Selected' => varInt('TypeID'), 'CanBeEmpty' => true, 'Style'=> "width: 200px;"]);?>
        <input type='submit' value='показать' class='button btn btn-primary btn-sm' />
        <input type='hidden' value='<?=$type?>' name='type'/>
        <input type='hidden' value='<?=$act?>' name='act'/>
        <br/><br/>
    </form>

<?php

$form = new cls_form($type, $act);
$form->draw();