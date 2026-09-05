<?php

return array (
    'cache_flags' =>
        array (
            'value' =>
                array (
                    'config_options' => 3600.0,
                ),
            'readonly' => false,
        ),
    'cookies' =>
        array (
            'value' =>
                array (
                    'secure' => false,
                    'http_only' => true,
                ),
            'readonly' => false,
        ),
    'exception_handling' =>
        array (
            'value' =>
                array (
                    'debug' => true,
                    'handled_errors_types' => 4437,
                    'exception_errors_types' => 4437,
                    'ignore_silence' => false,
                    'assertion_throws_exception' => true,
                    'assertion_error_type' => 256,
                    'log' => NULL,
                ),
            'readonly' => false,
        ),
    'connections' =>
        array (
            'value' =>
                array (
                    'default' =>
                        array (
                            'host' => 'callback_popup_mysql',
                            'database' => 'mysqldb',
                            'login' => 'mysql',
                            'password' => 'mysql',
                            'options' => 2.0,
                            'className' => '\\Bitrix\\Main\\DB\\MysqliConnection',
                        ),
                ),
            'readonly' => true,
        ),
    'crypto' =>
        array (
            'value' =>
                array (
                    'crypto_key' => '7af821bf957ca34c2e2efb721f06d41d',
                ),
            'readonly' => true,
        ),
    'messenger' =>
        array (
            'value' =>
                array (
                    'run_mode' => NULL,
                    'shuffle' => true,
                    'brokers' =>
                        array (
                            'default' =>
                                array (
                                    'type' => 'db',
                                    'params' =>
                                        array (
                                            'table' => 'Bitrix\\Main\\Messenger\\Internals\\Storage\\Db\\Model\\MessengerMessageTable',
                                        ),
                                ),
                        ),
                    'queues' =>
                        array (
                        ),
                ),
            'readonly' => true,
        ),
);
