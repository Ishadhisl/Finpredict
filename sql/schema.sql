-- FinPredict Database Schema
-- Credit Limit Predictor System
-- Created for university group project

-- Create database
CREATE DATABASE IF NOT EXISTS finpredict_db;
USE finpredict_db;

-- Table: customers
-- Stores customer personal information
-- This table holds the input data for credit score calculation
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    age INT NOT NULL CHECK (age >= 18 AND age <= 100),
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    education_level ENUM('High School', 'Bachelor', 'Master', 'PhD') NOT NULL,
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL,
    monthly_income DECIMAL(12, 2) NOT NULL CHECK (monthly_income >= 0),
    existing_credit_limit DECIMAL(12, 2) NOT NULL CHECK (existing_credit_limit >= 0),
    employment_years INT DEFAULT 0 CHECK (employment_years >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: credit_assessments
-- Stores the credit score calculation results
-- This is the core output of the system
CREATE TABLE IF NOT EXISTS credit_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    income_score INT,
    credit_limit_score INT,
    education_score INT,
    marital_status_score INT,
    total_credit_score INT NOT NULL CHECK (total_credit_score >= 0 AND total_credit_score <= 100),
    risk_level ENUM('Low Risk', 'Medium Risk', 'High Risk') NOT NULL,
    recommended_credit_limit DECIMAL(12, 2) NOT NULL,
    recommendation_percentage DECIMAL(5, 2),
    assessment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

-- Table: recommendation_history
-- Keeps track of all recommendations made to customers
-- Useful for auditing and tracking changes over time
CREATE TABLE IF NOT EXISTS recommendation_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    previous_credit_limit DECIMAL(12, 2),
    new_credit_limit DECIMAL(12, 2),
    decision_status ENUM('Approved', 'Rejected', 'Under Review') DEFAULT 'Under Review',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES credit_assessments(assessment_id) ON DELETE CASCADE
);

-- Create indexes for better query performance
CREATE INDEX idx_customer_email ON customers(email);
CREATE INDEX idx_assessment_customer ON credit_assessments(customer_id);
CREATE INDEX idx_assessment_date ON credit_assessments(assessment_date);
CREATE INDEX idx_recommendation_assessment ON recommendation_history(assessment_id);
