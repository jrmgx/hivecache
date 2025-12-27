# Deploy In Production

## Coolify

We are going to take coolify as an example but it can be done on other system as well.

This deploy method is docker based.

First, in your infrastructure deploy:
- postgres:16
and make it accessible into the internal network

Second, build the api production image:
- define all the needed environment variables (see .env to have a list)
- build the image `docker build -t bookmarkhive --target api-build -f infrastructure/docker/services/php/Dockerfile .`
- start this image

Third:
- generate a JWT key pair with `bin/console lexik:jwt:generate-keypair`
- save the files to the server in `/api/config/jwt/{public|private}.pem`

Last:
- either you are on Coolify and you expose the api:80
- or you are on your own, and you should expose the api:80 port to the internet via some router
