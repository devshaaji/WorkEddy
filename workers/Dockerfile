# syntax=docker/dockerfile:1.7
FROM python:3.11-slim

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY workers/requirements.txt /app/requirements.txt
RUN --mount=type=cache,target=/root/.cache/pip,sharing=locked \
    pip install -r /app/requirements.txt

COPY workers/ /app/workers/
COPY shared/ /app/shared/

RUN --mount=type=cache,target=/var/cache/workeddy/mediapipe,sharing=locked \
    mkdir -p /opt/mediapipe /var/cache/workeddy/mediapipe \
 && python -c "\
import shutil, urllib.request; \
cache = '/var/cache/workeddy/mediapipe/pose_landmarker_lite.task'; \
dest = '/opt/mediapipe/pose_landmarker_lite.task'; \
url = 'https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/latest/pose_landmarker_lite.task'; \
if not __import__('pathlib').Path(cache).is_file(): \
    print('Downloading pose landmarker model...', flush=True); \
    urllib.request.urlretrieve(url, cache); \
else: \
    print('Using cached pose landmarker model...', flush=True); \
shutil.copyfile(cache, dest); \
print('Model ready.', flush=True)"

ENV PYTHONUNBUFFERED=1

CMD ["python", "/app/workers/queue-listener/worker_runner.py"]
