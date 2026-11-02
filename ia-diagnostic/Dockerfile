FROM python:3.11-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    HF_HUB_OFFLINE=1

WORKDIR /app

COPY requirements.txt /tmp/requirements.txt

RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir torch --index-url https://download.pytorch.org/whl/cpu \
    && grep -vi '^torch' /tmp/requirements.txt > /tmp/requirements.rest.txt \
    && pip install --no-cache-dir -r /tmp/requirements.rest.txt

COPY . .

EXPOSE 8001

CMD ["sh", "-c", "uvicorn service:app --host 0.0.0.0 --port ${PORT:-8001}"]
