#!/usr/bin/env bash

COMPOSER_UPDATE="composer update --no-interaction -q"
COMPOSER_TESTS="composer tests:integration"

PHP_VER=$(printf '%s' "${1/./}")
if [[ "$PHP_VER" == "" ]]; then
  PHP_VER="82"
fi

if [[ ! "$2" == "" ]]; then
  COMPOSER_TESTS="${COMPOSER_TESTS} -- --filter=${2}"
fi

cd "docker"
docker compose run --rm "php${PHP_VER}" sh -c "${COMPOSER_TESTS}"; CODE=$?
docker compose down --volumes
exit $CODE
