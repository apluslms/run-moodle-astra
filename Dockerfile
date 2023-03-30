FROM moodlehq/moodle-php-apache:7.4-bullseye

COPY assets/apache2/conf-enabled/ /etc/apache2/conf-enabled/
COPY assets/apache2/sites-enabled/ /etc/apache2/sites-enabled/
COPY assets/apache2/ports.conf /etc/apache2/ports.conf
COPY assets/custom_php.ini ${PHP_INI_DIR}/conf.d/
COPY assets/moodle_init_db.sh /usr/local/bin/moodle_init_db.sh
COPY assets/moodle_add_test_data.php /usr/local/src/moodle_add_test_data.php

WORKDIR /var/www/html

ARG MOODLE_VER=3.11.7
ARG MOODLE_MAJOR_VER=3.11
# branch refers to the download URL, not git version control
ARG MOODLE_BRANCH=stable311

ARG ASTRA_VER=1.11.0
# the setup block plugin
ARG ASTRA_BLOCK_VER=1.3

# xdebug for debugging PHP
RUN pecl install xdebug-2.9.6 \
  && docker-php-ext-enable xdebug \
  && echo "xdebug.remote_enable=on"     >> /usr/local/etc/php/conf.d/xdebug.ini \
  && echo "xdebug.idekey=xdebug"        >> /usr/local/etc/php/conf.d/xdebug.ini \
  && rm -rf /tmp/pear \
  # download Moodle source code
  && cd /tmp \
  && curl -LO https://download.moodle.org/download.php/direct/${MOODLE_BRANCH}/moodle-${MOODLE_VER}.tgz \
  && tar -xzf moodle-${MOODLE_VER}.tgz --directory=/var/www/html --strip-components=1 \
  && chown -R www-data:www-data /var/www/html \
  && rm -f /tmp/moodle-${MOODLE_VER}.tgz \
  # download language packs
  && curl -LO https://download.moodle.org/download.php/direct/langpack/${MOODLE_MAJOR_VER}/sv.zip \
  && curl -LO https://download.moodle.org/download.php/direct/langpack/${MOODLE_MAJOR_VER}/fi.zip \
  && unzip sv.zip -d /var/www/moodledata/lang \
  && unzip fi.zip -d /var/www/moodledata/lang \
  && chown -R www-data:www-data /var/www/moodledata/lang \
  && rm -f /tmp/sv.zip /tmp/fi.zip \
  # download Astra plugin (astra directory into the moodle/mod directory)
  && curl -LO https://github.com/apluslms/moodle-mod_astra/archive/v${ASTRA_VER}.tar.gz \
  && tar -xzf v${ASTRA_VER}.tar.gz --directory=/var/www/html/mod \
    --strip-components=1 moodle-mod_astra-${ASTRA_VER}/astra \
  && chown -R www-data:www-data /var/www/html/mod/astra \
  && rm -f /tmp/v${ASTRA_VER}.tar.gz \
  && rm -f /var/www/html/mod/astra/local_settings.php \
  # download the Astra setup block plugin (astra_setup directory into the moodle/blocks directory)
  && curl -LO https://github.com/apluslms/moodle-block_astra_setup/archive/v${ASTRA_BLOCK_VER}.tar.gz \
  && tar -xzf v${ASTRA_BLOCK_VER}.tar.gz --directory=/var/www/html/blocks \
    --strip-components=1 moodle-block_astra_setup-${ASTRA_BLOCK_VER}/astra_setup \
  && chown -R www-data:www-data /var/www/html/blocks/astra_setup \
  && rm -f /tmp/v${ASTRA_BLOCK_VER}.tar.gz


COPY assets/config.docker-template.php /var/www/html/config.php
COPY assets/astra_local_settings.php /var/www/html/mod/astra/local_settings.php

# ENTRYPOINT has been set in the parent image.
# Install the database on startup and add test data.
CMD ["moodle_init_db.sh"]

