FROM python:3.8-buster

COPY requirements.txt /opt/app/requirements.txt

WORKDIR /opt/app

RUN pip install -r requirements.txt

WORKDIR /app

COPY script.py /app

EXPOSE 5000

CMD ["python3", "script.py"]
