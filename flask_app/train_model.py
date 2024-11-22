import pandas as pd
import numpy as np
from statsmodels.tsa.statespace.sarimax import SARIMAX
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sqlalchemy import create_engine, text
import logging
import json
import joblib
from itertools import product

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

logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s %(levelname)s %(message)s',
                    handlers=[
                        logging.FileHandler("train_model.log"),
                        logging.StreamHandler()
                    ])

# ================================
# Helper Functions
# ================================

def fetch_sales_data():
    query = "SELECT * FROM laundry"
    logging.info("Fetching sales data from the database.")
    df = pd.read_sql(text(query), engine)
    logging.info(f"Fetched {len(df)} records from the database.")
    return df

def preprocess_data(df):
    logging.info("Starting data preprocessing.")
    df['DATE'] = pd.to_datetime(df['DATE'], format='%Y-%m-%d')
    df = df.sort_values('DATE').set_index('DATE')
    df_monthly = df.resample('M').sum()
    
    if df_monthly.isnull().sum().sum() > 0:
        df_monthly = df_monthly.ffill()

    df_monthly['Month'] = df_monthly.index.month
    df_monthly['Quarter'] = df_monthly.index.quarter
    df_monthly['Prev_Month_Sales'] = df_monthly['TOTAL'].shift(1).fillna(0)
    df_monthly['Rolling_Avg_3'] = df_monthly['TOTAL'].rolling(window=3).mean().fillna(0)
    
    logging.info("Data preprocessing completed.")
    return df_monthly

def optimize_sarimax(df, param_grid, exog_columns):
    y = df['TOTAL']
    exog = df[exog_columns] if exog_columns else None

    best_score = float('inf')
    best_params = None
    best_model = None

    for params in param_grid:
        try:
            model = SARIMAX(y, exog=exog, order=params[0], seasonal_order=params[1], enforce_stationarity=False, enforce_invertibility=False)
            results = model.fit(disp=False)
            aic = results.aic
            if aic < best_score:
                best_score = aic
                best_params = params
                best_model = results
        except Exception as e:
            logging.warning(f"Failed for parameters {params}: {e}")
            continue

    logging.info(f"Best parameters: {best_params}, AIC: {best_score}")
    return best_model

def evaluate_model(results, df, exog_columns):
    y = df['TOTAL']
    exog = df[exog_columns] if exog_columns else None
    predictions = results.get_prediction(start=df.index[0], end=df.index[-1], exog=exog, dynamic=False)
    y_pred = predictions.predicted_mean
    y_true = y

    mae = mean_absolute_error(y_true, y_pred)
    mse = mean_squared_error(y_true, y_pred)
    r2 = r2_score(y_true, y_pred)
    logging.info(f"Evaluation Metrics - MAE: {mae:.2f}, MSE: {mse:.2f}, R²: {r2:.2f}")
    
    metrics = {'mae': mae, 'mse': mse, 'r2': r2}
    with open('model_metrics.json', 'w') as f:
        json.dump(metrics, f)
    logging.info("Model metrics saved as 'model_metrics.json'.")
    return metrics, y_pred

def save_model(results):
    joblib.dump(results, 'sarimax_model.pkl')
    logging.info("SARIMAX model saved as 'sarimax_model.pkl'.")

# ================================
# Main Function
# ================================

def main():
    df = fetch_sales_data()
    df_monthly = preprocess_data(df)

    param_grid = list(product([(1, 1, 1), (2, 1, 2), (3, 1, 3)],  # ARIMA orders
                              [(1, 1, 1, 12), (2, 1, 2, 12)]))  # Seasonal orders
    exog_columns = ['Month', 'Quarter', 'Prev_Month_Sales', 'Rolling_Avg_3']
    best_model = optimize_sarimax(df_monthly, param_grid, exog_columns)
    metrics, y_pred = evaluate_model(best_model, df_monthly, exog_columns)
    save_model(best_model)

if __name__ == '__main__':
    main()
