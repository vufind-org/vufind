# -*- mode: ruby -*-
# vi: set ft=ruby :

# All Vagrant configuration is done below.
Vagrant.configure("2") do |config|
  # The most common configuration options are documented and commented below.
  # For a complete reference, please see the online documentation at
  # https://docs.vagrantup.com.

  # Every Vagrant development environment requires a box. You can search for
  # boxes at https://app.vagrantup.com/boxes/search
  config.vm.box = "ubuntu/jammy64"

  # Provider-specific configuration so you can fine-tune various
  # backing providers for Vagrant. These expose provider-specific options.
  # Example for VirtualBox:
  #
  config.vm.provider "virtualbox" do |vb|
    # Display the VirtualBox GUI when booting the machine
    # vb.gui = true

    # Customize the amount of resources on the VM:
    vb.cpus = 2
    vb.memory = "2048"
  end

  # With Docker provider we need to use a Docker-friendly box.
  config.vm.provider "docker" do |d, override|
    override.vm.box = "tknerr/baseimage-ubuntu-22.04"
  end

  # Network configuration to forward ports.
  config.vm.network :forwarded_port, guest: 80, host: 4567
  config.vm.network :forwarded_port, guest: 8983, host: 4568
  config.vm.synced_folder ".", "/vagrant", :owner => 'vagrant'

  # Enable provisioning with a shell script. Additional provisioners such as
  # Puppet, Chef, Ansible, Salt, and Docker are also available. Please see the
  # documentation for more information about their specific syntax and use.
  config.vm.provision "shell", inline: <<-SHELL

    # Configure mysql-server package credential values.
    # NOTE: Please don't use these default values in any situation where security is a concern.
    debconf-set-selections <<< 'mysql-server mysql-server/root_password password root'
    debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password root'

    # Install package dependencies.
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y git zip unzip apache2 default-jdk mysql-server lsof \
    libapache2-mod-php php-pear php php-curl php-dev php-gd php-intl php-json php-ldap php-mbstring php-mysql php-soap php-xml

    # Install composer.
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php -r "if (hash_file('SHA384', 'composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    mv composer.phar /usr/local/bin/composer

    # Check out and set up VuFind.
    mkdir -p /vufindlocal/cache/cli /vufindlocal/config/vufind
    chown -R vagrant:vagrant /vufindlocal
    su - vagrant -c 'cd /vagrant && composer install && php install.php --non-interactive --overridedir=/vufindlocal'
    ln -s /vufindlocal/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf
    a2enmod rewrite

    # Restart Apache (and MySQL to be sure it's running).
    if [[ -d /run/systemd/system ]]; then
      systemctl restart apache2
      systemctl restart mysql
    else
      service apache2 restart
      service mysql restart
    fi

    # Set up cache and config permissions.
    chown -R www-data:www-data /vufindlocal/cache /vufindlocal/config/vufind
    chmod 777 /vufindlocal/cache/cli

    # Set up profile for command line.
    echo export VUFIND_HOME=/vagrant > /etc/profile.d/vufind.sh
    echo export VUFIND_LOCAL_DIR=/vufindlocal >> /etc/profile.d/vufind.sh
  SHELL
end
