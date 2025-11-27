FROM php:8.2-cli

WORKDIR /app

# Tüm projeyi konteynera kopyala
COPY . .

# Railway genelde PORT env kullanır, biz 8080 kullanalım
ENV PORT=8080

EXPOSE 8080

# index.php hangi dosyaysa onu yaz
CMD ["php", "-S", "0.0.0.0:8080", "index.php"]
