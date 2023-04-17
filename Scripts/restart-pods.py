import os
import sys
import requests


url_ek_controlplane = sys.argv[1]
name = sys.argv[2]

#url_ek_controlplane="localhost:81"
#name="restartpooods"

r = requests.get(url_ek_controlplane+"/reload_pods?name="+name)
print(r.content)


