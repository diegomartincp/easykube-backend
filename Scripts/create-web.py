"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros para crear un deployment de un proyecto web personalizado
"""

import os
import sys
import requests



url_ek_controlplane = sys.argv[1]
name = sys.argv[2]
url = sys.argv[3]
token = sys.argv[4]
replicas = sys.argv[5]

#url_ek_controlplane="34.123.158.74"
#name="awebo"
#url = "https://github.com/diegomartincp/webpage_private_example.git"
#token="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"

#Meter el token en la URL del git clone
x = url.split("//")
tokenurl=x[0]+"//"+token+"@"+x[1]


#FICHERO CON EL DEPLOYMENT
in_file = os.getcwd()+"\\..\\Scripts\\files\\nginx-project.kube"
# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{token-url}}",tokenurl)
contenido = contenido.replace("{{name}}",name)
contenido = contenido.replace("{{replicas}}",replicas)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
    #print(r.content)


os.remove('temp.yaml')



#FICHERO CON EL SERVICIO
srvfile = os.getcwd()+"\\..\\Scripts\\files\\nginx-service.kube"
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

