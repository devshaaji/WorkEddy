# syntax=docker/dockerfile:1.7
FROM python:3.11-slim

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY workers/live-worker/requirements.txt /app/requirements.txt
RUN --mount=type=cache,target=/root/.cache/pip,sharing=locked \
    pip install --prefer-binary --retries 10 --timeout 180 -r /app/requirements.txt

ENV PIP_DISABLE_PIP_VERSION_CHECK=1
ENV PIP_DEFAULT_TIMEOUT=180

COPY workers/live-worker/ /app/workers/live-worker/
COPY workers/shared/      /app/workers/shared/
COPY shared/              /app/shared/

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
    print('Downloading MediaPipe pose landmarker model...', flush=True)
    urllib.request.urlretrieve(url, str(cache))
else:
    print('Using cached MediaPipe pose landmarker model...', flush=True)

shutil.copyfile(str(cache), str(dest))
print('Model ready.', flush=True)
PY

# Pre-download YOLO26n-pose weights so first session starts fast.
# ultralytics auto-caches to ~/.cache/ultralytics on first load.
RUN --mount=type=cache,target=/root/.cache/ultralytics,sharing=locked \
    python -c "\
from ultralytics import YOLO; \
print('Pre-downloading YOLO26n-pose weights...', flush=True); \
YOLO('yolo26n-pose.pt'); \
print('Done.', flush=True)"

ENV PYTHONUNBUFFERED=1

CMD ["python", "/app/workers/live-worker/worker_runner.py"]
