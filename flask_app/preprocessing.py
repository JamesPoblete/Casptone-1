# preprocessing.py

from sklearn.base import BaseEstimator, TransformerMixin
from sklearn.pipeline import Pipeline
import joblib
import os
import numpy as np
import pandas as pd

class CyclicEncoder(BaseEstimator, TransformerMixin):
    """
    Adds cyclic features for specified columns in the dataframe.
    Transforms each cyclical column into two features using sine and cosine transformations.
    """

    def __init__(self, cyclical_columns_periods):
        """
        Initializes the CyclicEncoder.

        Parameters:
            cyclical_columns_periods (dict): Dictionary mapping column names to their periods.
                                            e.g., {'Month': 12, 'Quarter': 4}
        """
        self.cyclical_columns_periods = cyclical_columns_periods

    def fit(self, X, y=None):
        return self

    def transform(self, X):
        X = X.copy()
        for col, period in self.cyclical_columns_periods.items():
            if col not in X.columns:
                raise ValueError(f"Column '{col}' not found in input DataFrame.")
            X[f'{col}_sin'] = np.sin(2 * np.pi * X[col] / period)
            X[f'{col}_cos'] = np.cos(2 * np.pi * X[col] / period)
            X = X.drop(columns=[col])
        return X

def create_preprocessor(cyclical_columns_periods):
    """
    Creates a preprocessor pipeline that adds cyclic features.

    Parameters:
        cyclical_columns_periods (dict): Dictionary mapping cyclical column names to their periods.

    Returns:
        Pipeline: A sklearn Pipeline object.
    """
    # Define the cyclic encoder
    cyclic_encoder = CyclicEncoder(cyclical_columns_periods=cyclical_columns_periods)

    # Create the pipeline
    pipeline = Pipeline(steps=[
        ('cyclic', cyclic_encoder)
    ])

    return pipeline

def save_preprocessor(preprocessor, filename='preprocessor.joblib'):
    """
    Saves the preprocessor to a joblib file.

    Parameters:
        preprocessor (Pipeline): The preprocessor pipeline to save.
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
        Pipeline: The loaded preprocessor pipeline.
    """
    if not os.path.exists(filename):
        raise FileNotFoundError(f"Preprocessor file '{filename}' not found.")
    preprocessor = joblib.load(filename)
    print(f"Preprocessor loaded from '{filename}'.")
    return preprocessor

if __name__ == "__main__":
    # Example usage
    # Define cyclic columns
    cyclical_columns_periods = {'Month': 12, 'Quarter': 4}

    # Create preprocessor
    preprocessor = create_preprocessor(cyclical_columns_periods)

    # Save preprocessor
    save_preprocessor(preprocessor)
