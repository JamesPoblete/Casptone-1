# config.py

import os
from pathlib import Path

class Config:
    # Database configuration from environment variables
    DB_HOST = os.environ.get('DB_HOST', 'localhost')
    DB_PORT = os.environ.get('DB_PORT', '3306')
    DB_USER = os.environ.get('DB_USER', 'root')
    DB_PASSWORD = os.environ.get('DB_PASSWORD', '')
    DB_NAME = os.environ.get('DB_NAME', 'dbcapstone')
    DATABASE_URI = f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
    
    # API Key for authentication
    API_KEY = os.environ.get('API_KEY', 'testkey123')
    
    # Logging configuration
    BASE_DIR = Path(__file__).resolve().parent
    LOG_FILE = str(BASE_DIR / "api.log")  # Ensure it's a string path
    
    # Other configurations
    DEBUG = False
    TESTING = False
