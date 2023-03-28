import os
import sys
import requests



url_ek_controlplane = sys.argv[1]
name = sys.argv[2]
memory = sys.argv[3]
db = sys.argv[4]
user = sys.argv[5]
pwd = sys.argv[6]



#1. CONFIGMAP
in_file = os.getcwd()+"\\..\\Scripts\\files\\postgres-configmap.kube"
#in_file = os.getcwd()+"\\Scripts\\files\\postgres-configmap.kube"

# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}",name)
contenido = contenido.replace("{{db}}",db)
contenido = contenido.replace("{{user}}",user)
contenido = contenido.replace("{{pwd}}",pwd)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r1 = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
os.remove('temp.yaml')


#------------------
#2. VOLUME
in_file = os.getcwd()+"\\..\\Scripts\\files\\postgres-volume.kube"
#in_file = os.getcwd()+"\\Scripts\\files\\postgres-volume.kube"

# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}",name)
contenido = contenido.replace("{{memory}}",memory)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r2 = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
os.remove('temp.yaml')



#------------------
#3. PVC
in_file = os.getcwd()+"\\..\\Scripts\\files\\postgres-pvc.kube"
#in_file = os.getcwd()+"\\Scripts\\files\\postgres-pvc.kube"

# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}",name)
contenido = contenido.replace("{{memory}}",memory)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r3 = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
os.remove('temp.yaml')




#------------------
#3. PVC
in_file = os.getcwd()+"\\..\\Scripts\\files\\postgres-deployment.kube"
#in_file = os.getcwd()+"\\Scripts\\files\\postgres-deployment.kube"

# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}",name)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r4 = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
os.remove('temp.yaml')



#------------------
#3. PVC
in_file = os.getcwd()+"\\..\\Scripts\\files\\postgres-service.kube"
#in_file = os.getcwd()+"\\Scripts\\files\\postgres-service.kube"

# Abrir el archivo original para lectura
with open(in_file, 'r') as f:
    # Leer el contenido del archivo
    contenido = f.read()

# Reemplazar la palabra deseada
contenido = contenido.replace("{{name}}",name)

# Abrir el archivo de destino para escritura
with open('temp.yaml', 'w') as f:
    # Escribir el contenido modificado en el archivo de destino
    f.write(contenido)


with open('temp.yaml', 'rb') as f:
    r5 = requests.get("http://"+url_ek_controlplane+"/apply", files={"file": f})
os.remove('temp.yaml')

#Imprimir resultados
print(r1.content+r2.content+r3.content+r4.content+r5.content)
