import subprocess
import sys
import shutil
import os

name = sys.argv[1]
workgroup_id = sys.argv[2]
filename= sys.argv[3]
#workgroup_id="1"

#1 Copiar el fichero a la nueva ruta donde crearemos la iamgen con el dockerfile y borrarlo
shutil.copy2(".\\..\\public\\scripts\\ejemplo1\\"+filename , ".\\..\\Scripts\\image_creation\\script.py")
os.remove(".\\..\\public\\scripts\\ejemplo1\\"+filename)

#2 Extraer el requirements.txt
command="pipreqs --force .\..\Scripts\image_creation"
ret = subprocess.run(command, capture_output=True, shell=True)

#3 Ejecutar el dockerfile
command="docker build .\..\Scripts\image_creation -t "+name+"-"+workgroup_id
ret = subprocess.run(command, capture_output=True, shell=True)

command="docker tag "+name+"-"+workgroup_id+":latest diegomartinc/"+name+"-"+workgroup_id+":latest"
ret = subprocess.run(command, capture_output=True, shell=True)

command="docker push diegomartinc/"+name+"-"+workgroup_id+":latest"
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)


