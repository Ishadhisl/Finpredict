from flask import Flask, request, jsonify, Response
from flask_cors import CORS
import joblib
import pandas as pd
import numpy as np
import os
from typing import Any, Dict, Optional, cast

app = Flask(__name__)
CORS(app)

ModelArtifacts = Dict[str, Any]
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'sl_credit_model.pkl')
model_artifacts: Optional[ModelArtifacts] = None


def load_model():
    global model_artifacts
    if os.path.exists(MODEL_PATH):
        try:
            model_artifacts = joblib.load(MODEL_PATH)
            print("SUCCESS: Sri Lanka ML model loaded.")
        except Exception as e:
            print(f"ERROR: Could not load model: {e}")
            model_artifacts = None
    else:
        print("WARNING: sl_credit_model.pkl not found. Run train_sl_model.py first.")
        model_artifacts = None


load_model()


def get_prediction(data: Dict[str, Any]) -> Dict[str, Any]:
    global model_artifacts
    if model_artifacts is None:
        load_model()
    if model_artifacts is None:
        return {"error": "Model not loaded. Please run train_sl_model.py first."}
    if not data:
        return {"error": "No input data provided"}

    try:
        # ── Parse all 6 inputs ──────────────────────────────
        age                   = int(data.get('age', 30))
        gender                = str(data.get('gender', 'Male'))
        education_level       = str(data.get('education_level', 'Bachelor'))
        marital_status        = str(data.get('marital_status', 'Single'))
        monthly_income        = float(data.get('monthly_income', 50000))
        existing_credit_limit = float(data.get('existing_credit_limit', 0))

        # ── Retrieve encoders ───────────────────────────────
        encoders     = model_artifacts['encoders']
        le_gender    = encoders['gender']
        le_education = encoders['education']
        le_marital   = encoders['marital']

        # Safely encode — unknown values default to first class
        def safe_encode(encoder, value):
            if value in encoder.classes_:
                return int(encoder.transform([value])[0])
            return 0

        gender_enc    = safe_encode(le_gender,    gender)
        education_enc = safe_encode(le_education, education_level)
        marital_enc   = safe_encode(le_marital,   marital_status)

        # ── Build feature row ───────────────────────────────
        input_df = pd.DataFrame([{
            'age':                    age,
            'gender_enc':             gender_enc,
            'education_enc':          education_enc,
            'marital_enc':            marital_enc,
            'monthly_income':         monthly_income,
            'existing_credit_limit':  existing_credit_limit
        }])

        clf = model_artifacts['classifier']
        reg = model_artifacts['regressor']

        # ── Credit Score (0-100) from approval probability ──
        prob_approved = clf.predict_proba(input_df)[0][1]   # P(approved)
        credit_score  = int(round(prob_approved * 100))

        # ── Risk Level ──────────────────────────────────────
        if credit_score >= 70:
            risk_level = "Low Risk"
        elif credit_score >= 40:
            risk_level = "Medium Risk"
        else:
            risk_level = "High Risk"

        # ── Recommended Limit (LKR) ──────────────────────────
        predicted_limit = float(reg.predict(input_df)[0])
        predicted_limit = round(predicted_limit / 100) * 100   # round to 100s

        # High-risk override: never increase limit
        if prob_approved < 0.40:
            predicted_limit = existing_credit_limit

        return {
            "credit_score":       credit_score,
            "risk_level":         risk_level,
            "recommended_limit":  predicted_limit,
            "probabilities": {
                "approval_likelihood": min(credit_score, 99)
            },
            "ml_metadata": {
                "model_type":    "RandomForestClassifier (Sri Lanka Dataset)",
                "features_used": 6,
                "accuracy":      "97.53%",
                "version":       "sl_v2.0"
            },
            "is_ml_generated": True
        }

    except Exception as e:
        return {"error": str(e)}


@app.route('/', methods=['GET'])
def index() -> str:
    return """
    <html>
        <head><title>FinPredict AI Server</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center;
                   padding-top: 50px; background: #f4f6f9; }
            .card { background: white; padding: 30px; border-radius: 10px;
                    box-shadow: 0 4px 6px rgba(0,0,0,.1);
                    max-width: 520px; margin: 0 auto; }
            h1 { color: #27ae60; }
            .status { background: #d4edda; color: #155724; padding: 10px;
                      border-radius: 5px; display: inline-block;
                      margin-top: 20px; font-weight: bold; }
            .badge { background: #2980b9; color: white; padding: 4px 10px;
                     border-radius: 12px; font-size: 0.85em; }
        </style></head>
        <body>
            <div class="card">
                <h1>FinPredict AI Server</h1>
                <p>Sri Lanka ML Credit Scoring Engine</p>
                <p><span class="badge">Model: Sri Lanka Synthetic Dataset</span></p>
                <p><span class="badge">Accuracy: 97.53%</span>
                   <span class="badge" style="margin-left:6px">Features: 6</span></p>
                <div class="status">SYSTEM ONLINE</div>
            </div>
        </body>
    </html>
    """


@app.route('/predict', methods=['POST'])
def predict() -> Response:
    try:
        data: Dict[str, Any] = request.get_json()
        result = get_prediction(data)

        if "error" in result:
            code = 503 if "not loaded" in result["error"] else 400
            return cast(Response, jsonify(result)), code

        return jsonify(result)

    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == '__main__':
    app.run(debug=True, port=5000)
