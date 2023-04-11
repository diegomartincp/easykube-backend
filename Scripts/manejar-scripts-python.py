import subprocess
import os
import shutil

name="test"
workgroup_id="1"

#1 Copiar el fichero a la nueva ruta donde crearemos la iamgen
shutil.copy2('.\..\public\scripts\ejemplo1\script.py', '.\..\Scripts\image_creation\script.py')

#1 Extraer el requirements.txt
command="pipreqs --force .\..\Scripts\image_creation"
ret = subprocess.run(command, capture_output=True, shell=True)

#2 Ejecutar el dockerfile
command="docker build .\..\Scripts\image_creation -t "+name+"-"+workgroup_id
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)
exit()
command="docker tag script-test:latest diegomartinc/script-test:latest"
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)
print("--")
command="docker push diegomartinc/script-test:latest"
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)


