<?php

namespace App;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use PDO;
use Smarty;

class Bootstrap
{
    private PDO $pdo;
    private array $config;
    private Smarty $smarty;

    /**
     * Конструктор загружает все зависимости
     */
    public function __construct()
    {
        // Загружаем конфигурацию TODO Заменить на класс
        $this->config = require __DIR__ . '/config.php';

        // Подключаемся к БД
        $this->pdo = Database::getConnection();

        // Настраиваем Smarty
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir(__DIR__ . '/../templates');
        $this->smarty->setCompileDir('/tmp/smarty/templates_c');
        $this->smarty->setCacheDir('/tmp/smarty/cache');
        $this->smarty->caching = false;

        // Глобальные переменные для шаблонов
        $this->smarty->assign('site_name', $this->config['site']['name']);
        $this->smarty->assign('current_path', $_SERVER['REQUEST_URI'] ?? '/');
        $this->smarty->assign('current_year', date('Y'));

        // Регистрируем модификаторы
        $this->registerModifiers();
    }

    /**
     * Регистрирует модификатор формата времени
     */
    private function registerModifiers(): void
    {
        $this->smarty->registerPlugin('modifier', 'date_format_safe', function (string $date, string $format = 'd M, Y'): string {
            $map = ['%d' => 'd', '%m' => 'm', '%Y' => 'Y', '%b' => 'M', '%B' => 'F'];
            $phpFormat = strtr($format, $map);

            try {
                $dt = new \DateTime($date);
            } catch (Exception $e) {
                return $date;
            }

            return $dt->format($phpFormat);
        });
    }
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getSmarty(): Smarty
    {
        return $this->smarty;
    }

    /**
     * Рендерит 404 страницу
     */
    #[NoReturn]
    public function render404(): void
    {
        http_response_code(404);
        $this->smarty->assign('message', 'Page not found');
        $this->smarty->display('404.tpl');
        exit;
    }
}