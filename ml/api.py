from flask import Flask, request, jsonify, Response # type: ignore
from flask_cors import CORS # type: ignore
import joblib # type: ignore
import pandas as pd # type: ignore
import numpy as np # type: ignore
import os
from typing import Any, Dict, Optional, cast

app = Flask(__name__)
CORS(app) # Enable CORS for all routes

ModelArtifacts = Dict[str, Any]
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'credit_model.pkl')
model_artifacts: Optional[ModelArtifacts] = None

def load_model():
    global model_artifacts
    if os.path.exists(MODEL_PATH):
        try:
            model_artifacts = cast(Optional[ModelArtifacts], joblib.load(MODEL_PATH))
            print("✅ Model loaded successfully from Kaggle-trained dataset.")
        except Exception as e:
            print(f"❌ Error loading model: {e}")
            model_artifacts = None
    else:
        print("⚠️ Model file not found! Please run train_model.py first.")
        model_artifacts = None

# Initial load
load_model()

@app.route('/', methods=['GET'])
def index() -> str:
    return """
    <html>
        <head>
            <title>FinPredict AI Server</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; background-color: #f4f6f9; }
                .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
                h1 { color: #27ae60; }
                p { color: #555; }
                .status { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; display: inline-block; margin-top: 20px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>🤖 FinPredict AI Brain (Kaggle Edition)</h1>
                <p>The Python ML Engine is running successfully on a real-world Kaggle dataset.</p>
                <div class="status">✅ SYSTEM ONLINE</div>
                <p style="margin-top: 30px; font-size: 0.9em;">
                    <a href="http://localhost/finpredict-sample/pages/index.php">Go to Main Application</a>
                </p>
            </div>
        </body>
    </html>
    """

@app.route('/predict', methods=['POST'])
def predict() -> Response:
    try:
        if model_artifacts is None:
            return cast(Response, jsonify({"error": "Model not loaded. Please ensure credit_model.pkl exists."})), 503

        data: Dict[str, Any] = request.get_json()
        if not data:
            return cast(Response, jsonify({"error": "No input data provided"})), 400
        
        # 1. Parse Input from frontend form
        age = int(data.get('age', 25))
        monthly_income = float(data.get('monthly_income', 50000))
        existing_credit_limit = float(data.get('existing_credit_limit', 0))
        marital_status = data.get('marital_status', 'Single')
        
        # 2. Map Frontend Inputs to Kaggle Dataset Features
        # person_age = age
        # person_income = monthly_income * 12 (Annual)
        person_income = monthly_income * 12
        
        # Mapping marital status to home ownership as a proxy for the Kaggle dataset
        marital_to_home = {
            'Single': 'RENT',
            'Married': 'MORTGAGE',
            'Divorced': 'OWN',
            'Widowed': 'OTHER'
        }
        person_home_ownership = marital_to_home.get(marital_status, 'RENT')
        
        # cb_person_cred_hist_length = age - 18
        cb_person_cred_hist_length = max(1, age - 18)
        
        # loan_amnt = existing_credit_limit
        loan_amnt = existing_credit_limit if existing_credit_limit > 0 else 1000.0

        # Encode home ownership
        encoders = model_artifacts.get('encoders', {})
        home_classes = encoders.get('home_ownership_classes', ['RENT', 'OWN', 'MORTGAGE', 'OTHER'])
        # A simple hack to get the index (LabelEncoder equivalent)
        if person_home_ownership in home_classes:
            home_encoded = home_classes.index(person_home_ownership)
        else:
            home_encoded = 0

        # Create DataFrame matching Kaggle features
        input_data = {
            'person_age': [age],
            'person_income': [person_income],
            'person_home_ownership': [home_encoded],
            'loan_amnt': [loan_amnt],
            'cb_person_cred_hist_length': [cb_person_cred_hist_length]
        }
        input_df = pd.DataFrame(input_data)
        
        # 3. Predict
        model_score = model_artifacts.get('model_score')
        model_limit = model_artifacts.get('model_limit')
        
        if model_score is None or model_limit is None:
             return cast(Response, jsonify({"error": "Model missing from artifacts"})), 500

        # Get probability of NON-DEFAULT (class 0)
        prob_non_default = model_score.predict_proba(input_df)[0][0]
        
        # Map 0.0 - 1.0 probability to a 0 - 100 Credit Score Score
        predicted_score = float(prob_non_default * 100)
        
        # Predict Recommended Limit (Wait, it might predict high values, let's limit it)
        predicted_limit_val = model_limit.predict(input_df)[0]
        predicted_limit = float(predicted_limit_val)
        
        # Over-ride if high risk
        if prob_non_default < 0.5:
             predicted_limit = float(existing_credit_limit) # No increase
        
        # 4. Post-process (Determine Risk Level)
        risk_level = "High Risk"
        if predicted_score >= 70:
            risk_level = "Low Risk"
        elif predicted_score >= 40:
            risk_level = "Medium Risk"
            
        # 5. Generate Response
        rounded_limit = float(f"{predicted_limit:.2f}")
        
        response_data = {
            "credit_score": int(predicted_score),
            "risk_level": risk_level,
            "recommended_limit": rounded_limit,
            "probabilities": {
                "approval_likelihood": min(predicted_score, 99) 
            },
            "ml_metadata": {
                "model_type": "RandomForestClassifier",
                "features_used": 5,
                "version": "kaggle_v1.0"
            }
        }
        
        return jsonify(response_data)
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True, port=5000)
