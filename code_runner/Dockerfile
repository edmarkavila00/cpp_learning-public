FROM python:3.12-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends g++ \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --uid 10001 --no-create-home --shell /usr/sbin/nologin runner

COPY runner.py /opt/cppquest/runner.py
WORKDIR /tmp
USER 10001:10001
ENTRYPOINT ["python3", "/opt/cppquest/runner.py"]
