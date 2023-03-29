FROM ubuntu/apache2:2.4-22.04_beta
WORKDIR /thumb

RUN apt update
RUN apt-get -y install build-essential
RUN apt -y install wget
RUN wget https://download.imagemagick.org/ImageMagick/download/ImageMagick-7.1.0-52.tar.gz
RUN tar xvzf ImageMagick-7.1.0-52.tar.gz
RUN ImageMagick-7.1.0-52/configure
RUN cd ImageMagick-7.1.0-52
RUN make
RUN make install
RUN ldconfig /usr/local/lib

RUN apt-get -y install graphicsmagick

RUN apt -y install nodejs
RUN apt -y install npm

RUN npm install express
RUN npm install gm
RUN npm install aws-sdk

COPY ./ .
CMD [ "node", "index.mjs"]

# Команда RUN исполняется только при первом запуске контейнера, CMD - при каждом
