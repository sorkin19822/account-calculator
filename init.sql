CREATE DATABASE IF NOT EXISTS accounts_db;
USE accounts_db;

CREATE TABLE accounts (
                          id INT AUTO_INCREMENT PRIMARY KEY,
                          account_number VARCHAR(20) NOT NULL UNIQUE,
                          monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
                          balance DECIMAL(10,2) NOT NULL DEFAULT 0,
                          is_main BOOLEAN NOT NULL DEFAULT 0,
                          is_frozen BOOLEAN NOT NULL DEFAULT 0,
                          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
                              id INT AUTO_INCREMENT PRIMARY KEY,
                              account_number VARCHAR(20) NOT NULL,
                              transaction_type ENUM('deposit', 'fee_deduction', 'distribution', 'balance_adjustment') NOT NULL,
                              amount DECIMAL(10,2) NOT NULL,
                              balance_before DECIMAL(10,2) NOT NULL,
                              balance_after DECIMAL(10,2) NOT NULL,
                              description TEXT,
                              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                              FOREIGN KEY (account_number) REFERENCES accounts(account_number)
);

-- Вставка тестовых данных
INSERT INTO accounts (account_number, monthly_fee, balance, is_main, is_frozen) VALUES
                                                                                    ('715044', 200.00, -50.00, 1, 0),
                                                                                    ('71504401', 150.00, 100.00, 0, 0),
                                                                                    ('71504402', 100.00, -100.00, 0, 0),
                                                                                    ('71504403', 0.00, 50.00, 0, 1),
                                                                                    ('71504404', 150.00, 150.00, 0, 0);