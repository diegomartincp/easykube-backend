"""
Este script abre un fichero .kube y lo modifica de acuerdo a los parámetros
"""

import os
import requests
in_file = 'Scripts/empty-secret.kube'



#url_ek_controlplane = sys.argv[1]
#name = sys.argv[2]

url_ek_controlplane="192.168.1.62"
name="ejemplo"
secreto=name+"-secret"


# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{secreto}}", secreto)



