from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
from ml.api import get_prediction
import os

app = Flask(__name__)
CORS(app)

@app.route('/', methods=['GET'])
def index():
    # Try to serve index.html from the root folder, or from pages/ if it's there
    if os.path.exists('index.html'):
        return send_file('index.html')
    elif os.path.exists('pages/index.html'):
        return send_file('pages/index.html')
    return "Finpredict API is Running"

@app.route('/predict', methods=['POST'])
def predict():
    try:
        data = request.get_json()
        result = get_prediction(data)
        
        status_code = 200
        if "error" in result:
            status_code = 400
            if "not loaded" in result["error"]:
                status_code = 503
        
        return jsonify(result), status_code
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(debug=True, host='0.0.0.0', port=port)
