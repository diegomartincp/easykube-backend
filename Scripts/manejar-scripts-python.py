import subprocess
import os

#ruta="/scripts/ejemplo1"
#in_file = os.getcwd()+"\\..\\Scripts\\files\\issuer-lets-encrypt-stagging.kube"

#1 Extraer el requirements.txt
command="pipreqs --force .\..\public\scripts\ejemplo1"
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)
exit()

#2 Ejecutar el dockerfile
command="docker build . -t script-test"
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)
print("--")
exit()
command="docker tag script-test:latest diegomartinc/script-test:latest"
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)
print("--")
command="docker push diegomartinc/script-test:latest"
ret = subprocess.run(command, capture_output=True, shell=True)
print(ret)


