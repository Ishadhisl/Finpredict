<?php
/**
 * FinPredict Risk Classification Engine
 * 
 * RISK LEVEL DETERMINATION:
 * 
 * Based on the credit score (0-100), customers are classified into risk tiers:
 * 
 * 1. LOW RISK (Score: 70-100)
 *    - Excellent financial health
 *    - High repayment probability
 *    - Can receive significant credit limit increase
 *    - Typical customer profile: Good income, education, credit history
 * 
 * 2. MEDIUM RISK (Score: 40-69)
 *    - Acceptable financial health
 *    - Moderate repayment probability
 *    - Can receive moderate credit limit increase
 *    - Typical customer profile: Average income, some credit history
 * 
 * 3. HIGH RISK (Score: 0-39)
 *    - Poor financial health
 *    - Lower repayment probability
 *    - Minimal or no credit limit increase recommended
 *    - Typical customer profile: Low income, limited credit history, higher debt
 * 
 * Risk thresholds are set based on statistical analysis of financial defaults
 */

class RiskClassifier {
    
    // Risk level thresholds
    const HIGH_RISK_THRESHOLD = 40;      // Score below 40 = High Risk
    const MEDIUM_RISK_THRESHOLD = 70;    // Score 40-69 = Medium Risk
    const LOW_RISK_THRESHOLD = 100;      // Score 70+ = Low Risk
    
    /**
     * Classify risk level based on credit score
     * 
     * @param int $credit_score The calculated credit score (0-100)
     * @return array Risk classification with level and risk factors
     */
    public function classifyRisk($credit_score) {
        $classification = [
            'score' => $credit_score,
            'risk_level' => '',
            'risk_percentage' => 0,
            'description' => '',
            'recommendation' => '',
            'approval_likelihood' => ''
        ];
        
        if ($credit_score >= self::MEDIUM_RISK_THRESHOLD) {
            // LOW RISK CLASSIFICATION
            $classification['risk_level'] = 'Low Risk';
            $classification['risk_percentage'] = 100 - $credit_score; // Lower score = lower risk is inverse
            $classification['description'] = 'Customer demonstrates excellent financial health and strong repayment capacity.';
            $classification['recommendation'] = 'RECOMMENDED FOR APPROVAL - Significant credit limit increase justified.';
            $classification['approval_likelihood'] = 'Very High (90%+)';
        } 
        elseif ($credit_score >= self::HIGH_RISK_THRESHOLD) {
            // MEDIUM RISK CLASSIFICATION
            $classification['risk_level'] = 'Medium Risk';
            $classification['risk_percentage'] = 100 - $credit_score;
            $classification['description'] = 'Customer shows acceptable financial health. Credit limit increase possible with caution.';
            $classification['recommendation'] = 'CONDITIONAL APPROVAL - Moderate credit limit increase recommended with conditions.';
            $classification['approval_likelihood'] = 'Moderate (50-70%)';
        } 
        else {
            // HIGH RISK CLASSIFICATION
            $classification['risk_level'] = 'High Risk';
            $classification['risk_percentage'] = 100 - $credit_score;
            $classification['description'] = 'Customer shows financial constraints. Credit limit increase NOT recommended at this time.';
            $classification['recommendation'] = 'NOT RECOMMENDED - Suggest customer improve financial profile before reapplication.';
            $classification['approval_likelihood'] = 'Low (10-30%)';
        }
        
        return $classification;
    }
    
    /**
     * Get risk factors based on score components
     * 
     * @param array $score_components Individual component scores
     * @return array List of key risk factors affecting the classification
     */
    public function identifyRiskFactors($score_components) {
        $risk_factors = [];
        
        // Analyze income score
        if ($score_components['income_score'] < 10) {
            $risk_factors[] = [
                'factor' => 'Low Income',
                'severity' => 'High',
                'details' => 'Monthly income below average. Limited repayment capacity.'
            ];
        }
        
        // Analyze credit limit score
        if ($score_components['credit_limit_score'] < 5) {
            $risk_factors[] = [
                'factor' => 'Limited Credit History',
                'severity' => 'Medium',
                'details' => 'Low or no existing credit limit. New customer risk.'
            ];
        }
        
        // Analyze education score
        if ($score_components['education_score'] < 10) {
            $risk_factors[] = [
                'factor' => 'Limited Education',
                'severity' => 'Low',
                'details' => 'Lower education level may affect financial decision-making.'
            ];
        }
        
        // Analyze age score
        if ($score_components['age_score'] < 8) {
            $risk_factors[] = [
                'factor' => 'Age-Related Risk',
                'severity' => 'Low',
                'details' => 'Very young or approaching retirement age.'
            ];
        }
        
        return $risk_factors;
    }
    
    /**
     * Generate risk assessment summary
     * 
     * @param int $credit_score The credit score
     * @param array $customer_data Customer information
     * @return string HTML-formatted risk assessment
     */
    public function generateRiskAssessment($credit_score, $customer_data) {
        $classification = $this->classifyRisk($credit_score);
        
        $assessment = "<div class='risk-assessment-summary'>";
        $assessment .= "<h3>Risk Assessment Summary</h3>";
        $assessment .= "<p><strong>Overall Credit Score:</strong> <span class='score-badge score-" . strtolower(str_replace(' ', '-', $classification['risk_level'])) . "'>" . $credit_score . "/100</span></p>";
        $assessment .= "<p><strong>Risk Level:</strong> <span class='risk-badge risk-" . strtolower(str_replace(' ', '-', $classification['risk_level'])) . "'>" . $classification['risk_level'] . "</span></p>";
        $assessment .= "<p><strong>Description:</strong> " . htmlspecialchars($classification['description']) . "</p>";
        $assessment .= "<p><strong>Recommendation:</strong> " . htmlspecialchars($classification['recommendation']) . "</p>";
        $assessment .= "<p><strong>Approval Likelihood:</strong> " . htmlspecialchars($classification['approval_likelihood']) . "</p>";
        $assessment .= "</div>";
        
        return $assessment;
    }
}
?>
