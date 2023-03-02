"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros
"""

import os
import requests
in_file = 'Scripts/issuer-lets-encrypt-staging.kube'

#email = sys.argv[1]

email="campos.martin.diego@gmail.com"

# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido_modificado = contenido.replace("{{email}}", email)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido_modificado)


with open('temp.yaml', 'rb') as f:
    r = requests.get('http://localhost:5000/test', files={"file": f})
    print(r.content)
os.remove('temp.yaml')

