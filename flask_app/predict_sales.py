# predict_sales.py

import pandas as pd
import numpy as np
from flask import Flask, request, jsonify
from sklearn.linear_model import LinearRegression
from sqlalchemy import create_engine, text
from flask_cors import CORS
from functools import wraps
import logging
import json

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

# ================================
# Configuration
# ================================

# Database configuration
DB_HOST = 'localhost'       # Your database host
DB_PORT = '3306'            # Default MySQL port
DB_USER = 'root'            # Your database username
DB_PASSWORD = ''            # Your database password
DB_NAME = 'dbcapstone'      # Your database name

# API Key for authentication
API_KEY = 'testkey123'  # Replace with your actual API key

# Create a database connection string
DATABASE_URI = f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}'

# Initialize SQLAlchemy engine
engine = create_engine(DATABASE_URI)

# ================================
# Logging Configuration
# ================================

logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s %(levelname)s %(message)s',
                    handlers=[
                        logging.FileHandler("api.log"),
                        logging.StreamHandler()
                    ])

# ================================
# Helper Functions
# ================================

def load_model_metrics():
    """
    Load model evaluation metrics from 'model_metrics.json'.
    
    Returns:
        dict: Dictionary containing 'mae', 'mse', and 'r2' metrics.
    """
    try:
        with open('model_metrics.json', 'r') as f:
            metrics = json.load(f)
        logging.info("Model metrics loaded from 'model_metrics.json'.")
        return metrics
    except FileNotFoundError:
        logging.error("Model metrics file 'model_metrics.json' not found.")
        return None

# Load metrics at the start of the application
metrics = load_model_metrics()

def fetch_sales_data(year=None, month=None, day=None):
    """
    Fetch historical sales data from the database based on provided filters.

    Parameters:
        year (int): Year filter
        month (int): Month filter
        day (int): Day filter

    Returns:
        pd.DataFrame: DataFrame containing DATE and TOTAL columns
    """
    query = "SELECT DATE, TOTAL FROM laundry"
    conditions = []
    params = {}

    if year:
        conditions.append("YEAR(DATE) = :year")
        params['year'] = year
    if month:
        conditions.append("MONTH(DATE) = :month")
        params['month'] = month
    if day:
        conditions.append("DAY(DATE) = :day")
        params['day'] = day

    if conditions:
        query += " WHERE " + " AND ".join(conditions)

    logging.info(f"Executing query: {query} with params: {params}")
    df = pd.read_sql(text(query), engine, params=params)
    return df

def prepare_data(df):
    """
    Prepare data for linear regression.

    Parameters:
        df (pd.DataFrame): DataFrame containing DATE and TOTAL columns

    Returns:
        X (np.ndarray): Independent variables
        y (np.ndarray): Dependent variable (sales total)
        next_period_label (str): Label for the next period
        monthly_sales (pd.DataFrame): DataFrame with aggregated sales data
    """
    if df.empty:
        logging.warning("No data available for the given filters.")
        return None, None, None, None

    # Convert DATE to datetime and set as index
    df['DATE'] = pd.to_datetime(df['DATE'])
    df.set_index('DATE', inplace=True)

    # Resample data to monthly frequency, ensuring all months are included
    monthly_sales = df['TOTAL'].resample('M').sum()

    # Reindex to include all months between the min and max dates
    all_months = pd.date_range(start=monthly_sales.index.min(), end=monthly_sales.index.max(), freq='M')
    monthly_sales = monthly_sales.reindex(all_months, fill_value=0)

    # Reset index and prepare DataFrame
    monthly_sales = monthly_sales.reset_index()
    monthly_sales.rename(columns={'index': 'DATE', 'TOTAL': 'TOTAL'}, inplace=True)
    monthly_sales['Year'] = monthly_sales['DATE'].dt.year
    monthly_sales['Month'] = monthly_sales['DATE'].dt.month

    # Sort the DataFrame
    monthly_sales.sort_values('DATE', inplace=True)

    # Prepare X and y for regression
    X = monthly_sales[['Year', 'Month']].values
    y = monthly_sales['TOTAL'].values

    # Determine next period's label
    last_date = monthly_sales['DATE'].max()
    next_period_date = last_date + pd.DateOffset(months=1)
    next_period_label = next_period_date.strftime('%B %Y')

    logging.info(f"Prepared data for regression. Next period to predict: {next_period_label}")

    return X, y, next_period_label, monthly_sales

def train_model(X, y):
    """
    Train a linear regression model.

    Parameters:
        X (np.ndarray): Independent variables
        y (np.ndarray): Dependent variable

    Returns:
        model (LinearRegression): Trained linear regression model
    """
    model = LinearRegression()
    model.fit(X, y)
    logging.info("Linear regression model trained successfully.")
    return model

def save_prediction_to_db(prediction_date, predicted_sales):
    """
    Saves the predicted sales value for a given date to the database.
    
    Parameters:
        prediction_date (str): Date of the prediction in 'YYYY-MM-DD' format.
        predicted_sales (float): Predicted sales value.
    """
    query = text("REPLACE INTO sales_predictions (prediction_date, predicted_sales) VALUES (:prediction_date, :predicted_sales)")
    with engine.connect() as connection:
        connection.execute(query, {'prediction_date': prediction_date, 'predicted_sales': predicted_sales})
    logging.info(f"Saved prediction for {prediction_date}: ₱ {predicted_sales}")

def require_api_key(f):
    """
    Decorator to require API key authentication.

    Parameters:
        f (function): The route function

    Returns:
        function: The decorated function
    """
    @wraps(f)
    def decorated(*args, **kwargs):
        key = request.args.get('api_key')
        if key and key == API_KEY:
            return f(*args, **kwargs)
        else:
            logging.warning("Unauthorized access attempt.")
            return jsonify({'error': 'Unauthorized'}), 401
    return decorated

# ================================
# API Endpoints
# ================================

@app.route('/predict', methods=['GET'])
@require_api_key
def predict_sales():
    """
    API endpoint to predict next month's sales.

    Query Parameters:
        year (int): Year filter
        month (int): Month filter
        day (int): Day filter

    Returns:
        JSON response with predicted_sales, next_period, and accuracy metrics
    """
    try:
        # Retrieve query parameters
        year = request.args.get('year', default=None, type=int)
        month = request.args.get('month', default=None, type=int)
        day = request.args.get('day', default=None, type=int)

        logging.info(f"Received prediction request with year={year}, month={month}, day={day}")

        # Fetch data
        df = fetch_sales_data(year, month, day)

        # Prepare data
        X, y, next_period_label, monthly_sales = prepare_data(df)

        if X is None or y is None:
            logging.error("Insufficient data for prediction.")
            return jsonify({'error': 'Insufficient data for prediction.'}), 400

        # Train model
        model = train_model(X, y)

        # Predict next month's sales
        last_date = monthly_sales['DATE'].max()
        next_date = last_date + pd.DateOffset(months=1)
        next_period = np.array([[next_date.year, next_date.month]])
        predicted_sales = model.predict(next_period)[0]
        predicted_sales = max(predicted_sales, 0)  # Ensure non-negative

        # Round the prediction to 2 decimal places
        predicted_sales = round(predicted_sales, 2)

        logging.info(f"Predicted sales for {next_period_label}: ₱ {predicted_sales}")

        # Save the prediction to the database
        save_prediction_to_db(next_date.strftime('%Y-%m-%d'), predicted_sales)

        # Load precomputed metrics
        latest_mae = metrics.get('mae') if metrics else None
        latest_mse = metrics.get('mse') if metrics else None
        latest_r2 = metrics.get('r2') if metrics else None

        return jsonify({
            'predicted_sales': predicted_sales,
            'next_period': next_period_label,
            'mae': latest_mae,
            'mse': latest_mse,
            'r2': latest_r2
        }), 200

    except Exception as e:
        logging.exception("An error occurred during prediction.")
        return jsonify({'error': f'An error occurred: {str(e)}'}), 500

@app.route('/')
def home():
    return "Flask Sales Prediction Service is running."

# ================================
# Run the Flask App
# ================================

if __name__ == '__main__':
    # For development purposes; use a production server for deployment
    app.run(host='0.0.0.0', port=5000, debug=True)
