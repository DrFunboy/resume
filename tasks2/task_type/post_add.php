<?php
$_REQUEST['TaskPosts'] = implode(',', employees::$taskTypePosts);
(new cls_form($type, $act))->draw();