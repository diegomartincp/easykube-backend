import sys
import requests


url_ek_controlplane = sys.argv[1]
name = sys.argv[2]
dbuser = sys.argv[3]
dbname = sys.argv[4]

r = requests.get(url_ek_controlplane+"/postgres_backup?name="+name+"&dbuser="+dbuser+"&dbname="+dbname)
with open('../backup.sql', 'wb') as f:
    f.write(r.content)

