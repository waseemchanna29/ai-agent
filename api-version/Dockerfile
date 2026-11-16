# Server deployment: the container is the sandbox, so commands can run in "auto" mode.
FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends git nodejs npm \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY . .

ENV AGENTIC_WORKSPACE=/workspace \
    PYTHONUNBUFFERED=1
VOLUME ["/workspace"]

ENTRYPOINT ["python", "main.py"]
CMD ["status"]
