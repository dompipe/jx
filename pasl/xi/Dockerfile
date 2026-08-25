FROM php:8.3-cli-alpine
WORKDIR /app
COPY src/ /app/src/
COPY books/ /app/books/
COPY xi.php config.json /app/
RUN mkdir -p /app/data
ENV XI_DATA=/app/data
EXPOSE 8765
CMD ["php", "xi.php", "0.0.0.0:8765", "start", "config.json", "--foreground"]
