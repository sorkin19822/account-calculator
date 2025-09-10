<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Калькулятор распределения средств</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .main-account { background-color: #e3f2fd; }
        .frozen-account { background-color: #f3e5f5; }
        .inactive-account { background-color: #ffebee; }
        .active-account { background-color: #e8f5e8; }
        .calculator-section { border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <h1 class="mb-4">Калькулятор оптимального распределения средств</h1>

    <!-- Форма пополнения -->
    <div class="calculator-section">
        <h3>Пополнение счета</h3>
        <div class="row">
            <div class="col-md-4">
                <label for="depositAmount" class="form-label">Сумма пополнения:</label>
                <input type="number" class="form-control" id="depositAmount" min="0.01" step="0.01" value="100">
            </div>
            <div class="col-md-4">
                <label for="targetAccount" class="form-label">Целевой счет:</label>
                <select class="form-select" id="targetAccount">
                    <option value="">Основной счет (с распределением)</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="button" class="btn btn-primary me-2" onclick="calculateDistribution()">Рассчитать</button>
                <button type="button" class="btn btn-success" onclick="processDeposit()">Выполнить пополнение</button>
            </div>
        </div>
    </div>

    <!-- Таблица счетов -->
    <div class="calculator-section">
        <h3>Счета и расчет распределения</h3>
        <div class="table-responsive">
            <table class="table table-bordered" id="accountsTable">
                <thead>
                <tr>
                    <th>Л/С</th>
                    <th>Абонплата</th>
                    <th>Текущий баланс</th>
                    <th>Зачислено</th>
                    <th>Итоговый баланс</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <!-- Данные загружаются динамически -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- История операций -->
    <div class="calculator-section">
        <h3>История операций</h3>
        <div class="table-responsive">
            <table class="table table-sm" id="transactionsTable">
                <thead>
                <tr>
                    <th>Дата</th>
                    <th>Счет</th>
                    <th>Тип</th>
                    <th>Сумма</th>
                    <th>Баланс до</th>
                    <th>Баланс после</th>
                    <th>Описание</th>
                </tr>
                </thead>
                <tbody>
                <!-- Данные загружаются динамически -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования счета -->
<div class="modal fade" id="editAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактирование счета</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editAccountForm">
                    <input type="hidden" id="editAccountNumber">
                    <div class="mb-3">
                        <label for="editMonthlyFee" class="form-label">Абонплата:</label>
                        <input type="number" class="form-control" id="editMonthlyFee" min="0" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="editBalance" class="form-label">Баланс:</label>
                        <input type="number" class="form-control" id="editBalance" step="0.01">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveAccountChanges()">Сохранить</button>
            </div>
        </div>
    </div>
</div>