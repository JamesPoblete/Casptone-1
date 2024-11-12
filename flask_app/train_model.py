# train_model.py

import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression, Ridge, Lasso
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
from sklearn.model_selection import train_test_split, GridSearchCV, cross_val_score
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.pipeline import Pipeline
from sklearn.compose import ColumnTransformer
from sqlalchemy import create_engine, text
import joblib
import logging
import matplotlib.pyplot as plt
import io
import json  # Added to save metrics

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
    """
    logging.info("Starting data preprocessing.")

    # Handle missing values
    if df.isnull().sum().sum() > 0:
        logging.info("Handling missing values.")
        df = df.dropna().copy()  # Explicitly create a copy

    # Feature Engineering
    logging.info("Performing feature engineering.")
    df['DATE'] = pd.to_datetime(df['DATE'])
    df.loc[:, 'Year'] = df['DATE'].dt.year
    df.loc[:, 'Month'] = df['DATE'].dt.month
    df.loc[:, 'Day'] = df['DATE'].dt.day
    df.loc[:, 'Quarter'] = df['DATE'].dt.quarter
    df.loc[:, 'DayOfWeek'] = df['DATE'].dt.dayofweek
    df.loc[:, 'IsWeekend'] = df['DayOfWeek'].apply(lambda x: 1 if x >=5 else 0)

    # Add lag features (e.g., sales from previous month)
    logging.info("Adding lag features.")
    df = df.sort_values('DATE').copy()
    df['Total_Lag1'] = df['TOTAL'].shift(1)
    df['Total_Lag2'] = df['TOTAL'].shift(2)
    df['Total_Lag3'] = df['TOTAL'].shift(3)
    df['Total_Lag4'] = df['TOTAL'].shift(4)
    df['Total_Lag5'] = df['TOTAL'].shift(5)
    df = df.dropna().copy()

    # Define features and target
    X = df.drop(['DATE', 'TOTAL'], axis=1).copy()
    y = df['TOTAL'].copy()

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

    # Detect and handle outliers using Z-score
    from scipy import stats
    z_scores = np.abs(stats.zscore(X[numerical_features]))
    threshold = 3
    outliers = (z_scores > threshold).any(axis=1)
    logging.info(f"Detected {outliers.sum()} outliers. Removing them.")
    X = X[~outliers].copy()
    y = y[~outliers].copy()

    logging.info("Data preprocessing completed.")

    return X, y, preprocessor, categorical_features

def train_and_evaluate_model(X, y, preprocessor, categorical_features):
    """
    Train multiple regression models, perform hyperparameter tuning, and evaluate them.
    Save the best model and its metrics.
    """
    logging.info("Starting model training and evaluation.")

    # Split the data into training and testing sets
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, shuffle=False)

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
            'model__n_estimators': [100, 200],
            'model__max_depth': [None, 10, 20],
            'model__min_samples_split': [2, 5],
            'model__min_samples_leaf': [1, 2]
        },
        'GradientBoosting': {
            'model__n_estimators': [100, 200],
            'model__learning_rate': [0.05, 0.1],
            'model__max_depth': [3, 5],
            'model__min_samples_split': [2, 5],
            'model__min_samples_leaf': [1, 2]
        }
    }

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
            grid_search = GridSearchCV(pipeline, param_grids[name], cv=5, scoring='neg_mean_absolute_error', n_jobs=-1)
            grid_search.fit(X_train, y_train)
            best_model = grid_search.best_estimator_
            logging.info(f"Best parameters for {name}: {grid_search.best_params_}")
        else:
            # For models without hyperparameter tuning
            pipeline.fit(X_train, y_train)
            best_model = pipeline

        # Make predictions
        y_pred = best_model.predict(X_test)

        # Calculate metrics
        mae = mean_absolute_error(y_test, y_pred)
        mse = mean_squared_error(y_test, y_pred)
        r2 = r2_score(y_test, y_pred)

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
    if hasattr(best_model.named_steps['model'], 'feature_importances_'):
        logging.info("Plotting feature importances.")
        importances = best_model.named_steps['model'].feature_importances_
        # Get feature names from preprocessor
        num_features = preprocessor.transformers_[0][2]
        cat_features = preprocessor.transformers_[1][1].named_steps['onehot'].get_feature_names_out(categorical_features)
        feature_names = num_features + list(cat_features)
        feature_importances = pd.Series(importances, index=feature_names).sort_values(ascending=False)
        
        plt.figure(figsize=(10,6))
        feature_importances.head(20).plot(kind='barh')
        plt.xlabel('Feature Importance')
        plt.title(f'Feature Importances in {best_model_name}')
        plt.gca().invert_yaxis()
        plt.tight_layout()
        plt.savefig('feature_importances.png')
        plt.close()
        logging.info("Feature importances plotted and saved as 'feature_importances.png'.")

    logging.info("Model training and evaluation completed.")

def main():
    # Fetch data
    df = fetch_sales_data()

    # Preprocess data
    X, y, preprocessor, categorical_features = preprocess_data(df)

    # Train and evaluate models
    train_and_evaluate_model(X, y, preprocessor, categorical_features)

if __name__ == '__main__':
    main()
