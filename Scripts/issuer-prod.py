"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros
"""

import os
import requests
in_file = 'Scripts/issuer-lets-encrypt-prod.kube'



#url_ek_controlplane = sys.argv[1]
#email = sys.argv[2]
#env = sys.argv[3]
#ingress = sys.argv[4]
url_ek_controlplane="100.65.148.163"
email="campos.martin.diego@gmail.com"
name="ejemplo"



# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{email}}", email)
contenido = contenido.replace("{{name}}", name)
contenido = contenido.replace("{{ingress}}", name+"-ingress")

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r = requests.get("http://"+url_ek_controlplane+":5000/apply", files={"file": f})
    print(r.content)
os.remove('temp.yaml')

