FROM node:20 AS asset-builder

# Nastavíme pracovní adresář
WORKDIR /app

# Zkopírujeme vše do pracovního adresáře
COPY . .

RUN npm install
RUN npm run build-docker

########################################################################################################################

# Použijeme oficiální Nginx runtime
FROM nginx:latest as nginx

# Zkopírujeme hlavní Nginx konfiguraci
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# Zkopírujeme serverovou konfiguraci
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Zkopírujeme celý obsah aplikace do Nginx kontejneru, včetně složky www
COPY --from=asset-builder /app/www /usr/share/nginx/html/

# Otevřeme port pro Nginx
EXPOSE 80
