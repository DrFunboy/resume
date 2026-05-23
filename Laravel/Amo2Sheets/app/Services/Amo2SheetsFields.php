<?php

namespace App\Services;

class Amo2SheetsFields
{
    /**
     * Перечень полей доступных для экспорта
     */
    public static array $fields = [
        'deal' => [
            'id' => ['name' => 'ID сделки'],
            'name' => ['name' => 'Название сделки'],
            'sale' => ['name' => 'Бюджет сделки'],
            'responsible_user_id' => ['name' => 'ID ответственного'],
            'responsible_user_name' => ['name' => 'Имя ответственного'],
            'group_id' => ['name' => 'ID группы'],
            'status_id' => ['name' => 'ID статуса'],
            'status_name' => ['name' => 'Имя статуса'],
            'pipeline_id' => ['name' => 'ID воронки'],
            'pipeline_name' => ['name' => 'Имя воронки'],
            'created_by' => ['name' => 'ID создателя'],
            'created_by_name' => ['name' => 'Имя создателя'],
            'updated_by' => ['name' => 'ID редактора'],
            'updated_by_name' => ['name' => 'Имя редактора'],
            'closed_at' => ['name' => 'Дата завершения', 'format' => 'date'],
            'created_at' => ['name' => 'Дата создания', 'format' => 'date'],
            'updated_at' => ['name' => 'Дата обновления', 'format' => 'date'],
            'closest_task_at' => ['name' => 'Дата последней задачи', 'format' => 'date'],
            'score' => ['name' => 'Рейтинг сделки'],
        ],
        'contact' => [
            'id' => ['name' => 'ID контакта'],
            'name' => ['name' => 'Название контакта'],
            'responsible_user_id' => ['name' => 'ID ответственного'],
            'responsible_user_id_name' => ['name' => 'Имя ответственного'],
            'group_id' => ['name' => 'ID группы'],
            'created_by' => ['name' => 'ID создателя'],
            'created_by_name' => ['name' => 'Имя создателя'],
            'updated_by' => ['name' => 'ID обновителя'],
            'updated_by_name' => ['name' => 'Имя обновителя'],
            'created_at' => ['name' => 'Дата создания', 'format' => 'date'],
            'updated_at' => ['name' => 'Дата обновления', 'format' => 'date'],
            'closest_task_at' => ['name' => 'Дата последней задачи', 'format' => 'date'],
        ],
        'other' => [
            'empty' => ['name' => 'Пустое поле'],
        ]
    ];
}
