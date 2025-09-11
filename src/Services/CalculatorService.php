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
        // Инициализируем результат - все счета с нулевым распределением
        foreach ($accounts as $account) {
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
        }

        // Находим основной счет
        $mainAccountIndex = null;
        for ($i = 0; $i < count($result); $i++) {
            if ($result[$i]['is_main']) {
                $mainAccountIndex = $i;
                break;
            }
        }

        if ($mainAccountIndex === null) {
            return $result; // Нет основного счета
        }

        // Шаг 1: Покрываем основной счет до уровня абонплаты
        $mainAccount = &$result[$mainAccountIndex];
        $targetBalance = $mainAccount['monthly_fee']; // Уровень абонплаты
        $currentBalance = $mainAccount['current_balance'];

        if ($currentBalance < $targetBalance && $remainingAmount > 0) {
            $needed = $targetBalance - $currentBalance;
            $toAllocate = min($needed, $remainingAmount);

            $mainAccount['allocated'] = $toAllocate;
            $mainAccount['final_balance'] = $currentBalance + $toAllocate;
            $remainingAmount -= $toAllocate;
        }

        // Шаг 2: Распределяем остаток по дополнительным счетам с отрицательным балансом
        // Сортируем дополнительные счета по балансу (самые отрицательные первыми)
        $additionalAccounts = [];
        for ($i = 0; $i < count($result); $i++) {
            if (!$result[$i]['is_main'] && !$result[$i]['is_frozen'] && $result[$i]['current_balance'] < 0) {
                $additionalAccounts[] = ['index' => $i, 'balance' => $result[$i]['current_balance']];
            }
        }

        // Сортируем по балансу (самые отрицательные первыми)
        usort($additionalAccounts, function($a, $b) {
            return $a['balance'] - $b['balance'];
        });

        // Распределяем по отрицательным счетам
        foreach ($additionalAccounts as $accountInfo) {
            if ($remainingAmount <= 0) break;

            $index = $accountInfo['index'];
            $account = &$result[$index];
            $currentBalance = $account['current_balance'];

            // Покрываем отрицательный баланс
            $needed = abs($currentBalance);
            $toAllocate = min($needed, $remainingAmount);

            $account['allocated'] = $toAllocate;
            $account['final_balance'] = $currentBalance + $toAllocate;
            $account['is_active'] = $account['final_balance'] >= 0;
            $remainingAmount -= $toAllocate;
        }

        // Шаг 3: Если остались средства, добавляем их на основной счет
        if ($remainingAmount > 0) {
            $mainAccount['allocated'] += $remainingAmount;
            $mainAccount['final_balance'] += $remainingAmount;
            $remainingAmount = 0;
        }

        // Обновляем статус активности для всех счетов
        for ($i = 0; $i < count($result); $i++) {
            $result[$i]['is_active'] = $result[$i]['final_balance'] >= 0;
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