import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier, RandomForestRegressor
from sklearn.preprocessing import LabelEncoder
import joblib
import os

# Set random seed for reproducibility
np.random.seed(42)

def train_and_save_model():
    print("Starting ML Pipeline with Kaggle Dataset...")
    
    # 1. Load Local Kaggle Dataset (Credit Risk Dataset)
    dataset_path = os.path.join(os.path.dirname(__file__), 'credit_risk_dataset.csv')
    print(f"Loading dataset from {dataset_path}...")
    try:
        df = pd.read_csv(dataset_path)
    except Exception as e:
        print(f"Failed to load dataset: {e}\nPlease ensure 'credit_risk_dataset.csv' is in the 'ml' folder.")
        return
        
    print(f"Dataset loaded! Shape: {df.shape}")
    
    # 2. Preprocess
    print("Preprocessing data...")
    # Drop missing values
    df = df.dropna().copy()
    
    # We will map the frontend inputs to these Kaggle features:
    # age -> person_age
    # monthly_income -> person_income (annualized)
    # marital_status -> person_home_ownership (Synthetic mapping for compatibility)
    # existing_credit_limit -> loan_amnt
    # age -> cb_person_cred_hist_length (age - 18)
    
    # Let's keep these features for training
    features = ['person_age', 'person_income', 'person_home_ownership', 'loan_amnt', 'cb_person_cred_hist_length']
    
    X = df[features].copy()
    y_status = df['loan_status'] # 1 is Default, 0 is Non-Default
    
    # Encode person_home_ownership
    le_home = LabelEncoder()
    X['person_home_ownership'] = le_home.fit_transform(X['person_home_ownership'])
    
    # Model 1: Credit Score Predictor (Probability of Non-Default)
    # We use a Classifier to predict loan_status
    print("Training Random Forest Classifier for Credit Score...")
    model_score = RandomForestClassifier(n_estimators=100, max_depth=10, random_state=42)
    model_score.fit(X, y_status)
    acc = model_score.score(X, y_status)
    print(f"Credit Risk Model Accuracy: {acc:.4f}")
    
    # Model 2: Credit Limit Predictor
    # We will train a Regressor to predict the loan amount limit for people who did not default
    print("Training Random Forest Regressor for Credit Limit...")
    df_good = df[df['loan_status'] == 0].copy()
    X_limit = df_good[features].copy()
    X_limit['person_home_ownership'] = le_home.transform(X_limit['person_home_ownership'])
    y_limit = df_good['loan_amnt']
    
    model_limit = RandomForestRegressor(n_estimators=100, max_depth=10, random_state=42)
    model_limit.fit(X_limit, y_limit)
    r2 = model_limit.score(X_limit, y_limit)
    print(f"Credit Limit Model R2 Score: {r2:.4f}")
    
    # Save a sample of the transformed dataset for the user
    csv_path = os.path.join(os.path.dirname(__file__), 'finpredict_dataset.csv')
    df.head(5000).to_csv(csv_path, index=False)
    print(f"Sample dataset saved to {csv_path}")
    
    # 4. Save Artifacts
    print("Saving models...")
    artifacts = {
        'model_score': model_score,
        'model_limit': model_limit,
        'encoders': {
            'home_ownership_classes': list(le_home.classes_)
        }
    }
    
    model_path = os.path.join(os.path.dirname(__file__), 'credit_model.pkl')
    joblib.dump(artifacts, model_path)
    print(f"Models saved to {model_path}")
    print("✅ ML Pipeline Complete!")

if __name__ == "__main__":
    train_and_save_model()
