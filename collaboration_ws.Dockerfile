FROM python:3.12-alpine
WORKDIR /app
COPY collaboration_ws.py /app/collaboration_ws.py
CMD ["python", "collaboration_ws.py"]
