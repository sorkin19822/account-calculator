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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let accounts = [];
    let calculatedDistribution = [];

    // Загрузка данных при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        loadAccounts();
        loadTransactions();
    });

    // Загрузка списка счетов
    async function loadAccounts() {
        try {
            const response = await fetch('/api/?action=accounts');
            const result = await response.json();

            if (result.success) {
                accounts = result.data;
                populateTargetAccountSelect();
                renderAccountsTable();
            }
        } catch (error) {
            console.error('Ошибка загрузки счетов:', error);
            alert('Ошибка загрузки данных');
        }
    }

    // Заполнение селекта целевых счетов
    function populateTargetAccountSelect() {
        const select = document.getElementById('targetAccount');
        select.innerHTML = '<option value="">Основной счет (с распределением)</option>';

        accounts.forEach(account => {
            if (!account.is_frozen) {
                const option = document.createElement('option');
                option.value = account.account_number;
                option.textContent = `${account.account_number}${account.is_main ? ' (Основной)' : ''}`;
                select.appendChild(option);
            }
        });
    }

    // Отображение таблицы счетов
    function renderAccountsTable() {
        const tbody = document.querySelector('#accountsTable tbody');
        tbody.innerHTML = '';

        accounts.forEach((account, index) => {
            const distribution = calculatedDistribution.find(d => d.account_number === account.account_number);
            const allocated = distribution ? parseFloat(distribution.allocated) : 0;
            const finalBalance = distribution ? parseFloat(distribution.final_balance) : parseFloat(account.balance);

            const row = document.createElement('tr');
            row.className = getRowClass(account, finalBalance);

            row.innerHTML = `
                    <td>
                        ${account.account_number}
                        ${account.is_main ? '<span class="badge bg-primary">Основной</span>' : ''}
                        ${account.is_frozen ? '<span class="badge bg-secondary">Заморожен</span>' : ''}
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm"
                               value="${account.monthly_fee}"
                               min="0" step="0.01"
                               onchange="updateAccountField('${account.account_number}', 'monthly_fee', this.value)"
                               ${account.is_frozen ? 'disabled' : ''}>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm"
                               value="${account.balance}"
                               step="0.01"
                               onchange="updateAccountField('${account.account_number}', 'balance', this.value)">
                    </td>
                    <td class="fw-bold text-success">${allocated.toFixed(2)}</td>
                    <td class="fw-bold ${finalBalance >= 0 ? 'text-success' : 'text-danger'}">${finalBalance.toFixed(2)}</td>
                    <td>
                        <span class="badge ${getStatusBadgeClass(account, finalBalance)}">
                            ${getStatusText(account, finalBalance)}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary"
                                onclick="editAccount('${account.account_number}')">
                            Редактировать
                        </button>
                    </td>
                `;

            tbody.appendChild(row);
        });
    }

    // Получение класса строки для стилизации
    function getRowClass(account, finalBalance) {
        if (account.is_main) return 'main-account';
        if (account.is_frozen) return 'frozen-account';
        if (finalBalance < 0) return 'inactive-account';
        return 'active-account';
    }

    // Получение класса бейджа статуса
    function getStatusBadgeClass(account, finalBalance) {
        if (account.is_frozen) return 'bg-secondary';
        if (finalBalance >= 0) return 'bg-success';
        return 'bg-danger';
    }

    // Получение текста статуса
    function getStatusText(account, finalBalance) {
        if (account.is_frozen) return 'Заморожен';
        if (finalBalance >= 0) return 'Активен';
        return 'Неактивен';
    }

    // Обновление поля счета
    function updateAccountField(accountNumber, field, value) {
        const accountIndex = accounts.findIndex(a => a.account_number === accountNumber);
        if (accountIndex !== -1) {
            accounts[accountIndex][field] = parseFloat(value) || 0;
            // Пересчитать распределение если оно было выполнено
            if (calculatedDistribution.length > 0) {
                calculateDistribution();
            }
        }
    }

    // Расчет распределения средств
    async function calculateDistribution() {
        const amount = parseFloat(document.getElementById('depositAmount').value);
        const target = document.getElementById('targetAccount').value || null;

        if (!amount || amount <= 0) {
            alert('Введите корректную сумму пополнения');
            return;
        }

        try {
            const url = `/api/?action=calculate&amount=${amount}${target ? `&target=${target}` : ''}`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                calculatedDistribution = result.data;
                renderAccountsTable();
            } else {
                alert('Ошибка расчета: ' + result.message);
            }
        } catch (error) {
            console.error('Ошибка расчета:', error);
            alert('Ошибка выполнения расчета');
        }
    }

    // Выполнение пополнения
    async function processDeposit() {
        const amount = parseFloat(document.getElementById('depositAmount').value);
        const target = document.getElementById('targetAccount').value || null;

        if (!amount || amount <= 0) {
            alert('Введите корректную сумму пополнения');
            return;
        }

        if (!confirm('Выполнить пополнение на сумму ' + amount + '?')) {
            return;
        }

        try {
            const response = await fetch('/api/?action=deposit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: amount,
                    target: target
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('Пополнение успешно выполнено!');
                calculatedDistribution = [];
                loadAccounts();
                loadTransactions();
                document.getElementById('depositAmount').value = '';
            } else {
                alert('Ошибка пополнения: ' + result.message);
            }
        } catch (error) {
            console.error('Ошибка пополнения:', error);
            alert('Ошибка выполнения пополнения');
        }
    }

    // Редактирование счета
    function editAccount(accountNumber) {
        const account = accounts.find(a => a.account_number === accountNumber);
        if (!account) return;

        document.getElementById('editAccountNumber').value = accountNumber;
        document.getElementById('editMonthlyFee').value = account.monthly_fee;
        document.getElementById('editBalance').value = account.balance;

        const modal = new bootstrap.Modal(document.getElementById('editAccountModal'));
        modal.show();
    }

    // Сохранение изменений счета
    async function saveAccountChanges() {
        const accountNumber = document.getElementById('editAccountNumber').value;
        const monthlyFee = parseFloat(document.getElementById('editMonthlyFee').value);
        const balance = parseFloat(document.getElementById('editBalance').value);

        try {
            const response = await fetch('/api/?action=update_account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    account_number: accountNumber,
                    monthly_fee: monthlyFee,
                    balance: balance
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('Данные счета обновлены!');
                const modal = bootstrap.Modal.getInstance(document.getElementById('editAccountModal'));
                modal.hide();
                loadAccounts();
                loadTransactions();
            } else {
                alert('Ошибка обновления: ' + result.message);
            }
        } catch (error) {
            console.error('Ошибка обновления:', error);
            alert('Ошибка сохранения данных');
        }
    }

    // Загрузка истории транзакций
    async function loadTransactions() {
        try {
            const response = await fetch('/api/?action=transactions&limit=20');
            const result = await response.json();

            if (result.success) {
                renderTransactionsTable(result.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки транзакций:', error);
        }
    }

    // Отображение таблицы транзакций
    function renderTransactionsTable(transactions) {
        const tbody = document.querySelector('#transactionsTable tbody');
        tbody.innerHTML = '';

        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            const date = new Date(transaction.created_at).toLocaleString('ru-RU');

            row.innerHTML = `
                    <td>${date}</td>
                    <td>
                        ${transaction.account_number}
                        ${transaction.is_main ? '<span class="badge bg-primary">Основной</span>' : ''}
                    </td>
                    <td>
                        <span class="badge ${getTransactionTypeBadge(transaction.transaction_type)}">
                            ${getTransactionTypeText(transaction.transaction_type)}
                        </span>
                    </td>
                    <td class="${transaction.amount >= 0 ? 'text-success' : 'text-danger'}">
                        ${transaction.amount >= 0 ? '+' : ''}${transaction.amount}
                    </td>
                    <td>${transaction.balance_before}</td>
                    <td>${transaction.balance_after}</td>
                    <td>${transaction.description || ''}</td>
                `;

            tbody.appendChild(row);
        });
    }

    // Получение класса бейджа для типа транзакции
    function getTransactionTypeBadge(type) {
        switch (type) {
            case 'deposit': return 'bg-success';
            case 'fee_deduction': return 'bg-warning';
            case 'distribution': return 'bg-info';
            default: return 'bg-secondary';
        }
    }

    // Получение текста типа транзакции
    function getTransactionTypeText(type) {
        switch (type) {
            case 'deposit': return 'Пополнение';
            case 'fee_deduction': return 'Списание абонплаты';
            case 'distribution': return 'Распределение';
            case 'balance_adjustment': return 'Корректировка';
            default: return 'Другое';
        }
    }
</script>
</body>
</html>