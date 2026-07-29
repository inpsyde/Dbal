#!/usr/bin/env bash

# Generates a throwaway CA and a server certificate for the local/CI-only
# "dbal-mysql" test container, so integration tests run over TLS instead of
# plaintext. Nothing here should ever be reused outside this test setup.
set -euo pipefail

CERT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/certs"

if [[ -f "${CERT_DIR}/ca.pem" && -f "${CERT_DIR}/server-cert.pem" && -f "${CERT_DIR}/server-key.pem" ]]; then
  exit 0
fi

rm -rf "${CERT_DIR}"
mkdir -p "${CERT_DIR}"
cd "${CERT_DIR}"

openssl genrsa -out ca-key.pem 2048 >/dev/null 2>&1
openssl req -x509 -new -nodes -key ca-key.pem -sha256 -days 3650 \
  -out ca.pem -subj "/CN=dbal-test-ca" >/dev/null 2>&1

openssl genrsa -out server-key.pem 2048 >/dev/null 2>&1
openssl req -new -key server-key.pem -out server.csr -subj "/CN=dbal-mysql" >/dev/null 2>&1
printf 'subjectAltName=DNS:dbal-mysql,DNS:localhost\n' > san.ext
openssl x509 -req -in server.csr -CA ca.pem -CAkey ca-key.pem -CAcreateserial \
  -out server-cert.pem -days 3650 -sha256 -extfile san.ext >/dev/null 2>&1

rm -f server.csr san.ext ca.srl

# mysqld (running as the "mysql" user in the container) must be able to read
# the key; it's a throwaway test-only key with no real-world value.
chmod 644 ca-key.pem server-key.pem
