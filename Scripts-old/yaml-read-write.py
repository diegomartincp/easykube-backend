import ruamel.yaml
import json

in_file = 'Scripts-old/temp.yaml'
out_file = 'output.json'

yaml = ruamel.yaml.YAML(typ='safe')
with open(in_file, "rt") as file:
    data = yaml.load(file)
    print(data)

    #with open(r'store.yaml', 'w') as file:
        #documents = yaml.dump(data, file)

