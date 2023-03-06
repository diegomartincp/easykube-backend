"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros para crear un deployment de un proyecto web personalizado
"""

import os
import requests
in_file = 'Scripts/nginx-project.kube'


#url_ek_controlplane = sys.argv[1]
#name = sys.argv[2]
#url = sys.argv[3]
#token = sys.argv[4]

url_ek_controlplane="192.168.1.62"
name="ejemplo"
url = "https://github.com/diegomartincp/easykube-backend.git"
token="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"

#Meter el token en la URL del git clone
x = url.split("//")
tokenurl=x[0]+"//"+token+"@"+x[1]


# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{token-url}}",tokenurl)
contenido = contenido.replace("{{name}}",name)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)

"""
with open('temp.yaml', 'rb') as f:
    r = requests.get("http://"+url_ek_controlplane+":5000/test", files={"file": f})
    print(r.content)
"""

#os.remove('temp.yaml')
