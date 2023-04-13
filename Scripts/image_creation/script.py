from flask import Flask, request, send_file
app = Flask(__name__)

@app.route("/hola")
def info():
    return "ADIOS"

if __name__ == '__main__':
    #app.run(host='0.0.0.0',ssl_context='adhoc')
    app.run(host='0.0.0.0')