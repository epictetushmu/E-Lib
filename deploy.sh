#!/bin/bash

# E-Lib Deployment Script
# Colors for better readability
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
SOURCE_DIR="/home/makis/Desktop/E-Lib"
TARGET_SERVER="your-server-hostname-or-ip"
TARGET_DIR="/path/to/production/folder"
ENV_FILE=".env.production"
BACKUP_DIR="${TARGET_DIR}_backup_$(date +%Y%m%d_%H%M%S)"

# Check if production environment file exists
if [ ! -f "$SOURCE_DIR/$ENV_FILE" ]; then
    echo -e "${YELLOW}Creating production environment file...${NC}"
    cp "$SOURCE_DIR/.env" "$SOURCE_DIR/$ENV_FILE"
    echo -e "${RED}Please update $ENV_FILE with production values before deploying.${NC}"
    exit 1
fi

# Verify MongoDB connection locally before deploying
echo -e "${YELLOW}Verifying MongoDB connection...${NC}"
php -r "
require_once '$SOURCE_DIR/vendor/autoload.php';
try {
    \$factory = new App\Integration\Database\MongoConnectionFactory();
    \$db = \$factory->create('mongo', ['fallback' => false]);
    echo \"✓ MongoDB connection verified locally\\n\";
} catch (\Exception \$e) {
    echo \"✗ MongoDB connection failed: \" . \$e->getMessage() . \"\\n\";
    exit(1);
}"

if [ $? -ne 0 ]; then
    echo -e "${RED}MongoDB connection verification failed! Aborting deployment.${NC}"
    echo -e "${YELLOW}Check your MongoDB connection settings in $ENV_FILE${NC}"
    exit 1
fi

# Create backup of current deployment
echo -e "${YELLOW}Creating backup of current deployment...${NC}"
ssh "$TARGET_SERVER" "if [ -d $TARGET_DIR ]; then cp -r $TARGET_DIR $BACKUP_DIR; fi"

# Verify PHP extensions on target server
echo -e "${YELLOW}Verifying PHP extensions on target server...${NC}"
ssh "$TARGET_SERVER" "php -m | grep -E 'mongodb|openssl'" > /tmp/php_exts

if ! grep -q "mongodb" /tmp/php_exts || ! grep -q "openssl" /tmp/php_exts; then
    echo -e "${RED}Required PHP extensions missing on target server!${NC}"
    
    if ! grep -q "mongodb" /tmp/php_exts; then
        echo -e "${RED}Missing: mongodb${NC}"
    fi
    
    if ! grep -q "openssl" /tmp/php_exts; then
        echo -e "${RED}Missing: openssl${NC}"
    fi
    
    echo -e "${YELLOW}Please install missing extensions before deploying.${NC}"
    exit 1
fi

# Deploy application files
echo -e "${YELLOW}Deploying application to $TARGET_SERVER:$TARGET_DIR...${NC}"
rsync -avz --exclude-from="$SOURCE_DIR/.deployignore" \
    --exclude ".git" --exclude "node_modules" --exclude "vendor" \
    "$SOURCE_DIR/" "$TARGET_SERVER:$TARGET_DIR/"

# Run post-deployment commands on the server
echo -e "${YELLOW}Running post-deployment tasks...${NC}"
ssh "$TARGET_SERVER" "cd $TARGET_DIR && \
    cp $ENV_FILE .env && \
    composer install --no-dev --optimize-autoloader && \
    # ...existing code...
"

# Verify deployment
echo -e "${YELLOW}Verifying deployment...${NC}"
DEPLOY_CHECK=$(ssh "$TARGET_SERVER" "cd $TARGET_DIR && php -r \"require 'vendor/autoload.php'; echo 'OK';\"")

if [ "$DEPLOY_CHECK" != "OK" ]; then
    echo -e "${RED}Deployment verification failed! Rolling back...${NC}"
    ssh "$TARGET_SERVER" "rm -rf $TARGET_DIR && mv $BACKUP_DIR $TARGET_DIR"
    echo -e "${RED}Deployment rolled back to previous version.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Deployment completed successfully!${NC}"
echo -e "${YELLOW}A backup of the previous deployment is available at: $BACKUP_DIR${NC}"
