<?php
require_once __DIR__ . '/../config/database.php';

class Account {
    private $conn;
    private $table = 'accounts';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAllAccounts() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY is_main DESC, account_number ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccountByNumber($account_number) {
        $query = "SELECT * FROM " . $this->table . " WHERE account_number = :account_number";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':account_number', $account_number);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAccount($account_number, $monthly_fee, $balance) {
        $query = "UPDATE " . $this->table . " 
                  SET monthly_fee = :monthly_fee, balance = :balance, updated_at = CURRENT_TIMESTAMP 
                  WHERE account_number = :account_number";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':account_number', $account_number);
        $stmt->bindParam(':monthly_fee', $monthly_fee);
        $stmt->bindParam(':balance', $balance);
        return $stmt->execute();
    }

    public function updateBalance($account_number, $new_balance) {
        $query = "UPDATE " . $this->table . " 
                  SET balance = :balance, updated_at = CURRENT_TIMESTAMP 
                  WHERE account_number = :account_number";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':account_number', $account_number);
        $stmt->bindParam(':balance', $new_balance);
        return $stmt->execute();
    }

    public function getMainAccount() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_main = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNonFrozenAccounts() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_frozen = 0 ORDER BY is_main DESC, account_number ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}