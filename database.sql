
-- creating database for the project
CREATE DATABASE cluster; 
USE cluster;

CREATE TABLE user_accounts (
    username VARCHAR(255) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    token VARCHAR(255) NOT NULL
);

CREATE TABLE input_models (
    model_name VARCHAR(255) PRIMARY KEY NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    FOREIGN KEY (username) REFERENCES user_accounts(username)
);