FROM composer:latest
RUN docker-php-ext-install mysqli
RUN apk add --no-cache bash less mysql-client netcat-openbsd
COPY ./wait-for.sh /usr/bin/wait-for.sh
RUN chmod +x /usr/bin/wait-for.sh
WORKDIR /app
