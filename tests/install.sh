#!/usr/bin/env bash
composer create-project symfony/skeleton async-symfony && \
cd ./async-symfony && \
composer require server && \
composer config repositories.foo vcs git@github.com:torinaki/AmpBundle.git && \
composer require torinaki/amp-bundle:dev-master && \
cp ./async-symfony/vendor/torinaki/amp-bundle/server.php.dist ./async-symfony/config/server.php