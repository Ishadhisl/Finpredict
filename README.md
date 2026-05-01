# FinPredict — Intelligent Credit Assessment System

A university group project — **PHP + Python ML hybrid credit scoring system** calibrated for **Sri Lanka's financial context**.

---

## How It Works

```
Customer Form (6 inputs)
        │
        ▼
PHP: credit_score.php
  ├── Rule-based score (for display breakdown)
  └── Calls Python ML API ──────────────────────────────┐
                                                         ▼
                                              ml/api.py (Flask)
                                                  │
                                                  ▼
                                         sl_credit_model.pkl
                                    (RandomForest — 97.53% acc)
                                                  │
                                    ┌─────────────┴────────────┐
                                    ▼                          ▼
                            Credit Score (0–100)    Recommended Limit (LKR)
                                    │
                                    ▼
                            results.php (display)
```

---

## ML Model Details

| Property | Value |
|---|---|
| Dataset | Sri Lanka Synthetic (15,000 records) |
| Algorithm | RandomForestClassifier + RandomForestRegressor |
| Classifier Accuracy | **97.53%** |
| Regressor R² Score | **0.9929** |
| Features Used | 6 (all form inputs) |

### Features

| Feature | Type | Role |
|---|---|---|
| `age` | int | Medical expense proxy |
| `gender` | categorical | Demographic |
| `education_level` | categorical | Financial literacy |
| `marital_status` | categorical | Family expense proxy |
| `monthly_income` | float (LKR) | Primary capacity indicator |
| `existing_credit_limit` | float (LKR) | Bank trust track record |

### Feature Importances (from training)
```
existing_credit_limit  : 38.81%   ← most impactful
monthly_income         : 28.40%
education_level        : 23.29%
age                    :  6.23%
marital_status         :  2.58%
gender                 :  0.69%
```

---

## Dataset — Sri Lanka Synthetic

Since public Sri Lanka bank credit data is not available (CBSL private), a **synthetic dataset calibrated to Sri Lanka statistics** was generated.

**Income Distribution (LKR/month):**
| Range | Segment | % of Population |
|---|---|---|
| 10,000 – 22,000 | Very Low | 15% |
| 22,000 – 45,000 | Low | 30% |
| 45,000 – 90,000 | Medium | 35% |
| 90,000 – 180,000 | High | 15% |
| 180,000+ | Very High | 5% |

**Approval Logic built into dataset:**
- Income tier → 0–30 pts
- Credit-to-income ratio → 0–25 pts
- Education level → 0–20 pts
- Marital status → 0–15 pts
- Age group → 0–10 pts
- **Total ≥ 40** → Approved

**Limit logic:**
```
effective_income = monthly_income × education_factor × marital_factor × age_factor
recommended_limit = min(existing_limit × increase_pct, effective_income × risk_multiplier)
```

---

## Project Structure

```
finpredict-sample/
├── app.py                         # Root Flask API entry point
├── requirements.txt               # Python dependencies
├── RUN_GUIDE.txt                  # Setup instructions
├── pages/
│   ├── index.php                  # Customer input form
│   └── results.php                # Score + limit display
├── includes/
│   ├── credit_score.php           # Scoring engine (calls ML API)
│   ├── credit_limit_calculator.php# Fallback limit logic
│   └── risk_classifier.php        # Risk classification (Low/Medium/High)
├── ml/
│   ├── generate_sl_dataset.py     # Sri Lanka dataset generator
│   ├── sl_credit_dataset.csv      # Generated 15,000 record dataset
│   ├── train_sl_model.py          # Model training script
│   ├── sl_credit_model.pkl        # Trained model artifacts
│   ├── api.py                     # Flask ML API (port 5000)
│   └── requirements.txt           # ML-specific dependencies
├── config/
│   └── db_connection.php          # MySQL connection
└── sql/
    └── schema.sql                 # Database schema
```

---

## Setup & Run

### 1. Database
```
Open XAMPP → Start Apache + MySQL
Go to: http://localhost/phpmyadmin
Create database: finpredict_db
Import: sql/schema.sql
```

### 2. Python ML Backend
```bash
cd C:\xampp\htdocs\finpredict-sample
pip install -r requirements.txt

# (One-time) Regenerate dataset & retrain:
python ml/generate_sl_dataset.py
python ml/train_sl_model.py

# Start ML server:
python app.py
```

### 3. Open Application
```
http://localhost/finpredict-sample/pages/index.php
```

---

## Risk Classification

| Score | Risk Level | Credit Limit Action |
|---|---|---|
| 70 – 100 | Low Risk | 30–50% increase |
| 40 – 69 | Medium Risk | 10–25% increase |
| 0 – 39 | High Risk | No increase |

---

*University Group Project — FinPredict Credit Limit Predictor*
