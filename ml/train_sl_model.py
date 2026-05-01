"""
FinPredict — Sri Lanka ML Model Training
=========================================
Trains a Random Forest model on the generated Sri Lanka
synthetic credit dataset with all 6 form inputs as features.

Features  : age, gender, education_level, marital_status,
            monthly_income, existing_credit_limit
Target 1  : credit_approved  → RandomForestClassifier (credit score)
Target 2  : recommended_limit → RandomForestRegressor  (limit in LKR)
"""

import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier, RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import accuracy_score, r2_score
import joblib
import os

np.random.seed(42)

def train_and_save():
    print("=" * 55)
    print("  FinPredict — Sri Lanka ML Training Pipeline")
    print("=" * 55)

    # ── 1. Load Dataset ──────────────────────────────────────
    dataset_path = os.path.join(os.path.dirname(__file__), 'sl_credit_dataset.csv')
    print(f"\n[1/5] Loading dataset: {dataset_path}")
    df = pd.read_csv(dataset_path)
    print(f"      Records : {len(df):,}")
    print(f"      Columns : {list(df.columns)}")

    # ── 2. Encode Categoricals ───────────────────────────────
    print("\n[2/5] Encoding categorical features...")

    le_gender    = LabelEncoder()
    le_education = LabelEncoder()
    le_marital   = LabelEncoder()

    df['gender_enc']    = le_gender.fit_transform(df['gender'])
    df['education_enc'] = le_education.fit_transform(df['education_level'])
    df['marital_enc']   = le_marital.fit_transform(df['marital_status'])

    print(f"      Gender classes    : {list(le_gender.classes_)}")
    print(f"      Education classes : {list(le_education.classes_)}")
    print(f"      Marital classes   : {list(le_marital.classes_)}")

    # ── 3. Prepare Feature Matrix ────────────────────────────
    FEATURES = [
        'age',
        'gender_enc',
        'education_enc',
        'marital_enc',
        'monthly_income',
        'existing_credit_limit'
    ]

    X = df[FEATURES]

    # ── 4. Train Credit Approval Classifier ─────────────────
    print("\n[3/5] Training Credit Score Classifier (RandomForest)...")
    y_approval = df['credit_approved']

    X_train, X_test, y_train, y_test = train_test_split(
        X, y_approval, test_size=0.2, random_state=42
    )

    clf = RandomForestClassifier(
        n_estimators=200,
        max_depth=12,
        min_samples_leaf=5,
        random_state=42,
        n_jobs=-1
    )
    clf.fit(X_train, y_train)
    acc = accuracy_score(y_test, clf.predict(X_test))
    print(f"      Accuracy (test set): {acc*100:.2f}%")

    # Feature importance
    importances = dict(zip(FEATURES, clf.feature_importances_))
    print("      Feature importances:")
    for feat, imp in sorted(importances.items(), key=lambda x: -x[1]):
        print(f"        {feat:30s}: {imp:.4f}")

    # ── 5. Train Credit Limit Regressor ─────────────────────
    print("\n[4/5] Training Credit Limit Regressor (RandomForest)...")
    df_approved = df[df['credit_approved'] == 1].copy()
    X_lim = df_approved[FEATURES]
    y_lim = df_approved['recommended_limit']

    X_lim_train, X_lim_test, y_lim_train, y_lim_test = train_test_split(
        X_lim, y_lim, test_size=0.2, random_state=42
    )

    reg = RandomForestRegressor(
        n_estimators=200,
        max_depth=12,
        min_samples_leaf=5,
        random_state=42,
        n_jobs=-1
    )
    reg.fit(X_lim_train, y_lim_train)
    r2 = r2_score(y_lim_test, reg.predict(X_lim_test))
    print(f"      R2 Score (test set): {r2:.4f}")

    # ── 6. Save Artifacts ────────────────────────────────────
    print("\n[5/5] Saving model artifacts...")

    artifacts = {
        'classifier':    clf,
        'regressor':     reg,
        'features':      FEATURES,
        'encoders': {
            'gender':    le_gender,
            'education': le_education,
            'marital':   le_marital,
        }
    }

    model_path = os.path.join(os.path.dirname(__file__), 'sl_credit_model.pkl')
    joblib.dump(artifacts, model_path)
    print(f"      Saved to: {model_path}")

    print("\n" + "=" * 55)
    print("  Training Complete!")
    print(f"  Classifier Accuracy : {acc*100:.2f}%")
    print(f"  Regressor R2        : {r2:.4f}")
    print("=" * 55)

if __name__ == '__main__':
    train_and_save()
