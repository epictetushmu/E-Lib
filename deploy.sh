#!/bin/bash

# Configuration
SOURCE_DIR="/home/makis/Desktop/E-Lib"
TARGET_SERVER="your-server-hostname-or-ip"
TARGET_DIR="/path/to/production/folder"
ENV_FILE=".env.production"

# Check if production environment file exists
if [ ! -f "$SOURCE_DIR/$ENV_FILE" ]; then
    echo "Creating production environment file..."
    cp "$SOURCE_DIR/.env" "$SOURCE_DIR/$ENV_FILE"
    echo "Please update $ENV_FILE with production values before deploying."
    exit 1
fi

# Sync files to the production server
echo "Deploying application to $TARGET_SERVER:$TARGET_DIR..."
rsync -avz --exclude-from="$SOURCE_DIR/.deployignore" \
    --exclude ".git" --exclude "node_modules" \
    "$SOURCE_DIR/" "$TARGET_SERVER:$TARGET_DIR/"

# Run post-deployment commands on the server
ssh "$TARGET_SERVER" "cd $TARGET_DIR && \
    cp $ENV_FILE .env && \
    npm install --production && \
    pm2 restart app || pm2 start app.js --name app"

echo "Deployment completed successfully!"
