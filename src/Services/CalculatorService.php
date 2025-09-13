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
        $remainingAmount = (float)$depositAmount;

        // Если указан конкретный счет для пополнения
        if ($targetAccount !== null) {
            foreach ($accounts as $account) {
                $allocated = 0;
                $finalBalance = (float)$account['balance'];

                if ($account['account_number'] === $targetAccount) {
                    $allocated = $remainingAmount;
                    $finalBalance = (float)$account['balance'] + $remainingAmount;
                }

                $result[] = [
                    'account_number' => $account['account_number'],
                    'monthly_fee' => (float)$account['monthly_fee'],
                    'current_balance' => (float)$account['balance'],
                    'allocated' => $allocated,
                    'final_balance' => $finalBalance,
                    'is_main' => (bool)$account['is_main'],
                    'is_frozen' => (bool)$account['is_frozen'],
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
                'monthly_fee' => (float)$account['monthly_fee'],
                'current_balance' => (float)$account['balance'],
                'allocated' => 0,
                'final_balance' => (float)$account['balance'],
                'is_main' => (bool)$account['is_main'],
                'is_frozen' => (bool)$account['is_frozen'],
                'is_active' => (float)$account['balance'] >= 0
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

        // Новая логика распределения
        $this->distributeAmountProprtionally($result, $remainingAmount, $mainAccountIndex);

        return $result;
    }

    /**
     * Итеративное распределение суммы согласно алгоритму
     * @param array &$result - массив счетов для обновления
     * @param float $amount - сумма для распределения
     * @param int $mainAccountIndex - индекс основного счета
     */
    private function distributeAmountProprtionally(&$result, $amount, $mainAccountIndex) {
        if ($amount <= 0) return;

        $remainingAmount = $amount;

        // Итерация 0: Погашение долгов - сначала наименьшие
        $remainingAmount = $this->debtPayoffIteration($result, $remainingAmount);

        // Итерация 1: Доводим все счета до уровня абонплаты
        $remainingAmount = $this->firstIteration($result, $remainingAmount);

        // Итерации 2+: Добавляем по абонплате пропорционально, пока есть средства
        while ($remainingAmount > 0) {
            $amountBefore = $remainingAmount;
            $remainingAmount = $this->nextIteration($result, $remainingAmount);

            // Если сумма не изменилась, значит нет активных счетов для распределения
            // Добавляем остаток на основной счет
            if ($amountBefore == $remainingAmount) {
                $result[$mainAccountIndex]['allocated'] += $remainingAmount;
                $result[$mainAccountIndex]['final_balance'] += $remainingAmount;
                break;
            }
        }

        // Обновляем статус активности для всех счетов
        for ($i = 0; $i < count($result); $i++) {
            $result[$i]['is_active'] = $result[$i]['final_balance'] >= 0;
        }
    }

    /**
     * Итерация 0: Погашение долгов - сначала наименьшие
     */
    private function debtPayoffIteration(&$result, $amount) {
        if ($amount <= 0) return $amount;

        // Собираем информацию о счетах с долгами (исключая замороженные)
        $debtAccounts = [];

        for ($i = 0; $i < count($result); $i++) {
            if ($result[$i]['is_frozen']) continue;

            $currentBalance = $result[$i]['final_balance'];
            if ($currentBalance < 0) {
                $debt = abs($currentBalance);
                $debtAccounts[] = [
                    'index' => $i,
                    'debt' => $debt
                ];
            }
        }

        // Если нет долгов, возвращаем всю сумму
        if (empty($debtAccounts)) {
            return $amount;
        }

        // Сортируем долги по возрастанию (наименьшие сначала)
        usort($debtAccounts, function($a, $b) {
            return $a['debt'] <=> $b['debt'];
        });

        $remainingAmount = $amount;

        // Погашаем долги по порядку - сначала наименьшие
        foreach ($debtAccounts as $debtInfo) {
            if ($remainingAmount <= 0) break;

            $index = $debtInfo['index'];
            $debt = $debtInfo['debt'];

            if ($remainingAmount >= $debt) {
                // Хватает средств - погашаем долг полностью
                $result[$index]['allocated'] += $debt;
                $result[$index]['final_balance'] += $debt; // Баланс станет 0
                $remainingAmount -= $debt;
            } else {
                // Не хватает средств - погашаем частично
                $result[$index]['allocated'] += $remainingAmount;
                $result[$index]['final_balance'] += $remainingAmount;
                $remainingAmount = 0;
                break;
            }
        }

        return $remainingAmount;
    }

    /**
     * Первая итерация: доводим балансы до уровня абонплаты
     */
    private function firstIteration(&$result, $amount) {
        $remainingAmount = $amount;

        for ($i = 0; $i < count($result); $i++) {
            if ($result[$i]['is_frozen'] || $remainingAmount <= 0) continue;

            $currentBalance = $result[$i]['final_balance'];
            $monthlyFee = $result[$i]['monthly_fee'];

            // Если баланс меньше абонплаты, доводим до уровня абонплаты
            if ($currentBalance < $monthlyFee) {
                $needed = $monthlyFee - $currentBalance;
                $toAllocate = min($needed, $remainingAmount);

                $result[$i]['allocated'] += $toAllocate;
                $result[$i]['final_balance'] += $toAllocate;
                $remainingAmount -= $toAllocate;
            }
        }

        return $remainingAmount;
    }

    /**
     * Следующие итерации: добавляем по абонплате пропорционально
     */
    private function nextIteration(&$result, $amount) {
        if ($amount <= 0) return $amount;

        // Собираем активные счета (не замороженные, с абонплатой > 0)
        // Основной счет идет первым
        $activeAccounts = [];
        $mainAccountIndex = null;

        // Сначала находим основной счет
        for ($i = 0; $i < count($result); $i++) {
            if ($result[$i]['is_main'] && !$result[$i]['is_frozen'] && $result[$i]['monthly_fee'] > 0) {
                $activeAccounts[] = [
                    'index' => $i,
                    'monthly_fee' => $result[$i]['monthly_fee']
                ];
                $mainAccountIndex = $i;
                break;
            }
        }

        // Затем добавляем остальные активные счета
        for ($i = 0; $i < count($result); $i++) {
            if (!$result[$i]['is_main'] && !$result[$i]['is_frozen'] && $result[$i]['monthly_fee'] > 0) {
                $activeAccounts[] = [
                    'index' => $i,
                    'monthly_fee' => $result[$i]['monthly_fee']
                ];
            }
        }

        if (empty($activeAccounts)) {
            return $amount; // Нет активных счетов
        }

        // Вычисляем общую сумму абонплат
        $totalMonthlyFee = 0;
        foreach ($activeAccounts as $account) {
            $totalMonthlyFee += $account['monthly_fee'];
        }

        // Проверяем, хватает ли средств на полный цикл абонплат
        if ($amount >= $totalMonthlyFee) {
            // Хватает - добавляем по абонплате каждому
            foreach ($activeAccounts as $accountInfo) {
                $index = $accountInfo['index'];
                $monthlyFee = $accountInfo['monthly_fee'];

                $result[$index]['allocated'] += $monthlyFee;
                $result[$index]['final_balance'] += $monthlyFee;
                $amount -= $monthlyFee;
            }
        } else {
            // Не хватает - распределяем по приоритету
            $remainingAmount = $amount;

            foreach ($activeAccounts as $accountInfo) {
                if ($remainingAmount <= 0) break;

                $index = $accountInfo['index'];
                $monthlyFee = $accountInfo['monthly_fee'];

                if ($remainingAmount >= $monthlyFee) {
                    // Хватает на полную абонплату
                    $result[$index]['allocated'] += $monthlyFee;
                    $result[$index]['final_balance'] += $monthlyFee;
                    $remainingAmount -= $monthlyFee;
                } else {
                    // Не хватает на полную абонплату - отдаем весь остаток
                    $result[$index]['allocated'] += $remainingAmount;
                    $result[$index]['final_balance'] += $remainingAmount;
                    $remainingAmount = 0;
                    break;
                }
            }

            $amount = 0; // Вся сумма распределена
        }

        return $amount;
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
                        "Пропорциональное распределение при пополнении основного счета";

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