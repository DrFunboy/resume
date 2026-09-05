<?php

/** @var CMain $APPLICATION */
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php $APPLICATION->ShowTitle(); ?></title>

        <!-- Подключаем ресурсы -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.min.js"></script>

        <?php $APPLICATION->ShowHead(); ?>
    </head>
    <body>