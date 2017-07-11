FROM debian:latest

ADD sources.list /etc/apt/

RUN apt-get update
RUN apt-get install -qqy \
    php-cli php-mysql php-mcrypt php-mbstring php-curl php-xml php-json php-intl php-gd php-xdebug \
	--no-install-recommends
RUN rm -r /var/lib/apt/lists/*

VOLUME ["/www"]
EXPOSE 8001
