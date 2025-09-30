<?php
$promptVarsHint = '';
foreach (AI::CONTEXT_REPLACE_VARS as $name => $label) {
    $promptVarsHint .= "<div>@$name; - $label</div>";
}
$form = new cls_form($type, $act);
$form->draw();