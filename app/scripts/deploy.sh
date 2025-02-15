#!/bin/bash

REPO_DIR="/var/www/E-Lib"
BRANCH="master"

cd $REPO_DIR || exit
git fetch origin $BRANCH
git reset --hard origin/$BRANCH
git clean -df

sudo systemctl restart nginx