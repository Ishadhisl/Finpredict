<?php
/**
 * FinPredict Credit Limit Calculator
 * 
 * CREDIT LIMIT RECOMMENDATION LOGIC:
 * 
 * The recommended credit limit is NOT arbitrary but based on:
 * 
 * 1. CUSTOMER'S MONTHLY INCOME
 *    - General rule: Credit limit should not exceed 2-3x monthly income
 *    - Reasoning: Debt-to-income ratio should remain manageable
 * 
 * 2. RISK ASSESSMENT SCORE
 *    - Low Risk: Can offer 30-50% increase
 *    - Medium Risk: Can offer 10-30% increase
 *    - High Risk: Offer minimal increase (0-10%)
 * 
 * 3. EXISTING CREDIT LIMIT
 *    - Uses existing limit as baseline
 *    - Calculates percentage increase based on risk level
 *    - Caps total limit to income-based maximum
 * 
 * FORMULA:
 * 1. Calculate income-based maximum = Monthly Income × Credit Multiplier (varies by risk)
 * 2. Calculate percentage increase based on risk level
 * 3. New Credit Limit = Existing Limit × (1 + Increase %)
 * 4. Cap at income-based maximum
 * 5. Ensure minimum recommended limit increases
 */

class CreditLimitCalculator {
    
    // Credit multipliers based on income (how much credit relative to income)
    // These are industry-standard recommendations
    private $credit_multiplier_low_risk = 3.0;      // Low risk: 3x monthly income
    private $credit_multiplier_medium_risk = 2.0;   // Medium risk: 2x monthly income
    private $credit_multiplier_high_risk = 1.0;     // High risk: 1x monthly income
    
    /**
     * Calculate recommended credit limit based on risk and income
     * 
     * @param int $credit_score The customer's credit score
     * @param string $risk_level The risk classification (Low Risk, Medium Risk, High Risk)
     * @param float $existing_credit_limit Current credit limit
     * @param float $monthly_income Customer's monthly income
     * @return array Detailed credit limit recommendation
     */
    public function calculateRecommendedCreditLimit($credit_score, $risk_level, $existing_credit_limit, $monthly_income) {
        
        $recommendation = [
            'existing_credit_limit' => $existing_credit_limit,
            'monthly_income' => $monthly_income,
            'income_based_maximum' => 0,
            'recommended_credit_limit' => 0,
            'increase_amount' => 0,
            'increase_percentage' => 0,
            'recommendation_reason' => '',
            'risk_based_reasoning' => ''
        ];
        
        // Step 1: Determine income-based maximum credit limit
        // This ensures customers don't get credit exceeding a reasonable debt-to-income ratio
        $income_based_max = $this->calculateIncomeBasedMaximum($monthly_income, $risk_level);
        $recommendation['income_based_maximum'] = $income_based_max;
        
        // Step 2: Calculate percentage increase based on risk level and score
        $increase_percentage = $this->calculateIncreasePercentage($risk_level, $credit_score);
        $recommendation['increase_percentage'] = $increase_percentage;
        
        // Step 3: Calculate new recommended credit limit
        if ($existing_credit_limit == 0) {
            // New customer: Start from reasonable base
            $new_credit_limit = max(1000, $monthly_income * 1.5);
        } else {
            // Existing customer: Apply percentage increase
            $new_credit_limit = $existing_credit_limit * (1 + ($increase_percentage / 100));
        }
        
        // Step 4: Cap at income-based maximum
        $new_credit_limit = min($new_credit_limit, $income_based_max);
        
        // Ensure minimum increase for approved customers
        if ($risk_level === 'Low Risk' || $risk_level === 'Medium Risk') {
            if ($existing_credit_limit > 0) {
                $minimum_increase = $existing_credit_limit * 0.05; // At least 5% increase
                $new_credit_limit = max($new_credit_limit, $existing_credit_limit + $minimum_increase);
                $new_credit_limit = min($new_credit_limit, $income_based_max); // Still cap at maximum
            }
        }
        
        // Step 5: Round to nearest hundred for professional presentation
        $new_credit_limit = round($new_credit_limit / 100) * 100;
        
        $recommendation['recommended_credit_limit'] = $new_credit_limit;
        $recommendation['increase_amount'] = $new_credit_limit - $existing_credit_limit;
        
        // Add reasoning
        $recommendation['income_based_reasoning'] = $this->getIncomeBasedReasoning($monthly_income, $income_based_max);
        $recommendation['risk_based_reasoning'] = $this->getRiskBasedReasoning($risk_level, $increase_percentage, $credit_score);
        
        return $recommendation;
    }
    
    /**
     * Calculate income-based maximum credit limit
     * 
     * Uses industry standard debt-to-income ratios:
     * - Low Risk: 3x monthly income (30% debt-to-income ratio)
     * - Medium Risk: 2x monthly income (20% debt-to-income ratio)
     * - High Risk: 1x monthly income (10% debt-to-income ratio)
     * 
     * @param float $monthly_income Customer's monthly income
     * @param string $risk_level Risk classification
     * @return float Maximum recommended credit limit based on income
     */
    private function calculateIncomeBasedMaximum($monthly_income, $risk_level) {
        
        if ($risk_level === 'Low Risk') {
            $maximum = $monthly_income * $this->credit_multiplier_low_risk;
        } elseif ($risk_level === 'Medium Risk') {
            $maximum = $monthly_income * $this->credit_multiplier_medium_risk;
        } else {
            // High Risk
            $maximum = $monthly_income * $this->credit_multiplier_high_risk;
        }
        
        // Set minimum credit limit of $500
        return max($maximum, 500);
    }
    
    /**
     * Calculate the percentage increase to offer
     * 
     * Based on risk level and credit score:
     * 
     * LOW RISK (Score 70-100):
     * - Score 90-100: 50% increase (excellent score)
     * - Score 80-89: 40% increase (very good score)
     * - Score 70-79: 30% increase (good score)
     * 
     * MEDIUM RISK (Score 40-69):
     * - Score 60-69: 25% increase
     * - Score 50-59: 15% increase
     * - Score 40-49: 10% increase
     * 
     * HIGH RISK (Score 0-39):
     * - Score 30-39: 5% increase (minimal, with conditions)
     * - Score < 30: 0% increase (no increase recommended)
     * 
     * @param string $risk_level Risk classification
     * @param int $credit_score Credit score
     * @return float Percentage increase to apply
     */
    private function calculateIncreasePercentage($risk_level, $credit_score) {
        
        if ($risk_level === 'Low Risk') {
            if ($credit_score >= 90) {
                return 50;
            } elseif ($credit_score >= 80) {
                return 40;
            } else {
                return 30;
            }
        } 
        elseif ($risk_level === 'Medium Risk') {
            if ($credit_score >= 60) {
                return 25;
            } elseif ($credit_score >= 50) {
                return 15;
            } else {
                return 10;
            }
        } 
        else {
            // High Risk
            if ($credit_score >= 30) {
                return 5;
            } else {
                return 0;
            }
        }
    }
    
    /**
     * Get explanation of income-based maximum
     */
    private function getIncomeBasedReasoning($monthly_income, $maximum) {
        return "Based on your monthly income of LKR " . number_format($monthly_income, 2) . 
               ", the maximum recommended credit limit is LKR " . number_format($maximum, 2) . 
               ". This ensures your debt-to-income ratio remains manageable.";
    }
    
    /**
     * Get explanation of risk-based increase
     */
    private function getRiskBasedReasoning($risk_level, $increase_percentage, $credit_score) {
        if ($risk_level === 'Low Risk') {
            return "Your excellent financial profile (score: $credit_score/100) qualifies you for a $increase_percentage% credit limit increase. " .
                   "This reflects your strong repayment capacity and financial stability.";
        } 
        elseif ($risk_level === 'Medium Risk') {
            return "Your acceptable financial profile (score: $credit_score/100) qualifies you for a $increase_percentage% credit limit increase. " .
                   "Continued responsible payment behavior can lead to further increases.";
        } 
        else {
            if ($increase_percentage == 0) {
                return "Your current financial constraints (score: $credit_score/100) do not support a credit limit increase at this time. " .
                       "We recommend improving your income and reducing existing obligations before reapplying.";
            } else {
                return "Your financial profile (score: $credit_score/100) allows only a minimal $increase_percentage% increase pending conditions. " .
                       "Demonstrate improved financial management for future increases.";
            }
        }
    }
    
    /**
     * Generate actionable recommendations for the customer
     */
    public function generateCustomerRecommendations($risk_level, $credit_score) {
        $recommendations = [];
        
        if ($risk_level === 'High Risk') {
            $recommendations[] = "Increase monthly income to improve creditworthiness";
            $recommendations[] = "Reduce existing debt obligations";
            $recommendations[] = "Build a longer credit history";
            $recommendations[] = "Reapply in 6-12 months after financial improvement";
        } 
        elseif ($risk_level === 'Medium Risk') {
            $recommendations[] = "Continue making payments on time";
            $recommendations[] = "Reduce credit utilization ratio";
            $recommendations[] = "Increase monthly income if possible";
            $recommendations[] = "Avoid taking on new debt";
        } 
        else {
            // Low Risk
            $recommendations[] = "Maintain your excellent financial discipline";
            $recommendations[] = "Continue consistent on-time payments";
            $recommendations[] = "Monitor your credit utilization";
            $recommendations[] = "You are eligible for premium banking services";
        }
        
        return $recommendations;
    }
}
?>
