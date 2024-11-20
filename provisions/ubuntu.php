<?php
declare(strict_types=1);

return function(): \Generator {
    yield "Upgrading Packages" => <<<CMD
        DEBIAN_FRONTEND=noninteractive sudo apt-get update -y && sudo apt-get upgrade -y
        sudo apt-get install ca-certificates curl wget git jq openssl
    CMD;

    yield "Installing Docker" => <<<CMD
        curl -s https://releases.rancher.com/install-docker/27.0.3.sh | sh 2>&1
    CMD;

    yield "Setup Firewall" => <<<CMD
         sudo apt-get install ufw -y
         sudo ufw allow OpenSSH
         sudo ufw allow http
         sudo ufw allow https
         sudo ufw allow 2377/tcp
         sudo ufw allow 7946
         sudo ufw allow 4789/udp
         sudo ufw reload
    CMD;

    yield "Disable Password Login" => <<<CMD
        sudo sed -E -i 's|^#?(PasswordAuthentication)\s.*|\1 no|' /etc/ssh/sshd_config
        if ! grep '^PasswordAuthentication\s' /etc/ssh/sshd_config; then echo 'PasswordAuthentication no' |sudo tee -a /etc/ssh/sshd_config; fi
    CMD;
};