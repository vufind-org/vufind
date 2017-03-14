# -*- mode: ruby -*-
# vi: set ft=ruby :

# All Vagrant configuration is done below.
Vagrant.configure("2") do |config|
  # The most common configuration options are documented and commented below.
  # For a complete reference, please see the online documentation at
  # https://docs.vagrantup.com.

  # Every Vagrant development environment requires a box. You can search for
  # boxes at https://atlas.hashicorp.com/search.
  config.vm.box = "ubuntu/yakkety64"

  # Provider-specific configuration so you can fine-tune various
  # backing providers for Vagrant. These expose provider-specific options.
  # Example for VirtualBox:
  #
  config.vm.provider "virtualbox" do |vb|
    # Display the VirtualBox GUI when booting the machine
    #vb.gui = true
  
    # Customize the amount of resources on the VM:
    vb.cpus = 2
    vb.memory = "2048"
  end

  # Network configuration to forward ports.
  config.vm.network :forwarded_port, guest: 80, host: 4567
  
  # Enable provisioning with a shell script. Additional provisioners such as
  # Puppet, Chef, Ansible, Salt, and Docker are also available. Please see the
  # documentation for more information about their specific syntax and use.
  config.vm.provision "shell", inline: <<-SHELL
    
    # Configure mysql-server package credential values.
    # NOTE: Please don't use these default values in any situation where security is a concern.
    debconf-set-selections <<< 'mysql-server mysql-server/root_password password root'
    debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password root'

    # Install package dependencies.
    apt-get update
    apt-get install -y git zip unzip apache2 default-jdk mysql-server
    apt-get install -y libapache2-mod-php php-mbstring php-pear php php-dev php-gd php-intl php-json php-ldap php-mcrypt php-mysql php-xml php-curl
    
    # Install composer.
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '669656bab3166a7aff8a7506b8cb2d1c292f042046c5a994c43155c0be6190fa0355160742ab2e1c88d40d5be660b410') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    mv composer.phar /usr/local/bin/composer

    # Check out and set up VuFind.
    su - ubuntu -c 'cd /vagrant && composer install && php install.php --non-interactive'
    ln -s /vagrant/local/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf
    a2enmod rewrite
    systemctl restart apache2
  SHELL
end
