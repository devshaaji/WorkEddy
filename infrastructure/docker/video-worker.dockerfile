# syntax=docker/dockerfile:1.7
FROM python:3.11-slim

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends \
        ffmpeg \
        libgl1 \
        libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY workers/requirements.txt /app/requirements.txt
RUN --mount=type=cache,target=/root/.cache/pip,sharing=locked \
    pip install --prefer-binary --retries 10 --timeout 180 -r /app/requirements.txt

ENV PIP_DISABLE_PIP_VERSION_CHECK=1
ENV PIP_DEFAULT_TIMEOUT=180

COPY workers/ /app/workers/
COPY shared/  /app/shared/

# Download the MediaPipe pose landmarker model once and reuse it across builds.
RUN --mount=type=cache,target=/var/cache/workeddy/mediapipe,sharing=locked \
    mkdir -p /opt/mediapipe /var/cache/workeddy/mediapipe \
 && python - <<'PY'
from pathlib import Path
import shutil
import urllib.request

cache = Path('/var/cache/workeddy/mediapipe/pose_landmarker_lite.task')
dest = Path('/opt/mediapipe/pose_landmarker_lite.task')
url = 'https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/latest/pose_landmarker_lite.task'

if not cache.is_file():
    print('Downloading pose landmarker model...', flush=True)
    urllib.request.urlretrieve(url, str(cache))
else:
    print('Using cached pose landmarker model...', flush=True)

shutil.copyfile(str(cache), str(dest))
print('Model ready.', flush=True)
PY

ENV PYTHONUNBUFFERED=1

CMD ["python", "/app/workers/queue-listener/worker_runner.py"]
