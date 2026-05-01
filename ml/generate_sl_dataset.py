"""
FinPredict — Sri Lanka Synthetic Credit Dataset Generator
=========================================================
Generates 15,000 realistic Sri Lankan customer credit profiles
based on Sri Lanka 2024 demographic and economic statistics.

Income Distribution Reference:
  - CBSL Annual Report 2023
  - Dept. of Census & Statistics Sri Lanka 2022
  - LBO Sri Lanka Salary Survey 2024
"""

import pandas as pd
import numpy as np
import os

np.random.seed(42)
N = 15000  # number of records

# ─── 1. DEMOGRAPHIC FEATURES ─────────────────────────────────────────────────

# Age: working population 18-70, peak at 28-45
age = np.random.normal(loc=36, scale=11, size=N).clip(18, 70).astype(int)

# Gender: Sri Lanka workforce ~57% Male, 43% Female
gender = np.random.choice(['Male', 'Female'], size=N, p=[0.57, 0.43])

# Education: based on DCS 2022 educational attainment
education = np.random.choice(
    ['High School', 'Bachelor', 'Master', 'PhD'],
    size=N,
    p=[0.45, 0.38, 0.13, 0.04]
)

# Marital status: Sri Lanka 2022 census
marital = np.random.choice(
    ['Single', 'Married', 'Divorced', 'Widowed'],
    size=N,
    p=[0.30, 0.60, 0.06, 0.04]
)

# ─── 2. INCOME (LKR/month) ───────────────────────────────────────────────────
# Right-skewed: most earn 20k-80k, few earn 150k+
# Segments: very_low / low / medium / high / very_high

income_segment = np.random.choice(
    ['very_low', 'low', 'medium', 'high', 'very_high'],
    size=N,
    p=[0.15, 0.30, 0.35, 0.15, 0.05]
)

monthly_income = np.zeros(N)
for i, seg in enumerate(income_segment):
    if seg == 'very_low':
        monthly_income[i] = np.random.uniform(10000, 22000)
    elif seg == 'low':
        monthly_income[i] = np.random.uniform(22000, 45000)
    elif seg == 'medium':
        monthly_income[i] = np.random.uniform(45000, 90000)
    elif seg == 'high':
        monthly_income[i] = np.random.uniform(90000, 180000)
    else:  # very_high
        monthly_income[i] = np.random.uniform(180000, 400000)

monthly_income = monthly_income.round(2)

# ─── 3. EXISTING CREDIT LIMIT (LKR) ─────────────────────────────────────────
# 20% have no existing credit (new customers)
# Others: limit is 0.3x to 2.5x monthly income

has_credit = np.random.choice([0, 1], size=N, p=[0.20, 0.80])
credit_ratio = np.random.uniform(0.3, 2.5, size=N)
existing_credit_limit = np.where(
    has_credit == 0,
    0.0,
    (monthly_income * credit_ratio).round(-2)  # round to nearest 100
)

# ─── 4. CREDIT APPROVAL DECISION ─────────────────────────────────────────────
# This is the TARGET variable — encodes our domain knowledge

def compute_approval_score(inc, ex_limit, edu, mar, age_val, gen):
    score = 0.0

    # Income tier (0-30 pts)
    if inc >= 150000:   score += 30
    elif inc >= 80000:  score += 24
    elif inc >= 45000:  score += 18
    elif inc >= 22000:  score += 10
    else:               score += 3

    # Credit-to-income ratio (0-25 pts)
    ratio = ex_limit / inc if inc > 0 else 0
    if ex_limit == 0:   score += 3
    elif ratio <= 0.5:  score += 8
    elif ratio <= 1.0:  score += 15
    elif ratio <= 2.0:  score += 20
    else:               score += 25

    # Education (0-20 pts)
    edu_pts = {'High School': 5, 'Bachelor': 15, 'Master': 18, 'PhD': 20}
    score += edu_pts.get(edu, 5)

    # Marital status (0-15 pts)
    mar_pts = {'Married': 12, 'Single': 10, 'Widowed': 9, 'Divorced': 8}
    score += mar_pts.get(mar, 10)

    # Age (0-10 pts)
    if 30 <= age_val <= 55:  score += 10
    elif age_val < 30:       score += 6
    else:                    score += 7

    return score  # 0–100

# Calculate scores for all records
scores = np.array([
    compute_approval_score(
        monthly_income[i], existing_credit_limit[i],
        education[i], marital[i], age[i], gender[i]
    )
    for i in range(N)
])

# Approval: score >= 40 approved (with small noise for realism)
noise = np.random.normal(0, 3, size=N)
adjusted_scores = scores + noise
credit_approved = (adjusted_scores >= 40).astype(int)

# ─── 5. RECOMMENDED LIMIT (LKR) ──────────────────────────────────────────────
# Only for approved customers — based on effective income

edu_factor  = {'High School': 0.90, 'Bachelor': 1.00, 'Master': 1.08, 'PhD': 1.15}
mar_factor  = {'Single': 1.00, 'Married': 0.82, 'Divorced': 0.90, 'Widowed': 0.88}

def age_factor(a):
    if a <= 29:  return 1.00
    if a <= 44:  return 0.95
    if a <= 59:  return 0.85
    return 0.75

# Risk multiplier based on score
def risk_multiplier(s):
    if s >= 70:  return 4.0   # Low Risk
    if s >= 40:  return 2.5   # Medium Risk
    return 1.0                 # High Risk

recommended_limit = np.zeros(N)
for i in range(N):
    if credit_approved[i] == 1:
        eff_income = (
            monthly_income[i]
            * edu_factor.get(education[i], 1.0)
            * mar_factor.get(marital[i], 1.0)
            * age_factor(age[i])
        )
        ceiling = eff_income * risk_multiplier(scores[i])

        if existing_credit_limit[i] > 0:
            # Percentage increase from existing
            if scores[i] >= 90:    pct = 1.50
            elif scores[i] >= 80:  pct = 1.40
            elif scores[i] >= 70:  pct = 1.30
            elif scores[i] >= 60:  pct = 1.25
            elif scores[i] >= 50:  pct = 1.15
            else:                  pct = 1.10
            proposed = existing_credit_limit[i] * pct
        else:
            # New customer — start from 1.5x monthly income
            proposed = max(15000, monthly_income[i] * 1.5)

        # Cap at ceiling and round to nearest 100
        recommended_limit[i] = round(min(proposed, ceiling) / 100) * 100
    else:
        recommended_limit[i] = existing_credit_limit[i]  # No increase

# ─── 6. BUILD DATAFRAME & SAVE ───────────────────────────────────────────────

df = pd.DataFrame({
    'age':                    age,
    'gender':                 gender,
    'education_level':        education,
    'marital_status':         marital,
    'monthly_income':         monthly_income,
    'existing_credit_limit':  existing_credit_limit,
    'credit_score_raw':       scores.round(2),      # ground truth score
    'credit_approved':        credit_approved,       # classification target
    'recommended_limit':      recommended_limit      # regression target
})

out_path = os.path.join(os.path.dirname(__file__), 'sl_credit_dataset.csv')
df.to_csv(out_path, index=False)

print(f"Dataset generated: {N} records")
print(f"Approval rate   : {credit_approved.mean()*100:.1f}%")
print(f"Income range    : LKR {monthly_income.min():,.0f} – {monthly_income.max():,.0f}")
print(f"Avg credit limit: LKR {recommended_limit[credit_approved==1].mean():,.0f}")
print(f"Saved to        : {out_path}")
