FROM ubuntu:25.04

RUN apt update

RUN apt install -y php php-mbstring php-curl php-zip php-xml

COPY . /app

RUN apt install -y composer

WORKDIR /app

RUN composer install

CMD ["php", "manager.php", "export"]