
-- creating database for the project
CREATE DATABASE cluster; 
USE cluster;

CREATE TABLE user_accounts (
    username VARCHAR(255) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    token VARCHAR(255) NOT NULL
);

CREATE TABLE user_models (
    model_name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    PRIMARY KEY (model_name, username),
    FOREIGN KEY (username) REFERENCES user_accounts(username)
);

CREATE TABLE k_means (
    model_name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    centroids VARCHAR(255) NOT NULL,
    PRIMARY KEY (model_name, username),
    FOREIGN KEY (model_name, username) REFERENCES user_models(model_name, username)
);