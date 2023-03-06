"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros
"""

import os
import requests
in_file = 'Scripts/ingress-ssl.kube'



#url_ek_controlplane = sys.argv[1]
#name = sys.argv[2]
#ipname = sys.argv[3]
#dominio = sys.argv[4]

url_ek_controlplane="34.123.158.74"
name="awebo"
ipname="awebo"
dominio="awebo.ddns.net"


# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}", name)
contenido = contenido.replace("{{ipname}}", ipname)
contenido = contenido.replace("{{dominio}}", dominio)


# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
    print(r.content)
os.remove('temp.yaml')

