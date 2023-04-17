import os
import sys
import requests


url_ek_controlplane = sys.argv[1]
project_name = sys.argv[2]



r = requests.get(url_ek_controlplane+"/delete_project?project="+project_name)
#print("http://"+url_ek_controlplane+"/delete_project?project="+project_name)
print(r.content)


