FROM debian:latest

RUN apt-get update && apt-get install -qqy \
    php-cli php-mysql php-mcrypt php-mbstring php-curl php-xml php-json php-intl php-gd php-xdebug \
	--no-install-recommends && rm -r /var/lib/apt/lists/*

VOLUME ["/www"]
EXPOSE 8001