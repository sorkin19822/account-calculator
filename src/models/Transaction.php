<?php
require_once 'config/database.php';

class Transaction {
    private $conn;
    private $table = 'transactions';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function addTransaction($account_number, $type, $amount, $balance_before, $balance_after, $description = '') {
        $query = "INSERT INTO " . $this->table . " 
                  (account_number, transaction_type, amount, balance_before, balance_after, description) 
                  VALUES (:account_number, :type, :amount, :balance_before, :balance_after, :description)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':account_number', $account_number);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':balance_before', $balance_before);
        $stmt->bindParam(':balance_after', $balance_after);
        $stmt->bindParam(':description', $description);
        return $stmt->execute();
    }

    public function getTransactionsByAccount($account_number, $limit = 10) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE account_number = :account_number 
                  ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':account_number', $account_number);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllTransactions($limit = 50) {
        $query = "SELECT t.*, a.is_main FROM " . $this->table . " t 
                  JOIN accounts a ON t.account_number = a.account_number 
                  ORDER BY t.created_at DESC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}