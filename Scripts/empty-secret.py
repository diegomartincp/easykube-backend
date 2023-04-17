"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros
"""

import os
import requests
import sys

in_file = os.getcwd()+"\\..\\Scripts\\files\\empty-secret.kube"
#print(in_file)


url_ek_controlplane = sys.argv[1]
name = sys.argv[2]

#url_ek_controlplane="34.123.158.74"
#name="awebo"



# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}", name)


# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r = requests.get(url_ek_controlplane+"/apply", files={"file": f})
    print(r.content)
os.remove('temp.yaml')
