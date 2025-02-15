from flask import Flask, request
import subprocess

app = Flask(__name__)

@app.route('/github-webhook/', methods=['POST'])
def webhook():
    if request.method == 'POST':
        subprocess.run(["/bin/bash", "/var/www/E-Lib/app/scripts/deploy.sh"])
        return "Updated!", 200
    return "Forbidden", 403

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
