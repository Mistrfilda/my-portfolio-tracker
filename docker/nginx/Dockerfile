FROM node:24 AS asset-builder

COPY . /app
WORKDIR /app

RUN rm -rf /app/www/build/admin
RUN mv /app/www/prod-build/build/admin /app/www/build/admin

RUN rm -f /app/www/swagger-ui/openapi.yaml && cp /app/doc/openapi.yaml /app/www/swagger-ui/openapi.yaml

########################################################################################################################

FROM nginx:latest AS nginx

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY --from=asset-builder /app/www /usr/share/assets

EXPOSE 80
