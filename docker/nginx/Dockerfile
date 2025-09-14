FROM node:24 AS asset-builder

COPY . /app
WORKDIR /app

RUN npm install
RUN npm run build-docker

########################################################################################################################

FROM nginx:latest AS nginx

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY --from=asset-builder /app/www /usr/share/assets

EXPOSE 80
