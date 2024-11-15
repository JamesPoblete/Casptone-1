# train_model.py

import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression, Ridge, Lasso
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
from sklearn.model_selection import TimeSeriesSplit, GridSearchCV
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.pipeline import Pipeline
from sklearn.compose import ColumnTransformer
from sqlalchemy import create_engine, text
import joblib
import logging
import json
from scipy import stats

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
                    handlers=[
                        logging.FileHandler("train_model.log"),
                        logging.StreamHandler()
                    ])

# ================================
# Helper Functions
# ================================

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
    Preprocess the sales data:
    - Handle missing values
    - Feature engineering (add temporal features)
    - Encode categorical variables
    - Scale numerical features
    - Detect and handle outliers
    - Transform target variable
    """
    logging.info("Starting data preprocessing.")

    # Handle missing values
    missing_values = df.isnull().sum().sum()
    if missing_values > 0:
        logging.info(f"Handling {missing_values} missing values by dropping rows with missing data.")
        df = df.dropna().copy()  # Alternatively, you can impute missing values

    # Feature Engineering
    logging.info("Performing feature engineering.")
    df['DATE'] = pd.to_datetime(df['DATE'])
    df = df.sort_values('DATE').copy()
    df['Year'] = df['DATE'].dt.year
    df['Month'] = df['DATE'].dt.month
    df['Day'] = df['DATE'].dt.day
    df['Quarter'] = df['DATE'].dt.quarter
    df['DayOfWeek'] = df['DATE'].dt.dayofweek
    df['IsWeekend'] = df['DayOfWeek'].apply(lambda x: 1 if x >=5 else 0)

    # Add lag features (e.g., sales from previous months)
    logging.info("Adding lag features.")
    for lag in range(1, 6):
        df[f'Total_Lag{lag}'] = df['TOTAL'].shift(lag)
    
    df = df.dropna().copy()  # Drop rows with NaN after creating lag features

    # Define features and target
    X = df.drop(['DATE', 'TOTAL'], axis=1).copy()
    y = df['TOTAL'].copy()

    # Apply log transformation to target variable to stabilize variance
    y = np.log1p(y)  # log(1 + y) to handle zero sales

    # Identify numerical and categorical columns
    numerical_features = X.select_dtypes(include=['int64', 'float64']).columns.tolist()
    categorical_features = X.select_dtypes(include=['object', 'category']).columns.tolist()

    # Define preprocessing for numerical and categorical data
    numerical_transformer = Pipeline(steps=[
        ('scaler', StandardScaler())
    ])

    categorical_transformer = Pipeline(steps=[
        ('onehot', OneHotEncoder(handle_unknown='ignore'))
    ])

    preprocessor = ColumnTransformer(
        transformers=[
            ('num', numerical_transformer, numerical_features),
            ('cat', categorical_transformer, categorical_features)
        ])

    # Detect and handle outliers using capping (e.g., Winsorization)
    logging.info("Handling outliers using capping.")
    for col in numerical_features:
        lower_bound = df[col].quantile(0.01)
        upper_bound = df[col].quantile(0.99)
        df[col] = np.where(df[col] < lower_bound, lower_bound, df[col])
        df[col] = np.where(df[col] > upper_bound, upper_bound, df[col])

    logging.info("Data preprocessing completed.")

    return X, y, preprocessor, categorical_features

def train_and_evaluate_model(X, y, preprocessor, categorical_features):
    """
    Train multiple regression models, perform hyperparameter tuning, and evaluate them.
    Save the best model and its metrics.
    """
    logging.info("Starting model training and evaluation.")

    # Define models to evaluate
    models = {
        'LinearRegression': LinearRegression(),
        'Ridge': Ridge(),
        'Lasso': Lasso(),
        'RandomForest': RandomForestRegressor(random_state=42),
        'GradientBoosting': GradientBoostingRegressor(random_state=42)
    }

    # Hyperparameter grids for each model
    param_grids = {
        'RandomForest': {
            'model__n_estimators': [100, 200, 300],
            'model__max_depth': [None, 10, 20, 30],
            'model__min_samples_split': [2, 5, 10],
            'model__min_samples_leaf': [1, 2, 4]
        },
        'GradientBoosting': {
            'model__n_estimators': [100, 200, 300],
            'model__learning_rate': [0.01, 0.05, 0.1],
            'model__max_depth': [3, 5, 7],
            'model__min_samples_split': [2, 5, 10],
            'model__min_samples_leaf': [1, 2, 4]
        },
        'Ridge': {
            'model__alpha': [0.1, 1.0, 10.0, 100.0]
        },
        'Lasso': {
            'model__alpha': [0.1, 1.0, 10.0, 100.0]
        }
    }

    # Use TimeSeriesSplit for cross-validation
    tscv = TimeSeriesSplit(n_splits=5)

    # Store model performance
    model_performance = {}

    for name, model in models.items():
        logging.info(f"Training and evaluating model: {name}")

        # Create a pipeline
        pipeline = Pipeline(steps=[
            ('preprocessor', preprocessor),
            ('model', model)
        ])

        if name in param_grids:
            logging.info(f"Performing Grid Search for {name}")
            grid_search = GridSearchCV(
                pipeline,
                param_grids[name],
                cv=tscv,
                scoring='neg_mean_absolute_error',
                n_jobs=-1,
                verbose=1
            )
            grid_search.fit(X, y)
            best_model = grid_search.best_estimator_
            logging.info(f"Best parameters for {name}: {grid_search.best_params_}")
        else:
            # For models without hyperparameter tuning
            pipeline.fit(X, y)
            best_model = pipeline

        # Make predictions
        y_pred = best_model.predict(X)

        # Inverse transform the target variable
        y_true = np.expm1(y)  # inverse of log1p
        y_pred_actual = np.expm1(y_pred)

        # Calculate metrics
        mae = mean_absolute_error(y_true, y_pred_actual)
        mse = mean_squared_error(y_true, y_pred_actual)
        r2 = r2_score(y_true, y_pred_actual)

        logging.info(f"{name} - MAE: {mae:.2f}, MSE: {mse:.2f}, R²: {r2:.2f}")

        # Store performance
        model_performance[name] = {
            'model': best_model,
            'mae': mae,
            'mse': mse,
            'r2': r2
        }

    # Select the best model based on MAE
    best_model_name = min(model_performance, key=lambda x: model_performance[x]['mae'])
    best_model = model_performance[best_model_name]['model']
    best_mae = model_performance[best_model_name]['mae']
    best_mse = model_performance[best_model_name]['mse']
    best_r2 = model_performance[best_model_name]['r2']

    logging.info(f"Best model: {best_model_name} with MAE: {best_mae:.2f}, MSE: {best_mse:.2f}, R²: {best_r2:.2f}")

    # Save the best model
    joblib.dump(best_model, 'best_model.joblib')
    logging.info("Best model saved as 'best_model.joblib'.")

    # Save the performance metrics to a JSON file
    metrics = {
        'model_name': best_model_name,
        'mae': best_mae,
        'mse': best_mse,
        'r2': best_r2
    }

    with open('model_metrics.json', 'w') as f:
        json.dump(metrics, f)
    logging.info("Model metrics saved as 'model_metrics.json'.")

    # Plot feature importance if model supports it
    if best_model.named_steps['model'].__class__.__name__ in ['RandomForestRegressor', 'GradientBoostingRegressor']:
        logging.info("Plotting feature importances.")
        importances = best_model.named_steps['model'].feature_importances_
        # Get feature names from preprocessor
        num_features = preprocessor.transformers_[0][2]
        cat_features = best_model.named_steps['preprocessor'].transformers_[1][1].named_steps['onehot'].get_feature_names_out(categorical_features)
        feature_names = list(num_features) + list(cat_features)
        feature_importances = pd.Series(importances, index=feature_names).sort_values(ascending=False)
    
def main():
    # Fetch data
    df = fetch_sales_data()

    # Preprocess data
    X, y, preprocessor, categorical_features = preprocess_data(df)

    # Train and evaluate models
    train_and_evaluate_model(X, y, preprocessor, categorical_features)

if __name__ == '__main__':
    main()
