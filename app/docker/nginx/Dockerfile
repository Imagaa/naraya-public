FROM node:22-alpine AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources resources
RUN npm run build

FROM nginx:alpine
COPY public /var/www/html/public
COPY --from=assets /var/www/html/public/build /var/www/html/public/build
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
