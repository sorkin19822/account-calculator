<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/CalculatorService.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../models/Transaction.php';

$calculatorService = new CalculatorService();
$accountModel = new Account();
$transactionModel = new Transaction();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($path, $accountModel, $transactionModel);
            break;
        case 'POST':
            handlePost($path, $calculatorService, $accountModel);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet($path, $accountModel, $transactionModel) {
    switch ($path) {
        case 'accounts':
            $accounts = $accountModel->getAllAccounts();
            echo json_encode(['success' => true, 'data' => $accounts]);
            break;

        case 'transactions':
            $limit = $_GET['limit'] ?? 50;
            $transactions = $transactionModel->getAllTransactions($limit);
            echo json_encode(['success' => true, 'data' => $transactions]);
            break;

        case 'calculate':
            $accounts = $accountModel->getAllAccounts();
            $amount = floatval($_GET['amount'] ?? 0);
            $target = $_GET['target'] ?? null;

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Сумма должна быть больше 0']);
                return;
            }

            $calculatorService = new CalculatorService();
            $distribution = $calculatorService->calculateDistribution($accounts, $amount, $target);
            echo json_encode(['success' => true, 'data' => $distribution]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePost($path, $calculatorService, $accountModel) {
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($path) {
        case 'deposit':
            $amount = floatval($input['amount'] ?? 0);
            $target = $input['target'] ?? null;

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Сумма должна быть больше 0']);
                return;
            }

            $result = $calculatorService->processDeposit($amount, $target);
            echo json_encode($result);
            break;

        case 'update_account':
            $accountNumber = $input['account_number'] ?? '';
            $monthlyFee = floatval($input['monthly_fee'] ?? 0);
            $balance = floatval($input['balance'] ?? 0);

            if (empty($accountNumber)) {
                echo json_encode(['success' => false, 'message' => 'Номер счета обязателен']);
                return;
            }

            $result = $calculatorService->updateAccountData($accountNumber, $monthlyFee, $balance);
            echo json_encode($result);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}