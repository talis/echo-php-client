FROM ubuntu:trusty-20160526

MAINTAINER Omar Qureshi "oq@talis.com"

ENV DEBIAN_FRONTEND noninteractive
COPY docker/sources.list /etc/apt/sources.list.d/talis.list
RUN apt-get update && apt-get upgrade -y

RUN apt-get install -y --force-yes php5 php5-curl ant