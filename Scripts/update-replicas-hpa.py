import os
import sys
import requests


url_ek_controlplane = sys.argv[1]
name = sys.argv[2]
replicas = sys.argv[3]


r = requests.get("http://"+url_ek_controlplane+"/update_hpa_replicas?name="+name+"-deployment&replicas="+replicas)
print(r.content)


