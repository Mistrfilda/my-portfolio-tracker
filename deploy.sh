#!/bin/bash

# Cesta k textovému souboru s verzemi
VERSION_FILE="deploy-versions.txt"

# Přečtení verzí z textového souboru
PHP_VERSION=$(sed -n '1p' $VERSION_FILE)
NGINX_VERSION=$(sed -n '2p' $VERSION_FILE)

# Zvýšení verze pro nasazení
NEW_PHP_VERSION=$(echo $PHP_VERSION | awk -F. '{$NF = $NF + 1;} 1' | sed 's/ /./g')
NEW_NGINX_VERSION=$(echo $NGINX_VERSION | awk -F. '{$NF = $NF + 1;} 1' | sed 's/ /./g')

# Aktualizace verzí v souboru před nasazením
echo $NEW_PHP_VERSION > $VERSION_FILE
echo $NEW_NGINX_VERSION >> $VERSION_FILE

# Definice názvů obrazů a repo URL
REPO_URL="192.168.1.245:32000"
PHP_IMAGE="my-portfolio-tracker-php"
NGINX_IMAGE="my-portfolio-tracker-nginx"

# Sestavení Docker obrazů
docker-compose build

# Označení obrazů s verzemi ze souboru
docker tag $PHP_IMAGE $REPO_URL/$PHP_IMAGE:$NEW_PHP_VERSION
docker tag $NGINX_IMAGE $REPO_URL/$NGINX_IMAGE:$NEW_NGINX_VERSION

# Push obrazů do registry
docker push $REPO_URL/$PHP_IMAGE:$NEW_PHP_VERSION
docker push $REPO_URL/$NGINX_IMAGE:$NEW_NGINX_VERSION

echo "Deployment completed with PHP version $NEW_PHP_VERSION and Nginx version $NEW_NGINX_VERSION"
