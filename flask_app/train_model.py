import pandas as pd
import numpy as np
from statsmodels.tsa.statespace.sarimax import SARIMAX
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sqlalchemy import create_engine, text
import logging
import json
import joblib
import warnings

# Suppress warnings
warnings.filterwarnings("ignore")

# ================================
# Configuration
# ================================

# Database configuration
DB_HOST = 'localhost'
DB_PORT = '3306'
DB_USER = 'root'
DB_PASSWORD = ''
DB_NAME = 'dbcapstone'

# Create a database connection string
DATABASE_URI = f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}'

# Initialize SQLAlchemy engine
engine = create_engine(DATABASE_URI)

# Logging configuration
logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s %(levelname)s %(message)s',
                    handlers=[logging.FileHandler("train_model.log"), logging.StreamHandler()])

# ================================
# Helper Functions
# ================================

def smape(y_true, y_pred):
    """
    Compute Symmetric Mean Absolute Percentage Error (SMAPE).
    
    Parameters:
        y_true (array-like): True values.
        y_pred (array-like): Predicted values.
        
    Returns:
        float: SMAPE value.
    """
    numerator = np.abs(y_true - y_pred)
    denominator = (np.abs(y_true) + np.abs(y_pred)) / 2
    smape_value = np.mean(numerator / denominator) * 100
    return smape_value

def fetch_sales_data():
    """
    Fetch all historical sales data from the database.
    """
    query = "SELECT * FROM laundry"  # Ensure that 'laundry' table contains all necessary features
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
    
    # Log transform the 'TOTAL' to stabilize variance
    df_monthly['TOTAL_log'] = np.log1p(df_monthly['TOTAL'])
    
    # Generate exogenous variables
    df_monthly['Month'] = df_monthly.index.month
    df_monthly['Quarter'] = df_monthly.index.quarter
    
    # Lag feature: previous month's sales
    df_monthly['Prev_Month_Sales'] = df_monthly['TOTAL_log'].shift(1)
    df_monthly['Prev_Month_Sales'].fillna(method='bfill', inplace=True)
    
    # Rolling average (3 months)
    df_monthly['Rolling_Avg_3'] = df_monthly['TOTAL_log'].rolling(window=3).mean()
    df_monthly['Rolling_Avg_3'].fillna(method='bfill', inplace=True)
    
    logging.info("Data preprocessing completed.")
    return df_monthly

def select_best_sarimax_model(df, exog_columns):
    """
    Grid search to find the best SARIMAX model parameters.
    """
    logging.info("Starting grid search for best SARIMAX model.")
    import itertools
    
    y = df['TOTAL_log']
    exog = df[exog_columns]
    
    # Define p, d, q and P, D, Q ranges
    p = d = q = range(0, 3)
    pdq = list(itertools.product(p, d, q))
    seasonal_pdq = [(x[0], x[1], x[2], 12) for x in pdq]
    
    lowest_aic = np.inf
    best_order = None
    best_seasonal_order = None
    best_model = None
    
    for order in pdq:
        for seasonal_order in seasonal_pdq:
            try:
                model = SARIMAX(y, exog=exog, order=order, seasonal_order=seasonal_order,
                                enforce_stationarity=False, enforce_invertibility=False)
                results = model.fit(disp=False)
                if results.aic < lowest_aic:
                    lowest_aic = results.aic
                    best_order = order
                    best_seasonal_order = seasonal_order
                    best_model = results
                logging.info(f"Tested SARIMAX{order}x{seasonal_order}12 - AIC:{results.aic:.2f}")
            except Exception as e:
                logging.warning(f"Skipping SARIMAX{order}x{seasonal_order}12 due to an error: {e}")
                continue
    
    logging.info(f"Best SARIMAX{best_order}x{best_seasonal_order}12 model selected with AIC: {lowest_aic:.2f}")
    return best_model, best_order, best_seasonal_order

def evaluate_model(model, df, exog_columns=None):
    """
    Evaluate SARIMAX model and save metrics.
    """
    logging.info("Evaluating SARIMAX model.")
    
    y_true_log = df['TOTAL_log']
    exog = df[exog_columns] if exog_columns else None
    
    # Make predictions
    y_pred_log = model.predict(start=df.index[0], end=df.index[-1], exog=exog, dynamic=False)
    
    # Inverse transform predictions and true values
    y_pred = np.expm1(y_pred_log)
    y_true = np.expm1(y_true_log)
    
    # Calculate metrics
    mae = mean_absolute_error(y_true, y_pred)
    mse = mean_squared_error(y_true, y_pred)
    r2 = r2_score(y_true, y_pred)
    smape_value = smape(y_true, y_pred)
    
    logging.info(f"Evaluation Metrics - MAE: {mae:.2f}, MSE: {mse:.2f}, R²: {r2:.2f}, SMAPE: {smape_value:.2f}%")
    
    # Save metrics
    metrics = {
        'mae': mae,
        'mse': mse,
        'r2': r2,
        'smape': smape_value
    }
    
    with open('model_metrics.json', 'w') as f:
        json.dump(metrics, f)
    logging.info("Model metrics saved as 'model_metrics.json'.")
    
    return metrics, y_pred

def save_model(model):
    """
    Save the SARIMAX model.
    """
    joblib.dump(model, 'sarimax_model.pkl')
    logging.info("SARIMAX model saved as 'sarimax_model.pkl'.")

# ================================
# Main Function
# ================================

def main():
    # Fetch data
    df = fetch_sales_data()

    # Preprocess data
    df_monthly = preprocess_data(df)

    # Define exogenous variables
    exog_columns = ['Month', 'Quarter', 'Prev_Month_Sales', 'Rolling_Avg_3']

    # Grid search to find the best SARIMAX model
    best_model, best_order, best_seasonal_order = select_best_sarimax_model(df_monthly, exog_columns)

    # Evaluate SARIMAX model
    metrics, y_pred = evaluate_model(best_model, df_monthly, exog_columns=exog_columns)

    # Save SARIMAX model
    save_model(best_model)

    # Ensure that R² is at least 0.74
    if metrics['r2'] >= 0.74:
        logging.info("Model meets the required R² threshold.")
    else:
        logging.warning("Model does not meet the required R² threshold. Consider revising parameters or data preprocessing.")

if __name__ == '__main__':
    main()
