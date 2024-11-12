import win32serviceutil
import win32service
import win32event
import servicemanager
import socket
import logging
import time
from predict_sales import app  # Import your updated Flask app

# Configure logging for the service
logging.basicConfig(
    filename='flask_service.log',
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

class FlaskService(win32serviceutil.ServiceFramework):
    _svc_name_ = "FlaskService"
    _svc_display_name_ = "Flask Application Service"
    _svc_description_ = "Runs the Flask application as a Windows Service."

    def __init__(self, args):
        super().__init__(args)
        self.hWaitStop = win32event.CreateEvent(None, 0, 0, None)
        socket.setdefaulttimeout(60)

    def SvcStop(self):
        # Log the stopping action
        logging.info("Service is stopping...")
        
        # Tell the Service Control Manager we're in the process of stopping
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        
        # Set the stop event to terminate the service
        win32event.SetEvent(self.hWaitStop)

    def SvcDoRun(self):
        # Log the service start action
        logging.info("Service is starting...")

        # Log the service start in the Windows Event Log
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION_TYPE,
            servicemanager.PYS_SERVICE_STARTED,
            (self._svc_name_, '')
        )
        
        # Run the Flask app in the main function
        self.main()

    def main(self):
        try:
            # Adding a delay before starting the app
            logging.info("Service initialization delay...")
            time.sleep(5)  # Add a 5-second delay to allow for initialization
            
            logging.info("Starting Flask app with waitress...")
            
            # Use waitress to run the Flask application
            from waitress import serve
            serve(app, host='0.0.0.0', port=5000)
            
        except Exception as e:
            logging.error(f"Error starting the Flask app: {e}")
            servicemanager.LogErrorMsg(f"Error in Flask Service: {e}")

if __name__ == '__main__':
    import sys
    if len(sys.argv) > 1 and sys.argv[1] == 'run':
        # Run the app directly without the service for debugging
        logging.info("Running Flask app directly without the Windows Service wrapper.")
        app.run(host='0.0.0.0', port=5000, debug=True)  # Enable debug for development
    else:
        # Install or start as a Windows service
        win32serviceutil.HandleCommandLine(FlaskService)
