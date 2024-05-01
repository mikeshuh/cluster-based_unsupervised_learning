
-- creating database for the project
CREATE DATABASE cluster; 
USE cluster;

CREATE TABLE user_accounts (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- username: can ONLY contain English letters (case-insensitive), digits, _, -
    username VARCHAR(255) UNIQUE NOT NULL,
    -- email: well foramtted (make sure @ exist and . exist at certain areas)
    email VARCHAR(255) UNIQUE NOT NULL,
    -- have my own limitations
    password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE input_models (
    model_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(255) NOT NULL,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id)
);