<?php
declare(strict_types=1);

return function(): \Generator {
    yield "Upgrading Packages" => <<<CMD
        DEBIAN_FRONTEND=noninteractive sudo apt-get update -y && sudo apt-get upgrade -y
    CMD;

    yield "Installing Docker" => <<<CMD
        sudo apt-get install ca-certificates curl
        sudo install -m 0755 -d /etc/apt/keyrings
        sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
        sudo chmod a+r /etc/apt/keyrings/docker.asc
        echo \
          "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
          $(. /etc/os-release && echo "\$VERSION_CODENAME") stable" | \
          sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
        sudo apt-get update -y && sudo apt-get install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin -y
    CMD;

    yield "Setup Firewall" => <<<CMD
         sudo apt-get install ufw -y
         sudo ufw allow OpenSSH
         sudo ufw allow http
         sudo ufw allow https
         sudo ufw allow 2377/tcp
         sudo ufw allow 7946
         sudo ufw allow 4789/udp
    CMD;

    yield "Disable Password Login" => <<<CMD
        sudo sed -E -i 's|^#?(PasswordAuthentication)\s.*|\1 no|' /etc/ssh/sshd_config
        if ! grep '^PasswordAuthentication\s' /etc/ssh/sshd_config; then echo 'PasswordAuthentication no' |sudo tee -a /etc/ssh/sshd_config; fi
    CMD;
};