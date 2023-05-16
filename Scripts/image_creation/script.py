from flask import Flask, request, send_file
from datetime import datetime
app = Flask(__name__)

@app.route("/time")
def info():
    now = datetime.now()
    time = now.strftime("%H:%M:%S")
    return time

if __name__ == '__main__':
    #app.run(host='0.0.0.0',ssl_context='adhoc')
    app.run(host='0.0.0.0')