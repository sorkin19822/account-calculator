<?php

namespace AccountCalculator\Services;

use AccountCalculator\Models\Account;
use AccountCalculator\Models\Transaction;

class CalculatorService {
    private $accountModel;
    private $transactionModel;

    public function __construct() {
        $this->accountModel = new Account();
        $this->transactionModel = new Transaction();
    }

    /**
     * Расчет распределения средств при пополнении
     * @param array $accounts - массив счетов с текущими данными
     * @param float $depositAmount - сумма пополнения
     * @param string $targetAccount - целевой счет для пополнения (null для основного)
     * @return array - результат расчета
     */
    public function calculateDistribution($accounts, $depositAmount, $targetAccount = null) {
        $result = [];
        $remainingAmount = $depositAmount;

        // Если указан конкретный счет для пополнения
        if ($targetAccount !== null) {
            foreach ($accounts as $account) {
                $allocated = 0;
                $finalBalance = $account['balance'];

                if ($account['account_number'] === $targetAccount && !$account['is_frozen']) {
                    $allocated = $depositAmount;
                    $finalBalance = $account['balance'] + $depositAmount;
                }

                $result[] = [
                    'account_number' => $account['account_number'],
                    'monthly_fee' => $account['monthly_fee'],
                    'current_balance' => $account['balance'],
                    'allocated' => $allocated,
                    'final_balance' => $finalBalance,
                    'is_main' => $account['is_main'],
                    'is_frozen' => $account['is_frozen'],
                    'is_active' => $finalBalance >= 0
                ];
            }
            return $result;
        }

        // Распределение при пополнении основного счета
        // Сортируем счета: сначала основной, потом дополнительные (не замороженные)
        usort($accounts, function($a, $b) {
            if ($a['is_main'] != $b['is_main']) {
                return $b['is_main'] - $a['is_main']; // Основной счет первым
            }
            return strcmp($a['account_number'], $b['account_number']);
        });

        foreach ($accounts as $account) {
            $allocated = 0;
            $finalBalance = $account['balance'];

            // Пропускаем замороженные счета
            if ($account['is_frozen']) {
                $result[] = [
                    'account_number' => $account['account_number'],
                    'monthly_fee' => $account['monthly_fee'],
                    'current_balance' => $account['balance'],
                    'allocated' => 0,
                    'final_balance' => $account['balance'],
                    'is_main' => $account['is_main'],
                    'is_frozen' => $account['is_frozen'],
                    'is_active' => $account['balance'] >= 0
                ];
                continue;
            }

            // Рассчитываем необходимую сумму для покрытия отрицательного баланса
            if ($account['balance'] < 0 && $remainingAmount > 0) {
                $needed = abs($account['balance']);
                $allocated = min($needed, $remainingAmount);
                $remainingAmount -= $allocated;
                $finalBalance = $account['balance'] + $allocated;
            }

            // Если остались средства и баланс уже неотрицательный
            if ($remainingAmount > 0 && $finalBalance >= 0) {
                // Для основного счета добавляем все оставшиеся средства
                if ($account['is_main']) {
                    $allocated += $remainingAmount;
                    $finalBalance += $remainingAmount;
                    $remainingAmount = 0;
                }
            }

            $result[] = [
                'account_number' => $account['account_number'],
                'monthly_fee' => $account['monthly_fee'],
                'current_balance' => $account['balance'],
                'allocated' => $allocated,
                'final_balance' => $finalBalance,
                'is_main' => $account['is_main'],
                'is_frozen' => $account['is_frozen'],
                'is_active' => $finalBalance >= 0
            ];
        }

        return $result;
    }

    /**
     * Применение пополнения с сохранением в БД
     */
    public function processDeposit($depositAmount, $targetAccount = null) {
        try {
            $accounts = $this->accountModel->getAllAccounts();
            $distribution = $this->calculateDistribution($accounts, $depositAmount, $targetAccount);

            // Обновляем балансы в БД и записываем транзакции
            foreach ($distribution as $accountData) {
                if ($accountData['allocated'] > 0) {
                    $oldBalance = $accountData['current_balance'];
                    $newBalance = $accountData['final_balance'];

                    // Обновляем баланс
                    $this->accountModel->updateBalance($accountData['account_number'], $newBalance);

                    // Записываем транзакцию
                    $description = $targetAccount ?
                        "Прямое пополнение счета" :
                        "Распределение при пополнении основного счета";

                    $this->transactionModel->addTransaction(
                        $accountData['account_number'],
                        'deposit',
                        $accountData['allocated'],
                        $oldBalance,
                        $newBalance,
                        $description
                    );
                }
            }

            return [
                'success' => true,
                'distribution' => $distribution,
                'message' => 'Пополнение успешно выполнено'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка при обработке пополнения: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Обновление данных счета
     */
    public function updateAccountData($accountNumber, $monthlyFee, $balance) {
        try {
            $oldAccount = $this->accountModel->getAccountByNumber($accountNumber);
            if (!$oldAccount) {
                throw new Exception('Счет не найден');
            }

            $this->accountModel->updateAccount($accountNumber, $monthlyFee, $balance);

            // Записываем транзакцию если изменился баланс
            if ($oldAccount['balance'] != $balance) {
                $this->transactionModel->addTransaction(
                    $accountNumber,
                    'balance_adjustment',
                    $balance - $oldAccount['balance'],
                    $oldAccount['balance'],
                    $balance,
                    'Ручная корректировка баланса'
                );
            }

            return ['success' => true, 'message' => 'Данные счета обновлены'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка обновления: ' . $e->getMessage()];
        }
    }
}