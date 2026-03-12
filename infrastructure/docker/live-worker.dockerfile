FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY workers/live-worker/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

COPY workers/live-worker/ /app/workers/live-worker/
COPY ml/                  /app/ml/

# Download the MediaPipe pose landmarker model (lite variant, ~5 MB).
# Stored outside bind-mount path so it persists.
RUN mkdir -p /opt/mediapipe \
 && python -c "\
import urllib.request, sys; \
url = 'https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/latest/pose_landmarker_lite.task'; \
dest = '/opt/mediapipe/pose_landmarker_lite.task'; \
print('Downloading MediaPipe pose landmarker model...', flush=True); \
urllib.request.urlretrieve(url, dest); \
print('Done.', flush=True)"

# Pre-download YOLO26n-pose weights so first session starts fast.
# ultralytics auto-caches to ~/.cache/ultralytics on first load.
RUN python -c "\
from ultralytics import YOLO; \
print('Pre-downloading YOLO26n-pose weights...', flush=True); \
YOLO('yolo26n-pose.pt'); \
print('Done.', flush=True)"

ENV PYTHONUNBUFFERED=1

CMD ["python", "/app/workers/live-worker/worker_runner.py"]
