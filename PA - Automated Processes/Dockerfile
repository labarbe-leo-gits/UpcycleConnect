FROM php:8.2-cli

RUN apt-get update && apt-get install -y cron && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

COPY crontab /etc/cron.d/php-cron
RUN chmod 0644 /etc/cron.d/php-cron
RUN crontab /etc/cron.d/php-cron

# Create log file
RUN touch /var/log/cron.log

CMD printenv > /etc/environment && cron && tail -f /var/log/cron.log