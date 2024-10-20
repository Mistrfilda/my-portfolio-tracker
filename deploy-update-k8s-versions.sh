#!/bin/bash

# Cesta k adresáři se soubory Kubernetes definic
DIRECTORY=$1

# Kontrola, zda byl zadán adresář
if [ -z "$DIRECTORY" ]; then
  echo "Uveďte prosím cestu k adresáři se soubory Kubernetes definic."
  exit 1
fi

# Cesta k textovému souboru s verzemi
VERSION_FILE="deploy-versions.txt"

# Kontrola, zda existuje version.txt
if [ ! -f "$VERSION_FILE" ]; then
  echo "Soubor $VERSION_FILE neexistuje!"
  exit 1
fi

# Přečtení verzí z textového souboru
PHP_VERSION=$(sed -n '1p' $VERSION_FILE)
NGINX_VERSION=$(sed -n '2p' $VERSION_FILE)

# Procházení všech relevantních souborů ve zadaném adresáři a podsložkách
find "$DIRECTORY" -type d \( -name ".git" -o -name ".idea" \) -prune -o -type f -print | while read -r FILE; do
  echo "Aktualizace souboru: $FILE"

  # Náhrada verze pro PHP image
  sed -i.bak "s|\(image: .*/my-portfolio-tracker-php:\).*|\1$PHP_VERSION|g" "$FILE"

  # Náhrada verze pro NGINX image
  sed -i.bak "s|\(image: .*/my-portfolio-tracker-nginx:\).*|\1$NGINX_VERSION|g" "$FILE"

  # Odstranění záložních souborů vytvořených sed
  rm "$FILE.bak"
done

echo "Verze byly úspěšně aktualizovány ve všech relevantních souborech v adresáři: $DIRECTORY"
