# Použijeme oficiální Nginx runtime
FROM nginx:latest

# Zkopírujeme hlavní Nginx konfiguraci
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# Zkopírujeme serverovou konfiguraci
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Zkopírujeme celý obsah aplikace do Nginx kontejneru, včetně složky www
COPY www /usr/share/nginx/html/

# Otevřeme port pro Nginx
EXPOSE 80
