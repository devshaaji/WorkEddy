#!/bin/bash

echo "==================================================="
echo "    WorkEddy Docker Environment Setup (Unix/Mac)"
echo "==================================================="
echo ""

# Check if .env file exists
echo "[1/3] Checking if .env file exists..."
if [ ! -f .env ]; then
    echo ".env file not found. Copying from .env.example..."
    cp .env.example .env
    echo ".env file created successfully."
else
    echo ".env file already exists."
fi

echo ""
echo "[2/3] Building and starting Docker containers..."
docker-compose up -d --build

echo ""
echo "[3/3] Wait for services to initialize..."
sleep 5

echo ""
echo "==================================================="
echo "Setup Complete!"
echo "==================================================="
echo ""
echo "The WorkEddy services should now be running in the background."
echo ""
echo "Web Application:  http://localhost:8080"
echo "MySQL Database:   localhost:3307  (User: workeddy, Pass: workeddy)"
echo "Redis Server:     localhost:6380"
echo ""
echo "To stop the services, run: docker-compose down"
echo "To view the logs, run: docker-compose logs -f"
echo "==================================================="
