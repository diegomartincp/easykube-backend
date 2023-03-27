import os
import sys
import requests


#url_ek_controlplane = sys.argv[1]
#deployment = sys.argv[2]
#replicas = sys.argv[3]


r = requests.get("http://localhost:81/postgres_backup?name=lotenemos&dbuser=diego&dbname=diegodb")
with open('backup.sql', 'wb') as f:
    f.write(r.content)

