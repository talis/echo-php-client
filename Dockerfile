FROM talis/ubuntu:1404-latest

MAINTAINER Nadeem Shabir "ns@talis.com"

ENV DEBIAN_FRONTEND noninteractive

ARG git_oauth_token

RUN apt-get update && apt-get upgrade -y

RUN apt-get install -y --force-yes curl apt-transport-https && curl -L http://apt.talis.com:81/public.key | sudo apt-key add - && apt-get update

# Install php and ant
RUN apt-get install --no-install-recommends -y \
		curl ca-certificates \
		php5-cli \
		php5-dev \
		php5-xdebug \
		php5-curl \
		php5-json \
		php-pear \
    ant \
    git

# Install composer
RUN curl https://getcomposer.org/installer | php -- && mv composer.phar /usr/local/bin/composer && chmod +x /usr/local/bin/composer

RUN composer config -g github-oauth.github.com $git_oauth_token


# Tidy up
RUN apt-get -y autoremove && apt-get clean && apt-get autoclean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p /var/echo-php-client
COPY . /var/echo-php-client

WORKDIR /var/echo-php-client

RUN ant init

CMD /bin/bash -c "ant test"
