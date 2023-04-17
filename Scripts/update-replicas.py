import os
import sys
import requests


url_ek_controlplane = sys.argv[1]
name = sys.argv[2]
replicas = sys.argv[3]


r = requests.get(url_ek_controlplane+"/update_hpa_replicas?name="+name+"-hpa&replicas="+replicas)
print(r.content)


