<?php

namespace App\Component\Server\Task;

enum Param: string
{
    case GLOBAL_NAMESPACE = 'global.namespace';
    case DOCKER_SWARM_MANAGER_IP = 'docker.swarm.manager_ip';
    case DOCKER_SWARM_TOKEN = 'docker.swarm.token';
    case DOCKER_SWARM_TOKEN_TYPE = 'docker.swarm.token_type';

    case DOCKER_NODE_LIST_FILTER = 'docker.node.list.filter';
    case DOCKER_NODE_ID = 'docker.node.id';
    case DOCKER_NODE_UPDATE_LABEL_ADD = 'docker.node.update.label_add';
    case DOCKER_NODE_UPDATE_LABEL_REMOVE = 'docker.node.update.label_remove';
    case DOCKER_NODE_UPDATE_ROLE = 'docker.node.update.role';
    case DOCKER_NODE_UPDATE_AVAILABILITY = 'docker.node.update.availability';
    case DOCKER_STACK_DEPLOY_COMPOSE_FILE = 'docker.stack.deploy.compose_file';
    case DOCKER_STACK_DEPLOY_APP_NAME = 'docker.stack.deploy.app_name';
    case DOCKER_NETWORK_CREATE_DRIVER = 'docker.network.create.driver';
    case DOCKER_NETWORK_NAME = 'docker.network.name';
    case DOCKER_SERVICE_NAME = 'docker.service.name';
    case DOCKER_SERVICE_CONSTRAINTS = 'docker.service.constraints';
    case DOCKER_SERVICE_LABEL = 'docker.service.label';
    case DOCKER_SERVICE_MODE = 'docker.service.mode';
    case DOCKER_SERVICE_NETWORK = 'docker.service.network';
    case DOCKER_SERVICE_IMAGE = 'docker.service.image';
    case DOCKER_SERVICE_COMMAND = 'docker.service.command';
    case DOCKER_SERVICE_LIST_FILTER = 'docker.service.list.filter';
    case DOCKER_SERVICE_PORT_PUBLISH = 'docker.service.port_publish';
    case DOCKER_SERVICE_MOUNT = 'docker.service.volume';
    case DOCKER_SERVICE_ENV = 'docker.service.env';
    case DOCKER_SERVICE_REPLICAS = 'docker.service.replicas';
    case DOCKER_SERVICE_UPDATE_DELAY = 'docker.service.update_delay';
    case DOCKER_SERVICE_UPDATE_PARALLELISM = 'docker.service.update_parallelism';
    case DOCKER_SERVICE_LIMIT_CPU = 'docker.service.limit_cpu';
    case DOCKER_SERVICE_HEALTH_CMD = 'docker.service.health_cmd';
    case DOCKER_SERVICE_HEALTH_INTERVAL = 'docker.service.health_interval';
    case DOCKER_SERVICE_HEALTH_RETRIES = 'docker.service.health_retries';
    case DOCKER_SERVICE_ENV_FILE = 'docker.service.env_file';
    case DOCKER_SERVICE_LIMIT_MEMORY = 'docker.service.limit_memory';
    case DOCKER_IMAGE_NAME = 'docker.image.name';
    case DOCKER_INSPECT_ID = 'docker.inspect.id';
    case DOCKER_INSPECT_FILTER = 'docker.inspect.filter';
    case DOCKER_IMAGE_TAG = 'docker.image.tag';
    case DOCKER_IMAGE_TARGET = 'docker.image.target';
    case DOCKER_IMAGE_OUTPUT = 'docker.image.output';

    case DOCKER_IMAGE_FILE = 'docker.image.file';
    case DOCKER_VOLUME_NAME = 'docker.volume.name';
    case DOCKER_VOLUME_LIST_FILTER = 'docker.volume.list.filter';
}
