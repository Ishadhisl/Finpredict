<?php
/**
 * FinPredict Credit Score Calculation Engine
 * 
 * SCORING METHODOLOGY (ML-Inspired, Non-Arbitrary):
 * 
 * This system uses a weighted scoring model inspired by machine learning principles.
 * Each factor is evaluated logically based on financial risk theory:
 * 
 * 1. INCOME SCORE (Max: 30 points)
 *    - Higher income = Lower financial risk
 *    - Formula: Normalized income ratio vs. average baseline
 *    - Reasoning: Customers with higher income have better capacity to repay
 * 
 * 2. EXISTING CREDIT LIMIT SCORE (Max: 25 points)
 *    - Higher existing limit = Bank already trusted them = Lower risk
 *    - Formula: Growth potential from current limit
 *    - Reasoning: If bank already gave them credit, they're less risky
 * 
 * 3. EDUCATION SCORE (Max: 20 points)
 *    - Higher education = Better financial literacy = Lower risk
 *    - Logic: Educated individuals tend to make better financial decisions
 *    - Point allocation based on degree level
 * 
 * 4. MARITAL STATUS SCORE (Max: 15 points)
 *    - Married = Potentially more stable = Slightly lower risk
 *    - Single = Independent income source = Neutral risk
 *    - Logic: Marital status indicates financial stability patterns
 * 
 * 5. AGE FACTOR (Max: 10 points)
 *    - Middle-aged customers (30-55) = Peak earning potential = Lower risk
 *    - Young (18-29) or Old (55+) = Slightly higher risk
 *    - Logic: Age correlates with financial maturity and stability
 * 
 * TOTAL SCORE: 0-100
 * - Score calculation is transparent, logical, and defensible
 * - Each component has clear financial reasoning
 * - Can be explained to customers and auditors
 */

include_once __DIR__ . '/../config/db_connection.php';

class CreditScoreCalculator {
    
    // Income thresholds (monthly income in dollars)
    private $AVERAGE_MONTHLY_INCOME = 4000;
    private $HIGH_INCOME_THRESHOLD = 8000;
    
    // Credit limit thresholds
    private $MIN_CREDIT_LIMIT = 500;
    private $HIGH_CREDIT_LIMIT = 20000;
    
    /**
     * Calculate credit score based on customer data
     * 
     * @param array $customer_data Customer information
     * @return array Breakdown of score calculation with component scores
     */

    public function calculateCreditScore($customer_data) {
        
        // Validate input data
        if (!$this->validateInputData($customer_data)) {
            return ['error' => 'Invalid input data'];
        }
        
        // -------------------------------------------------------------
        // HYBRID SCORING: COMPONENTS + ML MODEL
        // -------------------------------------------------------------
        
        // 1. Calculate Rule-Based Components (for visualization)
        $scores = [
            'income_score' => $this->calculateIncomeScore($customer_data['monthly_income']),
            'credit_limit_score' => $this->calculateCreditLimitScore($customer_data['existing_credit_limit']),
            'education_score' => $this->calculateEducationScore($customer_data['education_level']),
            'marital_status_score' => $this->calculateMaritalStatusScore($customer_data['marital_status']),
            'age_score' => $this->calculateAgeScore($customer_data['age']),
            'breakdown' => []
        ];
        
        // Calculate Rule-Based Total
        $rule_based_total = $scores['income_score'] + 
                           $scores['credit_limit_score'] + 
                           $scores['education_score'] + 
                           $scores['marital_status_score'] + 
                           $scores['age_score'];

        // 2. Attempt ML Prediction
        $ml_result = $this->callPytonMLApi($customer_data);
        
        if ($ml_result && !isset($ml_result['error'])) {
            // ML SUCCESS: Use ML Score
            $scores['total_score'] = $ml_result['credit_score'];
            $scores['recommendation_from_ml'] = $ml_result['recommended_limit']; // Pass this up
            
            // Calculate Difference for "ML Adjustment"
            $adjustment = $scores['total_score'] - $rule_based_total;
            $scores['ml_adjustment'] = $adjustment;
            
        } else {
            // FALLBACK: Use Rule-Based Score
            $scores['total_score'] = $rule_based_total;
            $scores['ml_adjustment'] = 0;
        }

        // Add breakdown explanation
        $scores['breakdown'] = [
            'income' => "Income Score: {$scores['income_score']}/30 points",
            'credit_limit' => "Credit Limit Score: {$scores['credit_limit_score']}/25 points",
            'education' => "Education Score: {$scores['education_score']}/20 points",
            'marital_status' => "Marital Status Score: {$scores['marital_status_score']}/15 points",
            'age' => "Age Score: {$scores['age_score']}/10 points"
        ];
        
        if ($scores['ml_adjustment'] != 0) {
             $scores['breakdown']['adjustment'] = "AI Model Adjustment: " . ($scores['ml_adjustment'] > 0 ? "+" : "") . $scores['ml_adjustment'];
        }
        
        return $scores;
    }

    /**
     * Call the Python Flask API
     */
    private function callPytonMLApi($data) {
        $url = 'http://127.0.0.1:5000/predict';
        
        $json_data = json_encode($data);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout - fail fast
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $result) {
            return json_decode($result, true);
        }
        
        return false;
    }
    
    /**
     * INCOME SCORE CALCULATION (Max: 30 points)
     * 
     * Logic: Customers with higher income have better repayment capacity
     * Formula: Income ratio scaled to 30 points
     * 
     * Examples:
     * - 2000 LKR/month → Low score (financial stress)
     * - 4000 LKR/month → Medium score (average)
     * - 10000+ LKR/month → High score (strong repayment ability)
     */
    private function calculateIncomeScore($monthly_income) {
        $max_income_score = 30;
        
        // Calculate income ratio (against average baseline)
        $income_ratio = $monthly_income / $this->AVERAGE_MONTHLY_INCOME;
        
        // Scale to maximum 30 points
        // Capped at 30 points even for very high income
        $income_score = $income_ratio * 15;
        
        // Cap at maximum
        if ($income_score > $max_income_score) {
            $income_score = $max_income_score;
        }
        
        // Minimum 5 points for employed individuals
        if ($income_score < 5 && $monthly_income > 0) {
            $income_score = 5;
        }
        
        return round($income_score, 2);
    }
    
    /**
     * EXISTING CREDIT LIMIT SCORE (Max: 25 points)
     * 
     * Logic: Existing credit limit indicates historical trust from bank
     * If a financial institution already granted credit, customer is less risky
     * Formula: Credit limit growth potential
     * 
     * Examples:
     * - No credit limit → 0 points (new customer risk)
     * - 5000 LKR existing → 10 points (some history)
     * - 20000+ LKR existing → 25 points (high trust established)
     */
    private function calculateCreditLimitScore($existing_credit_limit) {
        $max_credit_limit_score = 25;
        
        // If customer has no existing credit limit, score is low (higher risk)
        if ($existing_credit_limit == 0) {
            return 3; // Minimal score for new customers
        }
        
        // Calculate score based on credit limit size
        // Higher limit = Bank already trusts them = Lower risk
        $limit_ratio = $existing_credit_limit / $this->HIGH_CREDIT_LIMIT;
        $credit_limit_score = $limit_ratio * $max_credit_limit_score;
        
        // Cap at maximum
        if ($credit_limit_score > $max_credit_limit_score) {
            $credit_limit_score = $max_credit_limit_score;
        }
        
        // Minimum 1 point for any existing credit
        if ($credit_limit_score < 1 && $existing_credit_limit > 0) {
            $credit_limit_score = 1;
        }
        
        return round($credit_limit_score, 2);
    }
    
    /**
     * EDUCATION SCORE CALCULATION (Max: 20 points)
     * 
     * Logic: Education correlates with financial literacy and decision-making
     * Higher education = Better financial planning = Lower risk
     * 
     * Point allocation:
     * - High School: 5 points (basic financial knowledge)
     * - Bachelor: 15 points (strong financial knowledge)
     * - Master: 18 points (advanced knowledge)
     * - PhD: 20 points (highest level expertise)
     */
    private function calculateEducationScore($education_level) {
        $education_scores = [
            'High School' => 5,
            'Bachelor' => 15,
            'Master' => 18,
            'PhD' => 20
        ];
        
        return isset($education_scores[$education_level]) ? $education_scores[$education_level] : 5;
    }
    
    /**
     * MARITAL STATUS SCORE (Max: 15 points)
     * 
     * Logic: Marital status can indicate financial stability
     * - Married: Typically dual income potential, more financial commitments → 12 points
     * - Single: Independent income, flexible spending → 10 points
     * - Divorced/Widowed: May have financial obligations → 8 points
     * 
     * NOTE: This is NOT a discriminatory factor but reflects average statistical patterns
     * in financial stability and commitment levels
     */
    private function calculateMaritalStatusScore($marital_status) {
        $marital_scores = [
            'Married' => 12,
            'Single' => 10,
            'Divorced' => 8,
            'Widowed' => 9
        ];
        
        return isset($marital_scores[$marital_status]) ? $marital_scores[$marital_status] : 10;
    }
    
    /**
     * AGE SCORE CALCULATION (Max: 10 points)
     * 
     * Logic: Age correlates with financial maturity and earning stability
     * Peak earning age (30-55): Lowest risk → 10 points
     * Young (18-29): Career establishment risk → 6 points
     * Older (55+): Retirement risk → 7 points
     * 
     * Examples:
     * - Age 22: 6 points (early career, less stable)
     * - Age 45: 10 points (peak earning years)
     * - Age 68: 7 points (approaching/in retirement)
     */
    private function calculateAgeScore($age) {
        $age_score = 0;
        
        if ($age >= 30 && $age <= 55) {
            // Peak earning years - lowest risk
            $age_score = 10;
        } elseif ($age >= 18 && $age < 30) {
            // Young professionals - establishing careers
            $age_score = 6;
        } elseif ($age > 55) {
            // Pre-retirement/retirement age
            $age_score = 7;
        }
        
        return $age_score;
    }
    
    /**
     * Validate input data before processing
     */
    private function validateInputData($data) {
        $required_fields = ['monthly_income', 'existing_credit_limit', 'education_level', 'marital_status', 'age'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }
        
        // Validate numeric fields
        if (!is_numeric($data['monthly_income']) || $data['monthly_income'] < 0) {
            return false;
        }
        
        if (!is_numeric($data['existing_credit_limit']) || $data['existing_credit_limit'] < 0) {
            return false;
        }
        
        if (!is_numeric($data['age']) || $data['age'] < 18 || $data['age'] > 100) {
            return false;
        }
        
        return true;
    }
}
?>
