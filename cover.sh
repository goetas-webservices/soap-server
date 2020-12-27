#!/bin/bash

vendor/bin/phpunit --coverage-php=code-coverage-data/server.cov
cd soap-client
vendor/bin/phpunit --coverage-php=../code-coverage-data/client.cov
cd ..
php phpcov-8.0.0.phar merge  --html=htm code-coverage-data/
