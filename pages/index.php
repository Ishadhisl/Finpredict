<?php
/**
 * FinPredict - Customer Input Form
 * 
 * This page collects customer information and submits it for credit score calculation.
 * All form data is validated and sent to results.php for processing.
 */

session_start();

// Define page title
$page_title = "Customer Credit Assessment";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinPredict - Credit Limit Predictor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <h1>FinPredict</h1>
            <p class="tagline">Credit Limit Predictor - Intelligent Credit Assessment System</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <div class="form-container">
            <h2>Customer Credit Assessment Form</h2>
            <p class="tagline" style="margin-bottom: 2rem;">
                Please provide your financial information below. Your data will be used to calculate a comprehensive 
                credit score based on logical, transparent financial principles. All information is handled securely.
            </p>

            <!-- Form -->
            <form id="creditPredictForm" method="POST" action="results.php" novalidate>
                
                <!-- Personal Information Section -->
                <!-- Personal Information Section -->
                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name <span style="color: #e74c3c;">*</span></label>
                        <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span style="color: #e74c3c;">*</span></label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="age">Age <span style="color: #e74c3c;">*</span></label>
                        <input type="number" id="age" name="age" min="18" max="100" placeholder="Enter your age" required>
                        <small style="color: #666;">Must be between 18 and 100 years old</small>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender <span style="color: #e74c3c;">*</span></label>
                        <select id="gender" name="gender" required>
                            <option value="">-- Select Gender --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Financial Information Section -->
                <!-- Financial Information Section -->
                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Financial Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="monthly_income">Monthly Income (LKR) <span style="color: #e74c3c;">*</span></label>
                        <input type="number" id="monthly_income" name="monthly_income" min="0" step="0.01" 
                               placeholder="Enter your monthly income" required>
                        <small style="color: #666;">Gross monthly salary/income</small>
                    </div>
                    <div class="form-group">
                        <label for="existing_credit_limit">Existing Credit Limit (LKR) <span style="color: #e74c3c;">*</span></label>
                        <input type="number" id="existing_credit_limit" name="existing_credit_limit" min="0" step="0.01" 
                               placeholder="Enter existing credit limit" required>
                        <small style="color: #666;">Leave as 0 if you have no existing credit</small>
                    </div>
                </div>

                <!-- Income Guidance -->
                <div id="incomePreview"></div>

                <!-- Additional Information Section -->
                <!-- Additional Information Section -->
                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Additional Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="education_level">Education Level <span style="color: #e74c3c;">*</span></label>
                        <select id="education_level" name="education_level" required>
                            <option value="">-- Select Education Level --</option>
                            <option value="High School">High School</option>
                            <option value="Bachelor">Bachelor's Degree</option>
                            <option value="Master">Master's Degree</option>
                            <option value="PhD">PhD</option>
                        </select>
                        <small style="color: #666;">Highest education level completed</small>
                    </div>
                    <div class="form-group">
                        <label for="marital_status">Marital Status <span style="color: #e74c3c;">*</span></label>
                        <select id="marital_status" name="marital_status" required>
                            <option value="">-- Select Marital Status --</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                        <small style="color: #666;">Current marital status</small>
                    </div>
                </div>

                <!-- Form Instructions -->
                <div class="alert alert-info" style="margin-top: 2rem;">
                    <strong>ℹ️ How Your Score is Calculated:</strong>
                    <ul style="margin-top: 0.5rem; margin-bottom: 0; padding-left: 1.5rem;">
                        <li><strong>Income Score (30 pts):</strong> Higher income = better repayment capacity</li>
                        <li><strong>Credit History Score (25 pts):</strong> Existing credit limit shows bank trust</li>
                        <li><strong>Education Score (20 pts):</strong> Education correlates with financial literacy</li>
                        <li><strong>Marital Status Score (15 pts):</strong> Indicates financial stability patterns</li>
                        <li><strong>Age Score (10 pts):</strong> Peak earning age (30-55) shows stability</li>
                    </ul>
                    <p style="margin-top: 1rem; margin-bottom: 0;"><em>Total: 0-100 points. Score is transparent, logical, and defensible.</em></p>
                </div>

                <!-- Form Buttons -->
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        Calculate Credit Score
                    </button>
                    <button type="reset" class="btn btn-reset" onclick="resetForm()">
                        Clear Form
                    </button>
                </div>

                <p style="margin-top: 1.5rem; color: #666; font-size: 0.9rem;">
                    <span style="color: #e74c3c;">*</span> Indicates required field
                </p>
            </form>
        </div>

        <!-- Information Section -->
        <div style="background: #f8f9fa; padding: 2rem; border-radius: 8px; margin-bottom: 2rem;">
            <h3>About FinPredict</h3>
            <p>
                FinPredict is an intelligent credit assessment system designed to provide financial institutions 
                with a transparent, data-driven approach to credit decisions. Unlike arbitrary scoring methods, 
                FinPredict uses logical formulas based on financial risk theory to calculate credit worthiness.
            </p>
            <p>
                Our system considers multiple factors - income, existing credit history, education, marital status, 
                and age - to create a comprehensive credit profile. Each factor is weighted based on its importance 
                in predicting financial risk and repayment capacity.
            </p>
            <p>
                <strong>Key Benefits:</strong>
            </p>
            <ul style="padding-left: 2rem;">
                <li>Transparent scoring methodology</li>
                <li>Defensible decision-making process</li>
                <li>ML-inspired logical formulas</li>
                <li>Fair and consistent evaluation</li>
                <li>Clear customer communication</li>
            </ul>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <p><strong>FinPredict - Credit Limit Predictor</strong></p>
        <p>University Group Project | Intelligent Credit Assessment System</p>
        <p style="font-size: 0.9rem; margin-top: 1rem;">© 2026 FinPredict. All rights reserved.</p>
    </footer>

    <!-- Include JavaScript -->
    <script src="../assets/js/script.js"></script>
</body>
</html>
