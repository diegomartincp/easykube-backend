import ruamel.yaml
import json
import sys
in_file = 'git-clone-variables.kube'

#port = sys.argv[1]
#name = sys.argv[2]
port="801"
name="miejemplo"



f = open(in_file, "r")
raw_data=f.read()
raw_data= raw_data.replace("{{port}}", port)
raw_data= raw_data.replace("{{name}}", name)
print(raw_data)

yaml = ruamel.yaml.YAML()
data = yaml.load(raw_data)
print(data)

with open(r'store.yaml', 'w') as file:
    documents = yaml.dump(data, file)
    print(documents)

