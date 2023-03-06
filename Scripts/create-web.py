"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros para crear un deployment de un proyecto web personalizado
"""

import os
import requests



#url_ek_controlplane = sys.argv[1]
#name = sys.argv[2]
#url = sys.argv[3]
#token = sys.argv[4]

url_ek_controlplane="100.65.148.163"
name="miapp"
url = "https://github.com/diegomartincp/webpage_private_example.git"
token="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"

#Meter el token en la URL del git clone
x = url.split("//")
tokenurl=x[0]+"//"+token+"@"+x[1]


#FICHERO CON EL DEPLOYMENT
in_file = 'Scripts/nginx-project.kube'
# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{token-url}}",tokenurl)
contenido = contenido.replace("{{name}}",name)

# Abrir el archivo de destino para escritura
with open('temp3.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp3.yaml', 'rb') as f:
    r = requests.get("http://"+url_ek_controlplane+":5000/apply", files={"file": f})
    print(r.content)


#os.remove('temp.yaml')



#FICHERO CON EL SERVICIO
srvfile = 'Scripts/nginx-service.kube'
# Abrir el archivo original para lectura
with open(srvfile, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}",name)

# Abrir el archivo de destino para escritura
with open('temp4.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp4.yaml', 'rb') as f:
    r = requests.get("http://"+url_ek_controlplane+":5000/apply", files={"file": f})
    print(r.content)


#os.remove('temp.yaml')

