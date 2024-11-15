# preprocessing.py

from sklearn.compose import ColumnTransformer
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from sklearn.pipeline import Pipeline
import joblib
import os

def create_preprocessor(numerical_features, categorical_features):
    """
    Creates a preprocessor with scaling for numerical features and one-hot encoding for categorical features.
    
    Parameters:
        numerical_features (list): List of numerical feature names.
        categorical_features (list): List of categorical feature names.
        
    Returns:
        ColumnTransformer: A sklearn ColumnTransformer object.
    """
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
    
    return preprocessor

def save_preprocessor(preprocessor, filename='preprocessor.joblib'):
    """
    Saves the preprocessor to a joblib file.
    
    Parameters:
        preprocessor (ColumnTransformer): The preprocessor pipeline to save.
        filename (str): The filename for the saved preprocessor.
    """
    joblib.dump(preprocessor, filename)
    print(f"Preprocessor saved as '{filename}'.")

def load_preprocessor(filename='preprocessor.joblib'):
    """
    Loads a preprocessor from a joblib file.
    
    Parameters:
        filename (str): The filename of the preprocessor to load.
        
    Returns:
        ColumnTransformer: The loaded preprocessor pipeline.
    """
    if not os.path.exists(filename):
        raise FileNotFoundError(f"Preprocessor file '{filename}' not found.")
    preprocessor = joblib.load(filename)
    print(f"Preprocessor loaded from '{filename}'.")
    return preprocessor
