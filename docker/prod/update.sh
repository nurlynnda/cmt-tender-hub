#!/bin/sh
# Update the server to the latest code on GitHub's main branch. Your data stays.
set -e
cd "$(dirname "$0")/../.."
./docker/prod/backup.sh                       # a fresh backup first, just in case
git pull --ff-only origin main
docker compose -f docker-compose.prod.yml up -d --build
docker image prune -f > /dev/null             # remove old copies of the app to save disk space
echo "Updated. The site restarts with the new version in about a minute."
