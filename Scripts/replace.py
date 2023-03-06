url = "https://github.com/diegomartincp/easykube-backend.git"
token="mitoken"

x = url.split("//")
urlfinal=x[0]+"//"+token+"@"+x[1]

print(urlfinal)
