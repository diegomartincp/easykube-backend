"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros para crear un deployment de un proyecto web personalizado
"""

import os
import sys
import requests



url_ek_controlplane = sys.argv[1]
name = sys.argv[2]
image = sys.argv[3]

#url_ek_controlplane="localhost:81"
#name="awebo7"
#image="test-1"



#FICHERO CON EL DEPLOYMENT
in_file = os.getcwd()+"\\..\\Scripts\\files\\python-deployment.kube"
# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada

contenido = contenido.replace("{{name}}",name)
contenido = contenido.replace("{{image}}",image)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
    #print(r.content)
os.remove('temp.yaml')

#FICHERO CON EL SERVICIO
srvfile = os.getcwd()+"\\..\\Scripts\\files\\python-service.kube"
# Abrir el archivo original para lectura
with open(srvfile, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}",name)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r2 = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
    print(r.content+r2.content)

os.remove('temp.yaml')

