FROM php:8.1-cli

WORKDIR /app

RUN docker-php-ext-install mysqli

COPY . /app

RUN mkdir -p /app/uploads /app/uploads/tugas \
    && chmod -R 775 /app/uploads

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /app"]
