FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        libgl1-mesa-glx \
        libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY workers/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

COPY workers/ /app/workers/
COPY ml/      /app/ml/

ENV PYTHONUNBUFFERED=1

CMD ["python", "/app/workers/queue-listener/worker_runner.py"]