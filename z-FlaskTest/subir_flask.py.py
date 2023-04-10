import subprocess

#1 Extraer el requirements.txt
command="pipreqs --force ./z"
ret = subprocess.run(command, capture_output=True, shell=True)

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


