FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY workers/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

COPY workers/ /app/workers/
COPY ml/      /app/ml/

# Download the MediaPipe pose landmarker model (lite variant, ~5 MB).
# Stored in /opt/mediapipe so the ./ml bind-mount in docker-compose doesn't hide it.
RUN mkdir -p /opt/mediapipe \
 && python -c "\
import urllib.request, sys; \
url = 'https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/latest/pose_landmarker_lite.task'; \
dest = '/opt/mediapipe/pose_landmarker_lite.task'; \
print('Downloading pose landmarker model...', flush=True); \
urllib.request.urlretrieve(url, dest); \
print('Done.', flush=True)"

ENV PYTHONUNBUFFERED=1

CMD ["python", "/app/workers/queue-listener/worker_runner.py"]