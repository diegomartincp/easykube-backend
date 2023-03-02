"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros
"""

import ruamel.yaml
import json
import sys
import requests
in_file = 'git-clone-variables.kube'

#port = sys.argv[1]
#name = sys.argv[2]
port="801"
name="miejemplo"



f = open(in_file, "r")
raw_data=f.read()
raw_data= raw_data.replace("{{port}}", port)
raw_data= raw_data.replace("{{name}}", name)
print(raw_data)

url="http://localhost:5000/test?data="+str(raw_data)
#headers = {'Content-type': 'application/json', 'Accept': 'text/plain'}
response = requests.get(url)
print(response.content)

