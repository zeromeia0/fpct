CREATE DATABASE IF NOT EXISTS if0_40173882_fpct_db;
USE if0_40173882_fpct_db;

CREATE TABLE IF NOT EXISTS vini_whitelist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mac_address VARCHAR(17) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vini_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  salt VARBINARY(16) NOT NULL,
  passhash VARBINARY(32) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Exemplo de como inserir uma whitelist manualmente:
-- INSERT INTO vini_whitelist (mac_address, description) VALUES ('00:11:22:33:44:55', 'Test device');
