# Mager Deploy 🚀


The Effortless Deployment Tool for the Lazy Developer.


# Installation

Current supported OS

* Linux
* MacOS
* Windows via WSL

```sh
curl https://raw.githubusercontent.com/praswicaksono/mager-deploy/refs/heads/master/install.sh | sh
```
# Deploy Your First Application

To deploy your first application, this tools rely on dead simple configuration called `mager.yaml`.
It will scan your working directory and deploy to your defined `namespace`

## Initializing

First you have to initialize a `namespace` for your project. `namespace` can be treated as separate environment or different set of cluster. In future `namespace` can contain multiple servers and grouped as a cluster.

Initializing `local` namespace, it will use your local machine as host instead of remote server. It can be treated as development environment, you could test deployment on `local` namespace first before doing actual deployment on remote server.

```sh
mager init --local
```

To add new namespace, it will ask your remote server credentials

```sh
mager namespace:add your_namespace
```

## Preparing Server

Before do deployment server need to be prepared first. It need to install required package and run load balancer.

```sh
mager prepare your_namespace
```

## Deploying

For example we have this following `mager` definition in our project dir

```yaml
name: sample-app
services:
  nginx:
    proxy:
      ports:
        - 80/tcp
      rule: Host(`{$host}`)
      host:  nginx.praswicaksono.pw
    after_deploy:
      - echo 'true'
    before_deploy:
      - echo 'true'
```

With those definition above, `mager` will scan `Dockerfile` in current working dir and build an image from it. Given this example of `Dockerfile`

```dockerfile
FROM nginx

COPY mager.html /usr/share/nginx/html
```

Last step just do

```sh
mager deploy your_namespace
```

Those command above will build your image and upload to your target server, this deployment doesnt require dockerhub or other repository for now.
