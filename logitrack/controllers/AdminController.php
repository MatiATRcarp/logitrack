<?php
require_once __DIR__ . '/../models/AdminModel.php';

class AdminController {

    private AdminModel $model;

    public function __construct(PDO $pdo) {
        $this->model = new AdminModel($pdo);
    }

    public function getDashboardData(): array {
        return [
            'metricas' => $this->model->getMetricas(),
            'viajes'   => $this->model->getViajesRecientes(),
        ];
    }
}
