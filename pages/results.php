<?php
/**
 * FinPredict - Results Page
 * 
 * This page:
 * 1. Receives customer data from the form
 * 2. Validates and sanitizes all inputs
 * 3. Calculates the credit score
 * 4. Classifies risk level
 * 5. Calculates recommended credit limit
 * 6. Stores results in database
 * 7. Displays comprehensive results to user
 */

session_start();

// Include required files
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/credit_score.php';
require_once __DIR__ . '/../includes/risk_classifier.php';
require_once __DIR__ . '/../includes/credit_limit_calculator.php';

// Initialize variables
$error_message = '';
$success_message = '';
$customer_data = [];
$credit_score = 0;
$risk_level = '';
$recommended_limit = 0;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Step 1: Validate and sanitize input data
    $customer_data = [
        'name' => isset($_POST['name']) ? sanitize_input($_POST['name']) : '',
        'email' => isset($_POST['email']) ? sanitize_input($_POST['email']) : '',
        'age' => isset($_POST['age']) ? intval($_POST['age']) : 0,
        'gender' => isset($_POST['gender']) ? sanitize_input($_POST['gender']) : '',
        'monthly_income' => isset($_POST['monthly_income']) ? floatval($_POST['monthly_income']) : 0,
        'existing_credit_limit' => isset($_POST['existing_credit_limit']) ? floatval($_POST['existing_credit_limit']) : 0,
        'education_level' => isset($_POST['education_level']) ? sanitize_input($_POST['education_level']) : '',
        'marital_status' => isset($_POST['marital_status']) ? sanitize_input($_POST['marital_status']) : ''
    ];
    
    // Step 2: Validate all required fields
    $validation_errors = [];
    
    if (empty($customer_data['name']) || strlen($customer_data['name']) < 2) {
        $validation_errors[] = "Name is required and must be at least 2 characters";
    }
    
    if (empty($customer_data['email']) || !validate_email($customer_data['email'])) {
        $validation_errors[] = "Valid email address is required";
    }
    
    if (!validate_numeric($customer_data['age'], 18, 100)) {
        $validation_errors[] = "Age must be between 18 and 100";
    }
    
    if (empty($customer_data['gender'])) {
        $validation_errors[] = "Gender is required";
    }
    
    if (!validate_numeric($customer_data['monthly_income'], 0)) {
        $validation_errors[] = "Monthly income must be a positive number";
    }
    
    if (!validate_numeric($customer_data['existing_credit_limit'], 0)) {
        $validation_errors[] = "Existing credit limit must be a positive number";
    }
    
    if (empty($customer_data['education_level'])) {
        $validation_errors[] = "Education level is required";
    }
    
    if (empty($customer_data['marital_status'])) {
        $validation_errors[] = "Marital status is required";
    }
    
    // If there are validation errors, display them
    if (!empty($validation_errors)) {
        $error_message = "Please correct the following errors:\n" . implode("\n", $validation_errors);
    } else {
        
        // Step 3: Insert or Update customer data in database (Handle returning customers)
        $check_sql = "SELECT customer_id FROM customers WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $customer_data['email']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $existing_customer = $result->fetch_assoc();
        $check_stmt->close();

        if ($existing_customer) {
            // Update existing customer
            $customer_id = $existing_customer['customer_id'];
            $sql = "UPDATE customers SET name = ?, age = ?, gender = ?, education_level = ?, marital_status = ?, monthly_income = ?, existing_credit_limit = ? WHERE customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sissdddi",
                $customer_data['name'],
                $customer_data['age'],
                $customer_data['gender'],
                $customer_data['education_level'],
                $customer_data['marital_status'],
                $customer_data['monthly_income'],
                $customer_data['existing_credit_limit'],
                $customer_id
            );
        } else {
            // Insert new customer
            $sql = "INSERT INTO customers (name, email, age, gender, education_level, marital_status, monthly_income, existing_credit_limit) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssissddd",
                $customer_data['name'],
                $customer_data['email'],
                $customer_data['age'],
                $customer_data['gender'],
                $customer_data['education_level'],
                $customer_data['marital_status'],
                $customer_data['monthly_income'],
                $customer_data['existing_credit_limit']
            );
        }
        
        if ($stmt) {
            if ($stmt->execute()) {
                if (!$existing_customer) {
                    $customer_id = $stmt->insert_id;
                }
                
                // Step 4: Calculate credit score
                $calculator = new CreditScoreCalculator();
                $score_data = $calculator->calculateCreditScore($customer_data);
                
                if (isset($score_data['error'])) {
                    $error_message = "Error calculating credit score: " . $score_data['error'];
                } else {
                    $credit_score = intval($score_data['total_score']);
                    
                    // Step 5: Classify risk level
                    $classifier = new RiskClassifier();
                    $risk_data = $classifier->classifyRisk($credit_score);
                    $risk_level = $risk_data['risk_level'];
                    
                    // Step 6: Calculate recommended credit limit
                    $limit_calculator = new CreditLimitCalculator();
                    
                    if (isset($score_data['is_ml_generated']) && $score_data['is_ml_generated']) {
                        // USE ML PREDICTION
                        $recommended_limit = intval($score_data['recommendation_from_ml']);
                        
                        // Calculate percentage change manually for display
                        $increase_percentage = 0;
                        if ($customer_data['existing_credit_limit'] > 0) {
                            $increase_percentage = (($recommended_limit - $customer_data['existing_credit_limit']) / $customer_data['existing_credit_limit']) * 100;
                        }
                        $increase_percentage = round($increase_percentage);
                        
                        // Construct the limit_data array expected by the view
                        $limit_data = [
                            'existing_credit_limit' => $customer_data['existing_credit_limit'],
                            'monthly_income' => $customer_data['monthly_income'],
                            'income_based_maximum' => $recommended_limit * 1.5, // Estimate for display
                            'recommended_credit_limit' => $recommended_limit,
                            'increase_amount' => $recommended_limit - $customer_data['existing_credit_limit'],
                            'increase_percentage' => $increase_percentage,
                            'recommendation_reason' => 'AI Model Recommendation based on similar customer profiles.',
                            'risk_based_reasoning' => 'The AI model has analyzed your profile against thousands of historical records to determine this optimal credit limit.',
                            'income_based_reasoning' => 'Your income supports this AI-optimized credit limit.'
                        ];
                        
                    } else {
                        // USE TRADITIONAL CALCULATION
                        $limit_data = $limit_calculator->calculateRecommendedCreditLimit(
                            $credit_score,
                            $risk_level,
                            $customer_data['existing_credit_limit'],
                            $customer_data['monthly_income']
                        );
                        $recommended_limit = intval($limit_data['recommended_credit_limit']);
                        $increase_percentage = $limit_data['increase_percentage'];
                    }
                    
                    // Step 7: Store assessment results in database
                    $assessment_sql = "INSERT INTO credit_assessments 
                                      (customer_id, income_score, credit_limit_score, education_score, 
                                       marital_status_score, total_credit_score, risk_level, recommended_credit_limit, recommendation_percentage) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $assessment_stmt = $conn->prepare($assessment_sql);
                    
                    if ($assessment_stmt) {
                        $assessment_stmt->bind_param(
                            "iiiiisidi",
                            $customer_id,
                            $score_data['income_score'],
                            $score_data['credit_limit_score'],
                            $score_data['education_score'],
                            $score_data['marital_status_score'],
                            $credit_score,
                            $risk_level,
                            $recommended_limit,
                            $increase_percentage
                        );
                        
                        $assessment_stmt->execute();
                        $assessment_id = $assessment_stmt->insert_id;
                        
                        // Store in session for this request
                        $_SESSION['assessment_id'] = $assessment_id;
                        $_SESSION['credit_score'] = $credit_score;
                        $_SESSION['risk_level'] = $risk_level;
                        $_SESSION['customer_data'] = $customer_data;
                        $_SESSION['score_breakdown'] = $score_data;
                        $_SESSION['limit_data'] = $limit_data;
                        $_SESSION['risk_data'] = $risk_data;
                        
                        $success_message = "Credit assessment completed successfully!";
                    } else {
                        $error_message = "Error storing assessment: " . $conn->error;
                    }
                }
            } else {
                $error_message = "Error storing customer data: " . $stmt->error;
            }
            
            $stmt->close();
        } else {
            $error_message = "Database error: " . $conn->error;
        }
    }
}

// Check if we have valid results to display
$has_results = isset($_SESSION['credit_score']) && !empty($error_message) === false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Assessment Results - FinPredict</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <h1>FinPredict</h1>
            <p class="tagline">Credit Assessment Results</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        
        <?php if (!empty($error_message)): ?>
            <!-- Error Alert -->
            <div class="alert alert-danger">
                <strong>⚠️ Error:</strong>
                <?php echo nl2br(htmlspecialchars($error_message)); ?>
            </div>
            <div style="text-align: center; margin: 2rem 0;">
                <a href="index.php" class="btn btn-primary" style="width: auto; display: inline-flex;">
                    ← Back to Assessment Form
                </a>
            </div>
        <?php elseif ($has_results): 
            $credit_score = $_SESSION['credit_score'];
            $risk_level = $_SESSION['risk_level'];
            $customer_data = $_SESSION['customer_data'];
            $score_data = $_SESSION['score_breakdown'];
            $limit_data = $_SESSION['limit_data'];
            $risk_data = $_SESSION['risk_data'];
        ?>
            <!-- Success Results -->
            <div class="alert alert-success">
                <strong>✓ Assessment Complete!</strong> Credit score has been calculated and stored in our system.
            </div>

            <!-- Customer Information -->
            <div class="results-container">
                <h2>Customer Assessment Summary</h2>
                
                <div class="results-grid">
                    <div>
                        <h4>Customer Information</h4>
                        <table>
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td style="text-align: right;">
                                    <?php echo htmlspecialchars($customer_data['name']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td style="text-align: right;">
                                    <?php echo htmlspecialchars($customer_data['email']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Age:</strong></td>
                                <td style="text-align: right;">
                                    <?php echo $customer_data['age']; ?> years
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Gender:</strong></td>
                                <td style="text-align: right;">
                                    <?php echo htmlspecialchars($customer_data['gender']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Education:</strong></td>
                                <td style="text-align: right;">
                                    <?php echo htmlspecialchars($customer_data['education_level']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="border-bottom: 0;"><strong>Marital Status:</strong></td>
                                <td style="text-align: right; border-bottom: 0;">
                                    <?php echo htmlspecialchars($customer_data['marital_status']); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div>
                        <h4>Financial Information</h4>
                        <table>
                            <tr>
                                <td><strong>Monthly Income:</strong></td>
                                <td style="text-align: right;">
                                    LKR <?php echo number_format($customer_data['monthly_income'], 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Existing Credit Limit:</strong></td>
                                <td style="text-align: right;">
                                    <?php echo $customer_data['existing_credit_limit'] > 0 ? 'LKR ' . number_format($customer_data['existing_credit_limit'], 2) : 'None'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="border-bottom: 0;"><strong>Assessment Date:</strong></td>
                                <td style="text-align: right; border-bottom: 0;">
                                    <?php echo date('F d, Y H:i'); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Credit Score Display -->
            <div class="results-container">
                <h2>Credit Score Analysis</h2>
                
                <!-- Main Score -->
                <div class="score-display">
                    <div class="score-number"><?php echo $credit_score; ?></div>
                    <div class="score-label">Credit Score (out of 100)</div>
                </div>

                <!-- Risk Level -->
                <div style="text-align: center; margin: 1.5rem 0;">
                    <p style="margin-bottom: 0.5rem;"><strong>Risk Classification:</strong></p>
                    <span class="risk-badge risk-<?php echo strtolower(str_replace(' ', '-', $risk_level)); ?>">
                        <?php echo htmlspecialchars($risk_level); ?>
                    </span>
                </div>

                <!-- Score Breakdown -->
                <div class="score-breakdown">
                    <h4 style="margin-bottom: 1rem; color: #2c3e50;">Score Breakdown</h4>
                    
                    <div class="breakdown-item">
                        <span class="breakdown-label">Income Score</span>
                        <span class="breakdown-score"><?php echo intval($score_data['income_score']); ?>/30</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Existing Credit Limit Score</span>
                        <span class="breakdown-score"><?php echo intval($score_data['credit_limit_score']); ?>/25</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Education Score</span>
                        <span class="breakdown-score"><?php echo intval($score_data['education_score']); ?>/20</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Marital Status Score</span>
                        <span class="breakdown-score"><?php echo intval($score_data['marital_status_score']); ?>/15</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Age Score</span>
                        <span class="breakdown-score"><?php echo intval($score_data['age_score']); ?>/10</span>
                    </div>
                    <?php if (isset($score_data['ml_adjustment']) && $score_data['ml_adjustment'] != 0): ?>
                    <div class="breakdown-item" style="background-color: #f0f8ff;">
                        <span class="breakdown-label"><strong>AI Model Adjustment</strong></span>
                        <span class="breakdown-score" style="color: <?php echo $score_data['ml_adjustment'] > 0 ? '#27ae60' : '#e74c3c'; ?>;">
                            <strong><?php echo ($score_data['ml_adjustment'] > 0 ? '+' : '') . intval($score_data['ml_adjustment']); ?></strong>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="breakdown-item" style="border-bottom: none; font-weight: bold; font-size: 1.1rem;">
                        <span class="breakdown-label">Total Score (AI Predicted)</span>
                        <span class="breakdown-score"><?php echo $credit_score; ?>/100</span>
                    </div>
                </div>

                <!-- Risk Assessment -->
                <div class="recommendation-box <?php 
                    if ($risk_level === 'Low Risk') echo '';
                    elseif ($risk_level === 'Medium Risk') echo 'conditional';
                    else echo 'not-recommended';
                ?>">
                    <div class="recommendation-title">
                        Risk Assessment
                    </div>
                    <p><?php echo htmlspecialchars($risk_data['description']); ?></p>
                    <p style="margin-top: 1rem; margin-bottom: 0;">
                        <strong>Recommendation:</strong> <?php echo htmlspecialchars($risk_data['recommendation']); ?>
                    </p>
                    <p style="margin-top: 0.5rem; margin-bottom: 0;">
                        <strong>Approval Likelihood:</strong> <?php echo htmlspecialchars($risk_data['approval_likelihood']); ?>
                    </p>
                </div>
            </div>

            <!-- Credit Limit Recommendation -->
            <div class="results-container">
                <h2>Credit Limit Recommendation</h2>
                
                <div class="results-grid">
                    <div>
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">Income-Based Maximum</h4>
                        <div class="credit-limit-result">
                            <div class="credit-limit-label">Maximum Recommended Limit</div>
                            <div class="credit-limit-amount">
                                LKR <?php echo number_format($limit_data['income_based_maximum'], 0); ?>
                            </div>
                            <div style="font-size: 0.9rem; color: #666; margin-top: 1rem;">
                                <?php echo htmlspecialchars($limit_data['income_based_reasoning']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">Recommended Credit Limit</h4>
                        <div class="credit-limit-result" style="background: linear-gradient(135deg, #27ae60, #229954); color: white;">
                            <div class="credit-limit-label" style="color: rgba(255,255,255,0.9);">New Recommended Limit</div>
                             <div class="credit-limit-amount" style="color: white;">
                                LKR <?php echo number_format($limit_data['recommended_credit_limit'], 0); ?>
                            </div>
                            <div class="increase-info positive" style="color: rgba(255,255,255,0.95);">
                                 <?php 
                                $increase = $limit_data['recommended_credit_limit'] - $limit_data['existing_credit_limit'];
                                if ($increase > 0) {
                                    echo '↑ + LKR ' . number_format($increase, 0) . ' (' . $limit_data['increase_percentage'] . '% increase)';
                                } elseif ($increase == 0) {
                                    echo 'No change from existing limit';
                                } else {
                                    echo '↓ - LKR ' . number_format(abs($increase), 0) . ' adjustment';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Risk-Based Reasoning -->
                <div class="alert alert-info" style="margin-top: 2rem;">
                    <strong>📊 Risk-Based Reasoning:</strong>
                    <p style="margin-top: 0.5rem; margin-bottom: 0;">
                        <?php echo htmlspecialchars($limit_data['risk_based_reasoning']); ?>
                    </p>
                </div>
            </div>

            <!-- Recommendations for Customer -->
            <div class="results-container">
                <h2>Recommendations for Continued Financial Health</h2>
                
                <?php 
                $limit_calculator = new CreditLimitCalculator();
                $recommendations = $limit_calculator->generateCustomerRecommendations($risk_level, $credit_score);
                ?>
                
                <div class="recommendations-list">
                    <h4>Action Items to Improve Your Financial Profile</h4>
                    <ul>
                        <?php foreach ($recommendations as $rec): ?>
                            <li><?php echo htmlspecialchars($rec); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Educational Note -->
                <div class="alert alert-info" style="margin-top: 1.5rem;">
                    <strong>ℹ️ How This Assessment Works:</strong>
                    <p style="margin-top: 0.5rem; margin-bottom: 0;">
                        This assessment uses a transparent, logical scoring system based on financial risk theory. 
                        Each component is weighted according to its importance in predicting repayment capacity. 
                        This approach ensures fair, defensible decision-making that can be explained and justified 
                        to stakeholders.
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="results-container" style="text-align: center;">
                <h3 style="margin-bottom: 1.5rem;">Next Steps</h3>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <button class="btn btn-secondary" onclick="printResults()" style="width: auto;">
                        🖨️ Print Results
                    </button>
                    <a href="index.php" class="btn btn-primary" style="display: inline-block; width: auto; text-decoration: none;">
                        ← New Assessment
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- No data submitted -->
            <div class="alert alert-warning">
                <strong>⚠️ No Assessment Data:</strong> Please complete the customer assessment form to calculate credit scores.
            </div>
            <div style="text-align: center; margin: 2rem 0;">
                <a href="index.php" class="btn btn-primary" style="width: auto; display: inline-flex;">
                    ← Back to Assessment Form
                </a>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p><strong>FinPredict - Credit Limit Predictor</strong></p>
        <p>University Group Project | Intelligent Credit Assessment System</p>
        <p style="font-size: 0.9rem; margin-top: 1rem; opacity: 0.7;">© 2026 FinPredict. All rights reserved.</p>
    </footer>

    <!-- Include JavaScript -->
    <script src="../assets/js/script.js"></script>
</body>
</html>
