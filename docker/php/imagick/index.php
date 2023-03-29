<?php
ini_set('memory_limit', '2048M');
require_once 'vendor/autoload.php';
use Aws\S3\S3Client;
if($_GET["link"]){
    try {
        $link = $_GET["link"];
        $image = new Imagick($link);
        $image->thumbnailImage(400, 400);
        $image->setImageFormat("jpg");
        $s3Client = new S3Client([
            'credentials' => [
                'key'      => "qNE6IX8R4VRST5IMogSK",
                'secret'   => "lkWpY-Ds5J8wH2r1JORIfYnafEMPBqrOZMiXtKWC",
            ],
            'version' => 'latest',
            'endpoint' => 'https://storage.yandexcloud.net',
            'region' => 'ru-central1',
        ]);
        $s3Client->registerStreamWrapper();
        $s3Client->putObject(array(
            'Bucket' => "scrm",
            'Key'    => "mini.jpg",
            'Body'   => $image->getimageblob()
        ));

    } catch (ImagickException $e) {
        $body = file_get_contents("/etc/ImageMagick-6/policy.xml");
        echo $body;
    }
} else echo "v.07 vendor S3Client";

var_dump($_REQUEST);

/*return [
    "statusCode" => 200,
    "body" => "v.07 vendor S3Client",
];


FROM php:7.4-apache
WORKDIR /app
COPY ./index.php .
CMD [ "php", "index.php" ]


FROM php:7.4-apache
WORKDIR /app
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install zip
RUN apt update && \
  apt upgrade && \
  apt install -y libmagickwand-dev --no-install-recommends && \
  pecl install imagick && docker-php-ext-enable imagick && \
  rm -rf /var/lib/apt/lists/*
ADD policy.xml /etc/ImageMagick-6/policy.xml
COPY ./index.php .
EXPOSE 80
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer require aws/aws-sdk-php
CMD ["php", "index.php"]
#CMD ["php", "-S", "0.0.0.0:80"]
