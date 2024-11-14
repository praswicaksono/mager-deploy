# Mager Deploy 🚀


The Effortless Deployment Tool for the Lazy Developer. Deploy your development environment straight to prod only in one command. Under the hood Mager use docker swarm to orchestrate your container.


# Installation

Current supported OS

* Linux
* MacOS
* Windows via WSL

# Requirement

* mkcert (need when you use mager in local)
* docker

```sh
curl https://raw.githubusercontent.com/praswicaksono/mager-deploy/refs/heads/master/install.sh | sh
```

# Mager Definition

Instead of `docker-compose.yaml`, Mager have their own definition called `mager.yaml`, Mager will read this configuration to build and deploy your application. Here is complete example of `mager.yaml`

```yaml
name: project-name
build:
  target: frankenphp_prod
  context: .
  image: someimage # If you define this, mager will use this image instead of build your image based on Dockerfile
services:
  service-name:
    cmd: ['php', '-v']
    option:
      limit_cpu: 0.25
      limit_memory: 512MB
    execute_once: # will execute on first deploy
      - echo '1'
    before_deploy: # will execute each time before deploy
      - echo '1'
    after_deploy: # will execute each time after deploy
      - echo '1'
    hosts:
      - host.docker.internal:host-gateway
    volumes:
      - ./:/app
    env:
      SERVER_NAME: ${SERVER_NAME:-:80}
      MERCURE_PUBLISHER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
      MERCURE_SUBSCRIBER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
      MERCURE_URL: ${CADDY_MERCURE_URL:-https://symfony-demo.wip/.well-known/mercure}
      MERCURE_PUBLIC_URL: ${CADDY_MERCURE_PUBLIC_URL:-https://symfony-demo.wip/.well-known/mercure}
      MERCURE_JWT_SECRET: ${CADDY_MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
    proxy:
      ports:
        - 80/tcp
      rule: Host(`{$host}`) && Path('/') # ${$host} is placeholder, it will replace by value of host attribute
      host: demo-app.com
```

# Development

Mager not just for deployment, it can be used to replicate your production environment to your local environment. Everything works in local should also work in production.

## Initializing Project

Once you init your project, you can generate Mager definition based on template that selected

```sh
mager init
```

## Deployment Preview on Local

First you need to add entry your host that you have defined in `mager.yaml` to system host file

```shell
sudo echo '127.0.0.1 demo-app.com' >> /etc/hosts
```

The run deployment

```shell
mager dev
```

It will install `proxy` and TLS certificate using `mkcert`.

Add `mkcert` root CA to system certificate store

```shell
CAROOT=~/.mager/certs mkcert -install
```

# Deploying To Production

Before you deploy to production, first you have to create `namespace`.

`Namespace` here is a set of servers within same network. You can add same server in different `namespace` but application that deployed will have different network.

```sh
mager namespace:add <your-namespace>
```

It will prompt you few question for server credentials.

**WARNING!** _For now `Mager` have limitation can only deploy on single server_

Then you have to prepare `namespace`, it will setup docker swarm, creating network and install proxy.

```shell
mager prepare <your-namespace>
```

And at last you can deploy your service to specific `namespace`

```shell
mager deploy <your-namespace>
```