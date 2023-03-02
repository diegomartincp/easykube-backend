"""
Este script instala en el cluster cert-manager
"""

import requests


url="http://192.168.1.42:5000/install_cert"
#headers = {'Content-type': 'application/json', 'Accept': 'text/plain'}
response = requests.get(url)
print(response.content)

