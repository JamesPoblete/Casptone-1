import pandas as pd
import numpy as np
from flask import Flask, request, jsonify
from sqlalchemy import create_engine, text
from flask_cors import CORS
import logging
from statsmodels.tsa.statespace.sarimax import SARIMAXResults
import joblib
import warnings
from datetime import datetime, timedelta

warnings.filterwarnings("ignore")  # Suppress warnings

# Flask app setup
app = Flask(__name__)
CORS(app)

# ================================
# Configuration
# ================================

DB_HOST = 'localhost'
DB_PORT = '3306'
DB_USER = 'root'
DB_PASSWORD = ''
DB_NAME = 'dbcapstone'

DATABASE_URI = f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
engine = create_engine(DATABASE_URI)

# Setup logging
logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s - %(levelname)s - %(message)s',
                    handlers=[logging.FileHandler("api.log"), logging.StreamHandler()])

# ================================
# Helper Functions
# ================================

def fetch_sales_data():
    """
    Fetch all historical sales data from the database.
    """
    query = "SELECT * FROM laundry"
    logging.info("Fetching sales data from the database.")
    df = pd.read_sql(text(query), engine)
    logging.info(f"Fetched {len(df)} records from the database.")
    return df

def preprocess_data(df):
    """
    Preprocess the sales data for SARIMAX.
    """
    logging.info("Starting data preprocessing.")
    
    # Convert DATE to datetime
    try:
        df['DATE'] = pd.to_datetime(df['DATE'], format='%Y-%m-%d')
        logging.info("Successfully converted DATE to datetime format.")
    except Exception as e:
        logging.error(f"Error converting DATE column to datetime: {e}")
        raise
    
    df = df.sort_values('DATE').set_index('DATE')
    
    # Resample to monthly data
    df_monthly = df.resample('M').sum()
    
    # Handle missing values
    if df_monthly.isnull().sum().sum() > 0:
        logging.info("Filling missing values with forward-fill method.")
        df_monthly = df_monthly.ffill()
    
    # Generate exogenous variables
    df_monthly['Month'] = df_monthly.index.month
    df_monthly['Quarter'] = df_monthly.index.quarter
    
    # Lag feature: previous month's sales
    df_monthly['Prev_Month_Sales'] = df_monthly['TOTAL'].shift(1)
    df_monthly['Prev_Month_Sales'].fillna(0, inplace=True)
    
    # Rolling average (3 months)
    df_monthly['Rolling_Avg_3'] = df_monthly['TOTAL'].rolling(window=3).mean()
    df_monthly['Rolling_Avg_3'].fillna(0, inplace=True)
    
    logging.info("Data preprocessing completed.")
    return df_monthly

def generate_future_exog(df_monthly, forecast_date):
    """
    Generate exogenous variables for the forecast date.
    """
    logging.info("Generating future exogenous variables.")
    
    future_exog = pd.DataFrame(index=[forecast_date])
    
    # Month and Quarter
    future_exog['Month'] = forecast_date.month
    future_exog['Quarter'] = forecast_date.quarter
    
    # Prev_Month_Sales
    prev_month_date = forecast_date - pd.DateOffset(months=1)
    if prev_month_date in df_monthly.index:
        last_month_sales = df_monthly.loc[prev_month_date, 'TOTAL']
    else:
        last_month_sales = df_monthly['TOTAL'].mean()
    future_exog['Prev_Month_Sales'] = last_month_sales
    
    # Rolling_Avg_3
    past_data = df_monthly[df_monthly.index < forecast_date]
    if len(past_data) >= 3:
        rolling_avg_3 = past_data['TOTAL'].iloc[-3:].mean()
    else:
        rolling_avg_3 = past_data['TOTAL'].mean()
    future_exog['Rolling_Avg_3'] = rolling_avg_3 if not np.isnan(rolling_avg_3) else df_monthly['TOTAL'].mean()
    
    logging.info(f"Future exogenous variables:\n{future_exog}")
    return future_exog

def save_prediction_to_db(prediction_date, predicted_sales):
    try:
        query = text("""
            REPLACE INTO sales_predictions 
            (prediction_date, predicted_sales) 
            VALUES (:prediction_date, :predicted_sales)
        """)
        with engine.connect() as connection:
            connection.execute(query, {
                'prediction_date': prediction_date, 
                'predicted_sales': predicted_sales
            })
        logging.info(f"Saved prediction for {prediction_date}: ₱ {predicted_sales}")
    except Exception as e:
        logging.error(f"Error saving prediction to the database: {e}")
        raise

# ================================
# API Endpoints
# ================================

@app.route('/predict', methods=['GET'])
def predict_sales():
    try:
        # Load the pre-trained SARIMAX model
        try:
            results = joblib.load('sarimax_model.pkl')
            logging.info("Loaded pre-trained SARIMAX model.")
        except Exception as e:
            logging.error(f"Error loading SARIMAX model: {e}")
            return jsonify({'error': 'Model not found. Please train the model first.'}), 500

        # Fetch and preprocess data
        df = fetch_sales_data()
        if df.empty:
            return jsonify({'error': 'No sales data available'}), 400

        df_monthly = preprocess_data(df)

        # Define exogenous variables
        exog_columns = ['Month', 'Quarter', 'Prev_Month_Sales', 'Rolling_Avg_3']

        # Get 'year' and 'month' from query parameters
        year = request.args.get('year', type=int)
        month = request.args.get('month', type=int)

        if year and month:
            # Validate the date
            try:
                forecast_date = pd.Timestamp(year=year, month=month, day=1) + pd.offsets.MonthEnd(0)
                next_period_label = forecast_date.strftime('%B %Y')
            except Exception as e:
                logging.error(f"Invalid date provided: {e}")
                return jsonify({'error': 'Invalid year or month provided.'}), 400
        else:
            # Default to next month after the last date in the dataset
            last_date = df_monthly.index.max()
            forecast_date = last_date + pd.DateOffset(months=1)
            next_period_label = forecast_date.strftime('%B %Y')

        # Generate future exogenous variables
        future_exog = generate_future_exog(df_monthly, forecast_date)

        # Ensure exog_columns are in future_exog
        future_exog = future_exog[exog_columns]

        # Forecast sales
        forecast = results.forecast(steps=1, exog=future_exog)
        predicted_sales = max(forecast.iloc[0], 0)
        predicted_sales = round(predicted_sales, 2)

        # Save the prediction to the database
        save_prediction_to_db(forecast_date.strftime('%Y-%m-%d'), predicted_sales)

        logging.info(f"Predicted sales for {next_period_label}: ₱ {predicted_sales}")

        response = {
            'predicted_sales': predicted_sales,
            'next_period': next_period_label
        }

        return jsonify(response), 200

    except Exception as e:
        logging.exception("An error occurred during prediction.")
        return jsonify({'error': f'An error occurred: {str(e)}'}), 500

@app.route('/')
def home():
    return "Flask Sales Prediction Service with SARIMAX is running."

# ================================
# Run the Flask App
# ================================

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
